<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

use App\Http\Resources\Ban as BanResource;
use App\Http\Resources\Flag as FlagResource;

use App\Post;
use App\Comment;
use App\Flag;
use App\Ban;

/**
 * @group Moderation
 *
 * All bans are handled by the observer hooked to flags, so there is no need to handle logic here.
 * Simply insert flags into the DB.
 */
class FlagsController extends ApiController
{
    // Flagging flood control interval - in seconds
    protected const FLOOD_CONTROL_INTERVAL = 10;

    // Unfortunately this is duplicated in FlagObserver, TBD
    public $verdicts = [
        "5.1" => "违反《中华人民共和国宪法》等(一)中规定的法律法规的内容",
        "5.2" => "侮辱他人或特定群体的内容，例如: 煽动民族矛盾、地域歧视、对个人进行侮辱性语言攻击",
        "5.3" => "侵犯他人隐私的内容，例如: 未经许可暴露他人具体姓名、个人信息、联系方式",
        "5.4" => "侵犯他人知识产权、商业机密的内容",
        "5.5" => "任何形式的商业广告或带有盈利性目的的内容，比如公众号二维码，培训机构，各种软件等; 任何形式的校内公益资源 (包括但不限于床位、免费票、学生证) 的有偿转让信息",
        "5.6" => "散布谣言和恐慌，可能会误导他人的内容",
        "5.7" => "鼓励、教唆他人实施违法犯罪、暴力、非法集会的内容",
        "5.8" => "含有任何淫秽、色情、性暗示、不当暴露的内容",
        "5.9" => "含有隐喻、容易引起歧义的缩写、代名词、特定表述等对于政治等敏感问题进行引导、讨论",
        "5.10" => "",
        "5.11" => "",
        "5.12" => "",
        "5.13" => "干扰社区正常运营，大量重复内容垃圾发帖等",
        "5.14" => "经多次举报自动判定引起他人不适的内容",
        "5.15" => "经本人要求删除涉及个人隐私、过多细节或对本人生活造成不良影响的本人发帖",
        "5.99" => "测试理由 (本删除的贴子为系统或测试发帖)"
    ];

    public $fold_verdicts = ["政治相关", "性相关", "引战", "偏离话题", "未经证实的传闻", "令人不适"]; // "未知"

    /**
     * @group Moderation
     *
     * Report hole
     * Replaces `report`. Reports this main post for deletion (type = `report`) or hiding (type = `fold`)
     *
     * When type is `fold`:
     * For ring < 4, the post will be folded immediately. Otherwise, it will take `MIN_PUBLIC_FOLD_THRESHOLD` (right now `3`) to fold this post.
     * For ring < 4, if the post was already folded, the reason will be overridden.
     * The API is throttled for once in 10 seconds with a silent drop if flood control is not met.
     * The folding reason will be used directly (if reporter is author) or using the most voted one (otherwise) and saved into `verdict` in either table.
     *
     * Possible errors:
     * - `AlreadyFlagged`: cannot flag the same post/comment twice.
     * - `CannotFlagAlreadyBanned`: if there is ban history, the flag will be dropped
     * If entitlement `Sudoers`, then these checks are relaxed.
     *
     * @queryParam user_token  required
     * @urlParam   id          required
     * @bodyParam  content     required   Report reason. Can be empty but field must exist.
     * @bodyParam  type                   `report` or `fold`. Default: `report`
     *
     * @response { "error": null }
     * @response { "error_msg": "Error Message", "error": "NotFound" }
     * @response { "error_msg": "已经举报过了", "error": "AlreadyFlagged" }
     */
    public function post(Request $request, Int $id) {
        if(!$request->has("content")) {
            return $this->errorResponse("InsufficientParameters");
        }

        $type = $request->input("type") ?? "report";
        if($type != "report" && $type != "fold") {
            return $this->errorResponse("NotImplemented");
        }

        $pData = Post::find($id);
        if(!$pData) {
            return $this->errorResponse("NotFound");
        }

        // Verify ring privileges. Ring 0, 1 can access all hidden posts
        if(!$request->uData->entitlements["ViewingDeleted"] && $pData->hidden > 1) {
            return $this->errorResponse("NotFound");
        }

        // Content
        $content = $request->input("content") ?? "";

        if($type == "fold") {
            // Check for reason - if reason is invalid, override content
            if(!in_array($content, $this->fold_verdicts)) {
                $content = "令人不适";
            }

            // Check if self fold - if yes, do not pass flood control
            // dont write here as we havent verified ring yet
            $pDataUID = (int) Crypt::decryptString($pData->user_id_enc);
            if($pDataUID == $request->uData->id && $pData->hidden == 0) {
                $pData->hidden = 1;
                $pData->verdict = $content;
                $pData->save();
                return $this->successResponse();
            }

            if(Cache::get('_hapi_last_fold_' . $request->uData->id, 0) + self::FLOOD_CONTROL_INTERVAL > time()) {
                return $this->successResponse(); // drop silently
            }

            // Write to flood control cache
            // and store for 60 seconds (longer than the cache flood control duration)
            Cache::put('_hapi_last_fold_' . $request->uData->id, time(), 20);
        }

        // Check if a flag already exists
        $fData = Flag::where("user_id", $request->uData->id)->where("post_id", $id)->where("type", "report")->count();
        if($fData > 0 && !$request->uData->entitlements["Sudoers"]) {
            return $this->errorResponse("AlreadyFlagged");
        }

        // No user should be punished twice. If there is a match with ban_id, abort
        // unless user is in sudoers group in case the flag will be applied again
        $bCount = Ban::where("post_id", $pData->id)->count();
        if($bCount > 0 && !$request->uData->entitlements["Sudoers"]) {
            return $this->errorResponse("CannotFlagAlreadyBanned");
        }

        // Add a flag
        $fData = new Flag;
        $fData->post_id = $id;
        $fData->type = $type;
        $fData->user_id = $request->uData->id;
        $fData->content = $content;

        $fData->save();

        return $this->successResponse();
    }

