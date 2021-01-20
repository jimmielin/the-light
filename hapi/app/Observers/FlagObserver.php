<?php

namespace App\Observers;

use App\Ban;
use App\Post;
use App\Comment;
use App\Flag;
use App\Message;
use App\User;

use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class FlagObserver
{
    // How many folding flags required before the post is folded?
    // Note, ring < 4 folding flags always take effect immediately
    protected const MIN_PUBLIC_FOLD_THRESHOLD = 3;

    // How many public flags required before the created_report routine runs?
    protected const MIN_PUBLIC_FLAG_THRESHOLD = 10;

    // When the weights add up to 100, the post is deleted.
    // Tune these parameters depending on user base size.
    protected const PUBLIC_FLAG_WEIGHT_BASE   = 10;
    protected const RING_FLAG_WEIGHT_BASE     = 25;
    protected const SUDO_FLAG_WEIGHT_BASE     = 1000;

    // The weight will decay 1/e^(replies / reply_factor)
    // using a factor of 100:
    //
    //  replies        10      20     ...  50    100    200
    //  weight X      0.90    0.82        0.60   0.37   0.14
    //  at 10,10       12      13          17     27     72
    protected const REPLY_FLAG_DECAY_FACTOR   = 50;

    // List of reasons - note that this is duplicated in FlagsController unfortunately
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

    /**
     * Handle the flag "created" event.
     * This will control banning and administrative actions that are automated through reports.
     *
     * @param \App\Flag $fData
     * @return void
     */
    public function created(Flag $fData): void {
        // Check which type of flag is being processed (type = report, fold, other)
        // then launch the internal routine.
        switch($fData->type) {
            case "report":
                $this->created_report($fData);
            break;

            case "fold":
                $this->created_fold($fData);
            break;

            default:
                // do nothing
        }
    }

    /**
     * Handle "report" kind Flag creation
     * @param \App\Flag $fData
     * @return void
     */
    private function created_report(Flag $fData): void {
        // Check a few prerequisite conditions.
        $toProcess = false;

        $flagTargetType = $fData->post_id !== null ? "post" : "comment";

        // 1: Was this a flag by Ring 0-2? If yes, verify tally
        $toProcess = $toProcess || $fData->user->ring <= 2;

        // 2: Are there more than MIN_PUBLIC_FLAG_THRESHOLD reports?
        $toProcess = $toProcess || 
          (($fCount = ($flagTargetType == "post" ? 
                      Flag::where("post_id", $fData->post_id)->where("type", "report")->count() :
                      Flag::where("comment_id", $fData->comment_id)->where("type", "report")->count())) > self::MIN_PUBLIC_FLAG_THRESHOLD);

        if(!$toProcess) return;

        // If yes - fire up the decision engine.
        $banReason = "Hummingbird Banhammer Check\n";
        if(isset($fCount)) $banReason .= "当前举报数 {$fCount}\n";
        $total = 0;

        $fDatas = ($flagTargetType == "post" ? 
                    Flag::where("post_id", $fData->post_id)->where("type", "report")->with('user')->latest()->get() :
                    Flag::where("comment_id", $fData->comment_id)->where("type", "report")->with('user')->latest()->get());
        if($flagTargetType == "post") {
            $decay_factor = exp(-$fData->post->reply_count/self::REPLY_FLAG_DECAY_FACTOR);
        }
        else {
            $decay_factor = 1.0;
        }
        $banReason .= "动态削减系数为 {$decay_factor}!\n";

        // Compute verdict. If a ring/sudo member has inputted a reason code
        // as noted by (5\.\d+) full match (5\.\d+(\.\d+)?), then the reason code will be matched 
        // and expanded and saved in the verdict.
        // If no verdict, then the default is 5.14
        $verdictCode = "5.14";
        $matches = array(); // temporary
        $isBridgeVerdict = false;

        foreach($fDatas as $fD) {
            $matches = array();
            if($fD->user->ring == 0) {
                $weight = round(self::SUDO_FLAG_WEIGHT_BASE * $decay_factor, 2);
                if(preg_match("/(5\.\d+)/siu", $fD->content, $matches)) {
                    if(isset($this->verdicts[$matches[0]])) {
                        $verdictCode = $matches[0];
                        // else silently drop
                    }
                }
            }
            elseif($fD->user->ring <= 2) {
                $weight = round(self::RING_FLAG_WEIGHT_BASE * $decay_factor, 2);
                if(preg_match("/(5\.\d+)/siu", $fD->content, $matches)) {
                    if(isset($this->verdicts[$matches[0]])) {
                        $verdictCode = $matches[0];
                        // else silently drop
                    }
                }
            }
            else {
                $weight = round(self::PUBLIC_FLAG_WEIGHT_BASE * $decay_factor, 2);
            }

            $user_repl = substr(sha1("-removed-unique-hash-" . $fD->user_id), 0, 8);
            $total += $weight;
            if($fD->user->id == 9999999) {
                // -removed-
            }
            else {
                if(!isset($matches[0])) {
                    $banReason .= "- [" . $fD->created_at . "] {$user_repl} 举报, 权重 = {$weight}, Σ {$total}\n";
                }
                else {
                    $banReason .= "- [" . $fD->created_at . "] 管理员 {$user_repl} 操作, 判定理由 " . $matches[0] . "\n";
                    $banReason .= "-- 操作附言: " . $fD->content . "\n";
                }
            }
        }

        $verdict = $verdictCode . " - " . $this->verdicts[$verdictCode] ?? "未知理由";

        if($total >= 99) {
            $banReason .= "- [" . Carbon::now() . "] 举报总权重超过阈值, 加入封禁名单\n";

            // Target user?
            $targetUser = ($flagTargetType == "post" ? $fData->post->user_id_enc : $fData->comment->user_id_enc);
            $targetUserId = intval(Crypt::decryptString($targetUser));

            // Compute the ban duration.
            $historicalBans = Ban::where("user_id", $targetUserId)->count();
            $latestBan = Ban::where("user_id", $targetUserId)->latest()->first();

            $start = Carbon::now();
            $banDays = 1; // defaults

            if($latestBan) {
                if(Carbon::create($latestBan->until)->isAfter(Carbon::now())) {
                    $start = Carbon::create($latestBan->until);
                    $banReason .= "- 现有封禁未解除至 " . $latestBan->until . "\n";
                }
            }

            if($historicalBans > 3) { $banDays = 2 * $historicalBans; }
            else { $banDays = 1; }

            $until = $start->addDays($banDays);

            // -removed-

            $banReason .= "- 查询累计封禁次数为 {$historicalBans}\n";
            $banReason .= "- 封禁时间 " . $banDays . " day(s) 至 {$until}\n";

            // Check if there is an existing ban data...
            if($flagTargetType == "post") $bData = Ban::where("post_id", $fData->post_id)->first();
            if($flagTargetType == "comment") $bData = Ban::where("comment_id", $fData->comment_id)->first();
            if(!$bData) $bData = new Ban;
            $bData->post_id = $fData->post_id;
            $bData->comment_id = $fData->comment_id;
            $bData->user_id = $targetUserId;
            $bData->verdict = $verdict;

            // Check if cross post - reason may be inaccurate
            if($isBridgeVerdict) {
                // - removed -
            }

            if($bData->reason) {
                $bData->reason .= "\n\n[" . now() . "] File opened by Hummingbird\n" . $banReason;
            }
            else $bData->reason = $banReason;
            $bData->until = $until;
            $bData->save();

            // Act on main post
            if($flagTargetType == "post") {
                $pID = $fData->post->id;
                $pData = Post::find($pID);
                $pData->hidden = 2;
                $pData->verdict = $verdict;
                $pData->save();

                // Tag all flags to be effective
                $fDatas = Flag::where("post_id", $pID)->update(["ban_id" => $bData->id]);
            }
            else {
                $cID = $fData->comment->id;
                $cData = Comment::find($cID);
                $cData->hidden = 2;
                $cData->verdict = $verdict;
                $cData->save();

                // Tag all flags to be effective
                $fDatas = Flag::where("comment_id", $cID)->update(["ban_id" => $bData->id]);
            }

            // Build a message to be sent...

            $mContent = "您发送的" . ($flagTargetType == "post" ? "树洞#{$pID}" : "评论#{$cID} (树洞#{$cData->post_id}下第{$cData->sequence}号评论)") . "因为违反社区管理条例而被删除.\n";
            $mContent .= "您的封禁时间至" . $until . "\n\n";

            $mContent .= "您有权利知晓封禁决策的过程, 并调取决策记录. 通常来讲, 您被封禁是因为足够多的人举报了您的发帖. 自动决策有助于有效治理社区, 但也可能存在误判的情况. 如果您认为您的发言不违反规定, 请联系我们申诉. (内测期间, 请填写反馈表申诉.)\n";

            $mContent .= "\n通常封禁决策是自动做出的, 您的案卷如下.\n";
            $mContent .= "--------------------------------------\n";
            $mContent .= $banReason;
            $mContent .= "--------------------------------------\n";

            $mData = new Message;
            $mData->user_id = $bData->user_id;
            $mData->title = "违规处理提示";
            $mData->content = $mContent;
            $mData->save();
        }
    }

    /**
     * Handle created "fold" type Flag
     */
    private function created_fold(Flag $fData): void {
        $fold = false;
        $flagTargetType = $fData->post_id !== null ? "post" : "comment";

        // Find the flagging user. If mod/admin ring, then fold directly
        if($fData->user->ring < 4) {
            $fold = true;
            $verdict_ring = $fData->content;
        }

        // For fold type flags, always handle because the threshold is low
        $fCount = ($flagTargetType == "post" ? 
                    Flag::where("post_id", $fData->post_id)->where("type", "fold")->count() :
                    Flag::where("comment_id", $fData->comment_id)->where("type", "fold")->count());

        if($fCount >= self::MIN_PUBLIC_FLAG_THRESHOLD) {
            $fold = true;
        }

        // Perform folding action
        if($fold) {
            // Get the reason... count majority
            $vQuery = DB::table("flags")->select(["content", DB::raw("COUNT(*) as count")])->where("type", "fold");
            $vQuery = ($flagTargetType == "post" ? $vQuery->where("post_id", $fData->post_id) : $vQuery->where("comment_id", $fData->comment_id));
            $vQuery = $vQuery->groupBy("content")->orderBy("count", "desc")->get();

            if(isset($vQuery[0])) {
                $verdict = $vQuery[0]->content;
            }
            else {
                $verdict = "未知";
            }

            if($flagTargetType == "post") {
                $pID = $fData->post_id;

                $pData = Post::find($fData->post_id);
                if(!$pData) return;

                if($pData->hidden === 1 && isset($verdict_ring)) $pData->verdict = $verdict_ring;
                else $pData->verdict = $verdict;

                $pData->hidden = 1;
                $pData->save();
            }

            if($flagTargetType == "comment") {
                $cID = $fData->comment_id;

                // For comment folding, mark hidden = 1
                $cData = Comment::find($fData->comment_id);
                if(!$cData) return;

                if($cData->hidden === 1 && isset($verdict_ring)) $cData->verdict = $verdict_ring;
                else $cData->verdict = $verdict;

                $cData->hidden = 1;
                $cData->save();
            }

            // Target user?
            $targetUser = ($flagTargetType == "post" ? $fData->post->user_id_enc : $fData->comment->user_id_enc);
            $targetUserId = intval(Crypt::decryptString($targetUser));

            $mContent = "您发送的" . ($flagTargetType == "post" ? "树洞#{$pID}" : "评论#{$cID} (树洞#{$cData->post_id}下第{$cData->sequence}号评论)") . "因为被多人标记 (多数理由为: {$verdict}) 而被折叠.\n";
            $mContent .= "折叠不意味着您违反了社区规定, 您不会因此受到任何处罚或记录. 相反, 折叠的目的是维护社区讨论的有序进行, 避免不希望看到可能含有争议的内容的同学被排除在社区之外.";

            $mData = new Message;
            $mData->user_id = $targetUserId;
            $mData->title = "折叠提示";
            $mData->content = $mContent;
            $mData->save();
        }
    }
}