    /**
     * @group Moderation
     *
     * Report comment
     * Replaces `report`. Reports this comment for deletion (type = `report`) or hiding (type = `fold`)
     *
     * When type is `fold`:
     * For ring < 4, the comment will be folded immediately. Otherwise, it will take `MIN_PUBLIC_FOLD_THRESHOLD` (right now `3`) to fold this comment.
     * For ring < 4, if the post was already folded, the reason will be overridden.
     * The API is throttled for once in 10 seconds with a silent drop if flood control is not met.
     * The folding reason will be used directly (if reporter is author) or using the most voted one (otherwise) and saved into `verdict` in either table.
     *
     * Possible errors:
     * - `AlreadyFlagged`: cannot flag the same post/comment twice.
     * - `CannotFlagAlreadyBanned`: if there is ban history, the flag will be dropped
     * If entitlement `Sudoers`, then these checks are relaxed.
     *
     * @queryParam user_token  required
     * @urlParam   id          required
     * @bodyParam  content     required   Report reason. Can be empty but field must exist.
     * @bodyParam  type                   `report` or `fold`. Default: `report`
     *
     * @response { "error": null }
     * @response { "error_msg": "Error Message", "error": "NotFound" }
     * @response { "error_msg": "已经举报过了", "error": "AlreadyFlagged" }
     */
    public function comment(Request $request, Int $id) {
        if(!$request->has("content")) {
            return $this->errorResponse("InsufficientParameters");
        }

        $type = $request->input("type") ?? "report";
        if($type != "report" && $type != "fold") {
            return $this->errorResponse("NotImplemented");
        }

        $cData = Comment::find($id);
        if(!$cData) {
            return $this->errorResponse("NotFound");
        }

        $pData = Post::find($cData->post_id);
        if(!$pData) {
            return $this->errorResponse("NotFound");
        }

        // Verify ring privileges. Ring 0, 1 can access all hidden posts
        if(!$request->uData->entitlements["ViewingDeleted"] && ($pData->hidden > 1 || $cData->hidden > 1)) {
            return $this->errorResponse("NotFound"); // do not leak state
        }

        // Content
        $content = $request->input("content") ?? "";

        if($type == "fold") {
            // Check for reason - if reason is invalid, override content
            if(!in_array($content, $this->fold_verdicts)) {
                $content = "令人不适";
            }

            // Check if self fold - if yes, do not pass flood control
            // dont write here as we havent verified ring yet
            $cDataUID = (int) Crypt::decryptString($cData->user_id_enc);
            if($cDataUID == $request->uData->id && $cData->hidden == 0) {
                $cData->hidden = 1;
                $cData->verdict = $content;
                $cData->save();
                return $this->successResponse();
            }

            if(Cache::get('_hapi_last_fold_' . $request->uData->id, 0) + self::FLOOD_CONTROL_INTERVAL > time()) {
                return $this->successResponse(); // drop silently
            }

            // Write to flood control cache
            // and store for 60 seconds (longer than the cache flood control duration)
            Cache::put('_hapi_last_fold_' . $request->uData->id, time(), 20);
        }

        // Check if a flag already exists
        $fData = Flag::where("user_id", $request->uData->id)->where("comment_id", $id)->where("type", $type)->count();
        if($fData > 0 && !$request->uData->entitlements["Sudoers"]) {
            return $this->errorResponse("AlreadyFlagged");
        }

        // No user should be punished twice. If there is a match with ban_id, abort
        // unless user is in sudoers group in case the flag will be applied again
        $bCount = Ban::where("comment_id", $pData->id)->count();
        if($bCount > 0 && !$request->uData->entitlements["Sudoers"]) {
            return $this->errorResponse("CannotFlagAlreadyBanned");
        }

        // Add a flag
        $fData = new Flag;
        $fData->comment_id = $id;
        $fData->type = $type;
        $fData->user_id = $request->uData->id;
        $fData->content = $content;

        $fData->save();

        return $this->successResponse();
    }

    /**
     * View Post Flags
     *
     * View flags attached to post.
     * If a ban action was imposed, the ban information will be available in `ban_data`.
     *
     * An **encrypted** representation of the `user_id` is passed and known to the server. This encrypted representation can be used to look up flags by this user in a different future API.
     *
     * Requires entitlement `ViewingFlags`.
     *
     * @queryParam user_token  required
     * @urlParam   id          required
     *
     * @response { "error": null, "data": { "created_at": 1234567890, "content": "reason", "user_id": "ab9210fca90" }, "ban_data": null }
     * @response { "error_msg": "无权查看此页面", "error": "NoPermission"}
     */
    public function viewPost(Request $request, Int $id) {
        if(!$request->uData->entitlements["ViewingFlags"]) {
            return $this->errorResponse("NoPermission");
        }

        $pData = Post::find($id);
        if(!$pData) {
            return $this->errorResponse("NotFound"); // do not leak state
        }

        // Even though the post may be hidden, we still show the flags.
        // This is intentional behavior.
        $fDatas = Flag::where("post_id", $id)->get();
        $bData  = Ban::where("post_id", $id)->first();

        return $this->successResponse([
            "data" => FlagResource::collection($fDatas),
            "ban_data" => ($bData ? new BanResource($bData) : null)
        ]);
    }

    /**
     * View Comment Flags
     *
     * View flags attached to comment.
     * If a ban action was imposed, the ban information will be available in `ban_data`.
     *
     * An **encrypted** representation of the `user_id` is passed and known to the server. This encrypted representation can be used to look up flags by this user in a different future API.
     *
     * Requires entitlement `ViewingFlags`.
     *
     * @queryParam user_token  required
     * @urlParam   id          required
     *
     * @response { "error": null, "data": { "created_at": 1234567890, "content": "reason", "user_id": "ab9210fca90" }, "ban_data": null }
     * @response { "error_msg": "无权查看此页面", "error": "NoPermission"}
     */
    public function viewComment(Request $request, Int $id) {
        if(!$request->uData->entitlements["ViewingFlags"]) {
            return $this->errorResponse("NoPermission");
        }

        $cData = Comment::find($id);
        if(!$cData) {
            return $this->errorResponse("NotFound"); // do not leak state
        }

        // Even though the post may be hidden, we still show the flags.
        // This is intentional behavior.
        $fDatas = Flag::where("comment_id", $id)->get();
        $bData  = Ban::where("comment_id", $id)->first();

        return $this->successResponse([
            "data" => FlagResource::collection($fDatas),
            "ban_data" => ($bData ? new BanResource($bData) : null)
        ]);
    }

    /**
     * Unban Post
     *
     * "Unflags" a post, removing the imposed ban. Requires entitlement `UndoBan`.
     * Once a post has been unbanned, it can *never* be banned again.
     *
     * Requires entitlement `UndoBan`.
     *
     * @queryParam user_token  required
     * @urlParam   id          required
     * @bodyParam  undelete    int       required   undo delete action? (`0` or `1`)
     * @bodyParam  unfold      int       required   undo fold action? (`0` or `1`)
     * @bodyParam  unban       int       required   undo ban action? (`0` or `1`)
     * @bodyParam  reason      string               unban reason - will be recorded on file
     *
     */
    public function unPost(Request $request, Int $id) {
        if(!$request->uData->entitlements["UndoBan"]) {
            return $this->errorResponse("NoPermission");
        }

        if(!$request->has(["undelete", "unfold", "unban"])) return $this->errorResponse("InsufficientParameters");

        // Locate the post
        $pData = Post::find($id);
        if(!$pData) {
            return $this->errorResponse("NotFound");
        }

        $undelete = boolval($request->input("undelete"));
        $unfold   = boolval($request->input("unfold"));
        $unban    = boolval($request->input("unban"));

        if($undelete) {
            if($pData->hidden == 2) {
                $pData->hidden = 0;
                $pData->save();
            }
        }

        if($unfold) {
            if($pData->hidden == 1) {
                $pData->hidden = 0;
                $pData->save();
            }
        }

        // Locate the ban.
        $bData = Ban::where("post_id", $id)->first();
        if(!$bData && !$unfold) {
            return $this->errorResponse("NotBannedCannotUndo");
        }

        if($bData) {

            $user_repl = substr(sha1("-unique-hash-aba810238-" . $request->uData->id), 0, 8);

            // Write to ban log
            $bData->reason .= "\n[" . now() . "] File opened\n";
            if($request->input("reason") !== null) {
                $bData->reason .= "- [" . now() . "] {$user_repl} 手动操作理由:\n------------------\n" . $request->input("reason") . "\n------------------\n";
            }
            if($unban) {
                $bData->until = now();
                $bData->reason .= "- [" . now() . "] 由 {$user_repl} 解除封禁\n";
            }
            
            if($undelete) $bData->reason .= "- [" . now() . "] 由 {$user_repl} 解除删帖\n";

            $bData->save();

        }

        return $this->successResponse();
    }

    /**
     * Unban Comment
     *
     * "Unflags" a post, removing the imposed ban. Requires entitlement `UndoBan`.
     * Once a post has been unbanned, it can *never* be banned again.
     *
     * Requires entitlement `UndoBan`.
     *
     * @queryParam user_token  required
     * @urlParam   id          required
     * @bodyParam  undelete    int       required   undo delete action? (`0` or `1`)
     * @bodyParam  unfold      int       required   undo fold action? (`0` or `1`)
     * @bodyParam  unban       int       required   undo ban action? (`0` or `1`)
     * @bodyParam  reason      string               unban reason - will be recorded on file
     *
     */
    public function unComment(Request $request, Int $id) {
        if(!$request->uData->entitlements["UndoBan"]) {
            return $this->errorResponse("NoPermission");
        }

        if(!$request->has(["undelete", "unfold", "unban"])) return $this->errorResponse("InsufficientParameters");

        // Locate the comment
        $cData = Comment::find($id);
        if(!$cData) {
            return $this->errorResponse("NotFound");
        }

        $undelete = boolval($request->input("undelete"));
        $unfold   = boolval($request->input("unfold"));
        $unban    = boolval($request->input("unban"));

        if($undelete) {
            if($cData->hidden == 2) {
                $cData->hidden = 0;
                $cData->save();
            }
        }

        if($unfold) {
            if($cData->hidden == 1) {
                $cData->hidden = 0;
                $cData->save();
            }
        }

        // Locate the ban.
        $bData = Ban::where("comment_id", $id)->first();
        if(!$bData && !$unfold) {
            return $this->errorResponse("NotBannedCannotUndo");
        }

        if($bData) {

            $user_repl = substr(sha1("-unique-hash-aba810238-" . $request->uData->id), 0, 8);

            // Write to ban log
            $bData->reason .= "\n[" . now() . "] File opened\n";
            if($request->input("reason") !== null) {
                $bData->reason .= "- [" . now() . "] {$user_repl} 手动操作理由:\n------------------\n" . $request->input("reason") . "\n------------------\n";
            }
            if($unban) {
                $bData->until = now();
                $bData->reason .= "- [" . now() . "] 由 {$user_repl} 解除封禁\n";
            }
            
            if($undelete) $bData->reason .= "- [" . now() . "] 由 {$user_repl} 解除删帖\n";

            // Check if we have to update the verdict. This is achieved by not having either undelete or unban,
            // and having a reason code
            if(!$unban && !$undelete) {
                $verdictCode = "0.0";
                $matches = array();
                if(preg_match("/(5\.\d+)/siu", $request->input("reason"), $matches)) {
                    if(isset($this->verdicts[$matches[0]])) {
                        $verdictCode = $matches[0];
                        // else silently drop
                    }
                }

                if(isset($this->verdicts[$matches[0]])) {
                    $bData->verdict = $verdictCode . " - " . $this->verdicts[$verdictCode];
                } // save...
            }

            $bData->save();

        }

        return $this->successResponse();
    }

}
