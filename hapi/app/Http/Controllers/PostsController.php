<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use App\Http\Resources\Post as PostResource;
use App\Http\Resources\Comment as CommentResource;

use App\Ban;
use App\User;

use App\Post;
use App\Comment;
use App\Favorite;

/**
 * @authenticated
 *
 * APIs for common interactions with hole.
 */
class PostsController extends ApiController
{
    // Flood control interval - in seconds
    protected const FLOOD_CONTROL_INTERVAL = 5;

    /**
     * @group Viewing
     *
     * List holes
     * Replaces `getlist`. Additionally, returns the `begchunk` (latest) and `endchunk` (oldest) of the chunk being retrieved, and `favorites_in_chunk` which includes followed posts within the chunk.
     *
     * @queryParam user_token  required
     * @queryParam limit                 Limit size per fetch. Defaults to 25. Maximum 50.
     * @urlParam   after                 Starting offset of hole ID. Defaults to latest if 0 or negative or not present. Example: 25
     *
     * @response   { 
     *    "error": null, "count": 25, 
     *    "data": [ { "pid": 1, "text": "Test", "type": "text", "timestamp": 1234567890, "likenum": 2, "reply": 25, "tag": null, "extra": null, "attention": true, "hidden": 1, "verdict": "折叠理由" }],
     *    "timestamp": 1599589514,
     *    "begchunk": 25, "endchunk": 1,
     *    "favorites_in_chunk": { "1": 1599665989 }
     * }
     * @response   { "error_msg": "Error Message", "error": "Unauthenticated" }
     */
    public function list(Request $request, Int $after = 0) {
        $pDatas = Post::limit(($limit = min(25, $request->query("limit", 25))));

        if(!$request->uData->entitlements["ViewingDeleted"]) $pDatas = $pDatas->where("hidden", "<", 2);

        if($after > 0) {
            $pDatas = $pDatas->where("id", "<", $after);
        }

        $pDatas = $pDatas->orderBy("created_at", "desc")->orderBy("id", "desc")->get();
        $count  = count($pDatas);

        if($count === 0) {
            $begchunk = null;
            $endchunk = null;

            $uFavDatas = [];
        }
        else {
            // Splice top hole (only 1 supported) iff $after > 0
            if($after > 0 && $pDatas[0]->created_at->timestamp > time()) {
                $pDatas = $pDatas->splice(1);
                $count = $count - 1;
            }

            $begchunk = $pDatas[0]->id;
            $endchunk = $pDatas[$count-1]->id;

            $begchunk_fav = (isset($pDatas[1]) && $begchunk < $pDatas[1]->id) ? $pDatas[1]->id : $begchunk;

            // Retrieve user fav data and merge into array.
            // We retrieve a maximum of $limit * 2 records counting down from first pid to prevent overloading with the past
            $uFavDatas = $request->uData->favorites()->where("post_id", "<=", $begchunk_fav)->orderBy("post_id", "desc")->limit($limit * 2)->get(["post_id", "created_at"]);

            $uFavDatas = $uFavDatas->mapWithKeys(function($fData) {
                return [$fData->post_id => $fData->created_at->timestamp];
            });

            // Merge into collection -
            foreach($pDatas as $pData) {
                if(isset($uFavDatas[$pData->id])) $pData->favorite = true;
                else $pData->favorite = false;
            }
        }

        return $this->successResponse([
            'count' => $count,
            'data' => PostResource::collection($pDatas),
            'timestamp' => time(),
            'begchunk' => $begchunk,
            'endchunk' => $endchunk,
            'favorites_in_chunk' => $uFavDatas
        ]);
    }

    /**
     * @group Viewing
     *
     * View single hole
     * Replaces `getone`, `getcomment`, following `getcomment` conventions to return `data` for comments data, and merging `getone` data into `post_data`.
     *
     * If post `hidden` is 1, it means that the content should be folded and not displayed by default. Entitlement `ViewingDeleted` additionally has access to `hidden` 2 comments.
     *
     * @queryParam user_token  required
     * @urlParam   id          required
     * @urlParam   after       After sequence number (0, 1, 2, ...). If **negative**, then the limit is interpreted as counting backwards from `-after` (i.e. -150 loads -150...-101). The chunk size is always 100 if after is specified.
     *
     * @response   { "error": null, 
     *     "data": [ { "cid": 1, "pid": 1, "text": "[Alice] Hello world", "timestamp": 1234567891, "tag": "Test tag", "name": "Alice", "hidden": 0 } ],
     *     "post_data": { "pid": 1, "text": "Test", "type": "text", "timestamp": 1234567890, "likenum": 2, "reply": 25, "tag": null, "extra": null, "attention": true }}
     * }
     * @response   { "error_msg": "Error Message", "error": "NotFound" }
     */
    public function view(Request $request, Int $id, Int $after = 0) {
        $pData = Post::find($id);
        if(!$pData) {
            return response()->json(["error_msg" => "找不到该树洞", "error" => "NotFound"]);
        }

        // Verify ring privileges. Ring 0, 1 can access all hidden posts
        if(!$request->uData->entitlements["ViewingDeleted"] && $pData->hidden > 1) {
            return response()->json(["error_msg" => "找不到该树洞", "error" => "NotFound"]);
        }

        // Note that "2" is true hidden, "1" is folded
        $cDatas = $pData->comments();

        // native?
        if($pData->secondary_id === null) {
            $cDatas = $cDatas->orderBy("sequence", "asc");
        }
        else {
            $cDatas = $cDatas->orderBy("created_at", "asc");
        }

        if(!$request->uData->entitlements["ViewingDeleted"]) {
            $cDatas = $cDatas->where("hidden", "<>", "2");
        }

        if($after > 0) {
            $cDatas = $cDatas->where("sequence", ">", $after)->limit(100);
        } elseif($after < 0) {
            $after = -$after;
            $cDatas = $cDatas->where("sequence", "<", $after)->limit(100);
        }

        $cDatas = $cDatas->get();
        $count  = count($cDatas);

        $isFavorite = Favorite::where("user_id", $request->uData->id)->where("post_id", $pData->id)->count() > 0;
        $pData->favorite = $isFavorite;

        return $this->successResponse([
            "count" => $count,
            "data" => CommentResource::collection($cDatas),
            "post_data" => new PostResource($pData)
        ]);

    }

    /**
     * @group Viewing
     *
     * Get followed holes
     * Replaces `getattention`. Returns data in the same format as `/holes/list`, while also allowing an optional `after?` argument (note that holes are ordered in BACKWARDS order, newest first). It also provides the total number of followed holes, which can be used for pagination.
     * @queryParam user_token  required
     * @queryParam limit                 Limit size per fetch. Defaults to 25. Maximum 50.
     * @urlParam   after                 Starting offset of hole ID. Defaults to latest if 0 or negative or not present. Example: 25
     *
     * @response   { 
     *    "error": null, "count": 25, "total": 72,
     *    "data": [ { "pid": 1, "text": "Test", "type": "text", "timestamp": 1234567890, "likenum": 2, "reply": 25, "tag": null, "extra": null, "attention": true }],
     *    "timestamp": 1599589514,
     *    "begchunk": 25, "endchunk": 1
     * }
     * @response   { "error_msg": "Not Logged In", "error": "Unauthenticated" }
     */
    public function getFavorite(Request $request, Int $after = 0) {
        $pDatas = $request->uData->favorite_holes()->limit(($limit = min(25, $request->query("limit", 25))));

        if(!$request->uData->entitlements["ViewingDeleted"]) {
            $pDatas = $pDatas->where("hidden", "<", 2);
        }

        if($after > 0) {
            $pDatas = $pDatas->where("posts.id", "<", $after);
        }

        $pDatas = $pDatas->latest()->get();
        $count  = count($pDatas);

        if($count === 0) {
            $begchunk = null;
            $endchunk = null;
        }
        else {
            $begchunk = $pDatas[0]->id;
            $endchunk = $pDatas[$count-1]->id;

            // Merge into collection -
            foreach($pDatas as $pData) {
                $pData->favorite = true;
            }
        }

        return $this->successResponse([
            'data' => PostResource::collection($pDatas),
            'timestamp' => time(),

            'count' => $count,
            'total' => $request->uData->favorites->count(),
            'begchunk' => $begchunk,
            'endchunk' => $endchunk
        ]);
    }

    /**
     * @group Viewing
     *
     * Search
     * Replaces `search`. Returns a collection of Posts based on search input. The result is similar to `/holes/list`.
     * 
     * The usual caveat with ngram 2 index is of course, single keys cannot be searched.
     *
     * The FULLTEXT index created for the posts table is: `FULLTEXT KEY content(content) WITH PARSER ngram`.
     *
     * **As MariaDB 10.5 is yet to support the ngram parser, currently the search is a very slow `%LIKE%` search. It will be changed next month when MariaDB updates.** Hopefully this will be done by the end of this month. See [MDEV-10267](https://jira.mariadb.org/browse/MDEV-10267)
     *
     * @response   {"error":null,"data":[{"pid":1,"hot":1599665989,"timestamp":1599660194,"reply":0,"likenum":1,"tag":null,"text":"Genesis","hidden":0,"url":"","extra":null, "attention": true}],"count":1,"begchunk":1,"endchunk":1}
     * @response   {"error":null,"data":[],"count":0,"begchunk":null,"endchunk":null}
     * @queryParam user_token  required
     * @queryParam keywords    required  Keyword(s) to search. If numeric, will display that hole first if it matches.
     * @urlParam   after                 Starting offset of hole ID. Defaults to latest if 0 or negative or not present. Example: 25
     */
    public function search(Request $request, $after = 0) {
        if($request->query("keywords") === null) {
            return $this->errorResponse("InsufficientParameters");
        }

        $pageSize = 50;

        // we have a ngram = 2 fulltext parser now; the query is
        // select * from posts where match content against ('文明' in boolean mode)
        //
        // the fulltext index has to be built with
        // create fulltext index ft_index on posts(content) with parser ngram;
        // create fulltext index ft_index on comments(content) with parser ngram;
        //
        // the stopword list has to be cleared and created with a varchar(30):
        // CREATE TABLE empty_stopwords(value VARCHAR(30)) ENGINE = INNODB;
        //
        // then:
        // (using root) set global innodb_ft_server_stopword_table = 'ng/empty_stopwords';
        //
        // now, this does not work with single chars because ngram is 2,
        // so if the keyword is length 1, we use LIKE syntax
        // otherwise, we use boolean mode
        $keywords = $request->query("keywords");

        if(mb_strlen($keywords) === 1) {
            // do a %like% search. only supports one keyword
            // do not search comments in this case, because
            $pDatas = Post::orderBy("id", "desc")->where(function($query) use ($keywords) {
                $query->where("posts.content", "LIKE", "%" . $keywords . "%");
                if(strval(intval($keywords)) == $keywords) {
                    $query->orWhere("posts.id", $keywords);
                }
            });
        }
        else {
            // use ngram boolean search.
            $pDatas = Post::orderBy("id", "desc")->where(function($query) use ($keywords, $after) {
                $query->whereHas('comments', function($query) use ($keywords, $after) {
                    $query->whereRaw('MATCH comments.content AGAINST (? IN BOOLEAN MODE)', [$keywords]);
                })->orWhere(function($query) use ($keywords, $after) {
                    $query->whereRaw('MATCH posts.content AGAINST (? IN BOOLEAN MODE)', [$keywords]);
                    // if(strval(intval($keywords)) == $keywords) {
                    //     $query->orWhere("posts.id", $keywords);
                    // }
                });
            });
        }

        if($after > 0) {
            $pDatas = $pDatas->where("posts.id", "<", $after);

            if(strval(intval($keywords)) == $keywords) {
                $pDatas = $pDatas->where("posts.id", "<>", $keywords);
            }
        }

        $pDatas = $pDatas->where("hidden", "0")->limit($pageSize)->get();

        // Merge itself if page is 1
        if($after == 0 && strval(intval($keywords)) == $keywords) {
            $pDatas2 = Post::where("posts.id", $keywords)->where("hidden", "<", "2")->get();
            $pDatas = $pDatas2->concat($pDatas);
        }

        $count = count($pDatas);

        if($count === 0) {
            $begchunk = null;
            $endchunk = null;
        }
        else {
            if(isset($pDatas2) && count($pDatas2) && count($pDatas) > 1) $begchunk = $pDatas[1]->id;
            else $begchunk = $pDatas[0]->id;
            $endchunk = $pDatas[$count-1]->id;

            // Retrieve user fav data and merge into array.
            $uFavDatas = $request->uData->favorites()->where("post_id", "<=", $begchunk)->where("post_id", ">=", $endchunk)->get(["post_id", "created_at"]);

            $uFavDatas = $uFavDatas->mapWithKeys(function($fData) {
                return [$fData->post_id => $fData->created_at->timestamp];
            });

            // Merge into collection -
            foreach($pDatas as $pData) {
                if(isset($uFavDatas[$pData->id])) $pData->favorite = true;
                else $pData->favorite = false;
            }
        }
        

        return $this->successResponse([
            "data" => PostResource::collection($pDatas),

            "count" => $count,
            // "begchunk" => $begchunk,
            "endchunk" => $endchunk
        ]);
    }


    /**
     * @group Posting
     *
     * Post a new hole
     * Replaces `dopost`. Returns as the data the newly inserted post ID.
     * Note that if content is deemed to be duplicate or flood, Error 429 `FloodControl` will trigger. If user is banned from posting, Error 403 `Banned` will trigger; `message` will show the specific ban reason and expiration time.
     *
     * The maximum size permitted for `image` is 1 MiB after base64-decoding.
     *
     * Possible error codes:
     * - `InsufficientParameters`
     * - `NotFound`
     * - `Banned`
     * - `UnsupportedType`
     * - `FloodControl` - "您发帖速度过快, 请等待后再发送!"
     * - 500: `InternalAtomicTransactionError`
     *
     * For image upload:
     * - `IncorrectBinaryEncoding`
     * - `IncorrectBase64EncodingLegacy`
     * - `ExceededMaximumFileSize1MB`
     * - `IncorrectImageFormat`
     *
     * @queryParam user_token  required
     * @bodyParam  text        string  required    Post text. Can be blank but must be present.
     * @bodyParam  type        string  required    text, image
     * @bodyParam  data_type   string              If given: jpeg, png, gif ... (PHP supported image formats).
     * @bodyParam  data        string              If `data_type` exists, raw data; otherwise a base64-encoded image.
     * @response { "error": null, "data": "1820322" }
     * @response { "error_msg": "您被封禁至 until, 因此无法发帖", "error": "Banned", "message": "Banned due to reason XYZ until 2038-01-01 11:22:33Z" }
     */
    public function post(Request $request) {
        if(!$request->has(["text", "type"])) {
            return $this->errorResponse("InsufficientParameters");
        }

        // Perform flood control. Planned set of verifications:
        // 1) no duplicate content in this tree within 600 sec - proceed
        //    Carbon::now()->subMinutes(10);
        // 2) duplicate content -
        //    1) not same author - proceed
        //    2) same author - drop due to flood control
        //
        // For now, only impose a 30-second (self::FLOOD_CONTROL_INTERVAL) flood control duration

        // Perform flood control - 30 seconds minimum
        if(Cache::get('_hapi_last_post_' . $request->uData->id, 0) + self::FLOOD_CONTROL_INTERVAL > time()) {
            return $this->errorResponse("FloodControl");
        }

        // Ban verification
        if(is_array($banStatus = $this->retrieveBanStatus($request->uData->id))) {
            return $this->errorResponse("Banned", "您被封禁至" . $banStatus["until"] . ", 因此无法发帖", ["message" => $banStatus["reason"], "until" => $banStatus["until"]]);
        }

        // Is bridge?
        $isBridge = $request->has("bridge") && $request->boolean("bridge") && $request->uData->entitlements["CanPostBridge"];

        $pData = new Post;

        $type = $request->input("type", "text");
        switch($type) {
            case "text":
                if(is_null($request->input("text"))) {
                    return $this->errorResponse("CannotMakeEmptyPost");
                }

                $pData->type = "text";
                $pData->content = $request->input("text");
                $pData->extra = "";
            break;

            case "image":
                $up_result = $this->_handleImageUpload($request);
                if(!$up_result[0]) {
                    return $up_result[1];
                }

                $pData->type = "image";
                $pData->content = $request->input("text") ?? '';
                $pData->extra = $up_result[1];
            break;

            default:
                return $this->errorResponse("UnsupportedType");
        }

        $pData->user_id_enc = Crypt::encryptString(strval($request->uData->id));
        $pData->user_map_enc = Crypt::encryptString(serialize([
            $request->uData->id => "洞主"
        ]));
        $pData->ip = $request->ip();

        if($isBridge) {
            $pData->secondary_id = -999; // signal to model creation event
        }

        $pData->save();

        // Write to flood control cache
        // and store for 60 seconds (longer than the cache flood control duration)
        Cache::put('_hapi_last_post_' . $request->uData->id, time(), 60);

        // Automatically follow this post if not already followed
        if(!Favorite::where([["user_id", $request->uData->id], ["post_id", $pData->id]])->count()) {
            $fData = new Favorite;
            $fData->user_id = $request->uData->id;
            $fData->post_id = $pData->id;
            $fData->save();
        }

        return $this->successResponse([
            "data" => $pData->id
        ]);
    }

    /**
     * @group Posting
     *
     * Reply to a hole
     * Replaces `docomment`. Returns as the data the newly inserted comment ID.
     * Note that if content is deemed to be duplicate or flood, Error 429 `FloodControl` will trigger. If user is banned from posting, Error 403 `Banned` will trigger; `message` will show the raw ban reason and `until` the timestamped expiry time.
     *
     * The operations on this method are pessimistic locked.
     *
     * Possible error codes:
     * - `InsufficientParameters`
     * - `NotFound`
     * - `Banned`
     * - `UnsupportedType`
     * - `FloodControl`
     * - 500: `InternalAtomicTransactionError`
     *
     * For image upload:
     * - `IncorrectBinaryEncoding`
     * - `IncorrectBase64EncodingLegacy`
     * - `ExceededMaximumFileSize1MB`
     * - `IncorrectImageFormat`
     * - `UnsupportedUploadImageFormat`
     *
     * @queryParam user_token  required
     * @urlParam   id          required            Target PID
     * @bodyParam  text        string  required    
     * @bodyParam  type        string              text or image
     * @bodyParam  data_type   string              If given: jpeg, png, gif ... (PHP supported image formats).
     * @bodyParam  data        string              If `data_type` exists, raw data; otherwise a base64-encoded image.
     * @response { "error": null, "data": "1820322" }
     * @response { "error_msg": "参数错误", "error": "InsufficientParameters" }
     * @response { "error_msg": "您被封禁至 until, 因此无法发帖", "error": "Banned", "message": "Banned due to reason XYZ until 2038-01-01 11:22:33Z" }
     * @response 500 { "error_msg": "服务器内部错误", "error": "InternalAtomicTransactionError" }
     */
    public function reply(Request $request, $id) {
        if(!$request->has(["text"])) {
            return $this->errorResponse("InsufficientParameters");
        }

        $pData = Post::find($id);
        if(!$pData) {
            return $this->errorResponse("NotFound");
        }

        // Verify ring privileges. Ring 0, 1 can access all hidden posts
        if(!$request->uData->entitlements["ViewingDeleted"] && $pData->hidden > 1) {
            return $this->errorResponse("NotFound");
        }

        // For now, only impose a 30-second (self::FLOOD_CONTROL_INTERVAL) flood control duration

        // Perform flood control - 30 seconds minimum
        if(Cache::get('_hapi_last_comment_' . $request->uData->id, 0) + self::FLOOD_CONTROL_INTERVAL > time()) {
            return $this->errorResponse("FloodControl");
        }

        // Ban verification
        if(is_array($banStatus = $this->retrieveBanStatus($request->uData->id))) {
            return $this->errorResponse("Banned", "您被封禁至" . $banStatus["until"] . ", 因此无法发帖", ["message" => $banStatus["reason"], "until" => $banStatus["until"]]);
        }

        //--------------------------------------------
        // Initiate DB Transaction
        // Using pessimistic locking.
        DB::beginTransaction();

        try {
            $pData = Post::where("id", $id)->lockForUpdate()->first();

            // Construct the Comment model
            $cData = new Comment;
            $cData->post_id = $pData->id;
            $cData->extra = "";

            // The author map should NOT be updated if this is a bridge post
            // but we do not use it anyway if it this is a secondary post; it is
            // simply ignored (for sake of consistency though, there may be issues)
            // but for now we just manually overwrite

            // Mark as bridge beforehand so names can be re-assigned
            if(!is_null($pData->secondary_id)) {
                // For hinting to model event
                $cData->secondary_id = -999;
            
                // BRIDGE
            }
            else {
                // NON-BRIDGE
            }

            // Retrieve the Post author map information
            try {
                $userMap = unserialize(Crypt::decryptString($pData->user_map_enc));
            } catch (DecryptException $e) {
                Log::warning("user_map_enc decryption failed in p#{$pData->id}. rebuilding");
                // have to rebuild the user map. this needs to be handled higher up
                $userMap = $pData->rebuildUserMap(!is_null($pData->secondary_id));
            }

            // Append to usermap.
            if(!isset($userMap[$request->uData->id])) {
                $userMap[$request->uData->id] = $pData->getNextName(count($userMap)-1, !is_null($pData->secondary_id));
                // Save usermap. TX 1
                $pData->user_map_enc = Crypt::encryptString(serialize($userMap));
                $pData->save();
            }

            $type = $request->input("type", "text");
            switch($type) {
                case "text":
                    if(is_null($request->input("text"))) {
                        return $this->errorResponse("CannotMakeEmptyPost");
                    }

                    $cData->type = "text";
                    $cData->content = $request->input("text");
                break;

                case "image":

                    $up_result = $this->_handleImageUpload($request);
                    if(!$up_result[0]) {
                        return $up_result[1];
                    }

                    $cData->type = "image";
                    $cData->content = $request->input("text") ?? '';
                    $cData->extra = $up_result[1];

                break;

                default:
                    return $this->errorResponse("UnsupportedType");
            }

            $cData->user_id = $request->uData->id;
            $cData->ip = $request->ip();

            // Save comment. TX 2
            $cData->save();

            // Automatically follow this post if not already followed
            if(!Favorite::where([["user_id", $request->uData->id], ["post_id", $pData->id]])->count()) {
                $fData = new Favorite;
                $fData->user_id = $request->uData->id;
                $fData->post_id = $pData->id;
                $fData->save();
            }

            DB::commit();

        } catch(\Exception $e) {
            DB::rollBack();
            Log::warning("Failed to insert in atomic operation in PostsController@reply. Exception short: " . strval($e));

            return $this->errorResponse("InternalAtomicTransactionError", "", [], 500);
        }

        // Write to flood control cache
        // and store for 60 seconds (longer than the cache flood control duration)
        Cache::put('_hapi_last_comment_' . $request->uData->id, time(), 60);

        return $this->successResponse([
            "data" => $cData->id
        ]);
    }

    /**
     * @group Moderation
     * Tag Post
     * This will add a tag to the post. Requires entitlement `Tagging`.
     *
     * @queryParam user_token required
     * @urlParam id required
     * @bodyParam content required
     */
    public function tagPost(Request $request, Int $id) {
        if(!$request->uData->entitlements["Tagging"]) {
            return $this->errorResponse("NoPermission");
        }

        if(!$request->has("content")) return $this->errorResponse("InsufficientParameters");

        // Verifications can be relaxed as Tagging is a high privilege

        $pData = Post::find($id);
        if(!$pData) {
            return $this->errorResponse("NotFound");
        }

        $pData->tag = $request->input("content");
        if(strlen($pData->tag) === 0) $pData->tag = null;
        $pData->save();

        return $this->successResponse();
    } 

    /**
     * @group Moderation
     * Tag Comment
     * This will add a tag to the comment. Requires entitlement `Tagging`.
     *
     * @queryParam user_token required
     * @urlParam id required
     * @bodyParam content required
     */
    public function tagComment(Request $request, Int $id) {
        if(!$request->uData->entitlements["Tagging"]) {
            return $this->errorResponse("NoPermission");
        }

        if(!$request->has("content")) return $this->errorResponse("InsufficientParameters");

        // Verifications can be relaxed as Tagging is a high privilege

        $cData = Comment::find($id);
        if(!$cData) {
            return $this->errorResponse("NotFound"); // do not leak state
        }

        $cData->tag = $request->input("content");
        if(strlen($cData->tag) === 0) $cData->tag = null;
        $cData->save();

        return $this->successResponse();
    } 

    /**
     * @group Moderation
     *
     * Edit Post
     * Edits post contents. If only `content` is edited, the entitlement `EditingText` is required.
     * Additionally if `type` and `extra` are modified, the entitlement `EditingTypeExtra` is required.
     *
     * Not all arguments need to be present.
     *
     * @queryParam user_token required
     * @urlParam id required
     * @bodyParam text string
     * @bodyParam type string text/image/any..
     * @bodyParam extra string any raw extra data
     */
    public function editPost(Request $request, Int $id) {
        if(!$request->uData->entitlements["EditingText"]) { // at the very least need to edit text
            return $this->errorResponse("NoPermission");
        }

        // Verifications can be relaxed as Edit is a high privilege

        $pData = Post::find($id);
        if(!$pData) {
            return $this->errorResponse("NotFound");
        }

        if($request->input("text") !== null) $pData->content = $request->input("text");
        if($request->uData->entitlements["EditingTypeExtra"]) {
            if($request->input("type") !== null) $pData->type = $request->input("type");
            if($request->input("extra") !== null) $pData->extra = $request->input("extra");
        }
        $pData->save();

        return $this->successResponse();
    } 

    /**
     * @group Moderation
     *
     * Edit Comment
     * Edits comment contents. If only `content` is edited, the entitlement `EditingText` is required.
     * Additionally if `type` and `extra` are modified, the entitlement `EditingTypeExtra` is required.
     *
     * Not all arguments need to be present.
     *
     * @queryParam user_token required
     * @urlParam id required
     * @bodyParam text string
     * @bodyParam type string text/image/any..
     * @bodyParam extra string any raw extra data
     */
    public function editComment(Request $request, Int $id) {
        if(!$request->uData->entitlements["EditingText"]) { // at the very least need to edit text
            return $this->errorResponse("NoPermission");
        }

        // Verifications can be relaxed as Edit is a high privilege

        $cData = Comment::find($id);
        if(!$cData) {
            return $this->errorResponse("NotFound"); // do not leak state
        }

        if($request->input("text") !== null) $cData->content = $request->input("text");
        if($request->uData->entitlements["EditingTypeExtra"]) {
            if($request->input("type") !== null) $cData->type = $request->input("type");
            if($request->input("extra") !== null) $cData->extra = $request->input("extra");
        }
        $cData->save();

        return $this->successResponse();
    } 

    // ------------------------- INTERNAL UTILITY METHODS ---------------------------- //

    /**
     * Internal method: Verify and enforce ban.
     * This will calculate the latest ban available for the user and determine
     * if it applies to this transaction.
     */
    private function retrieveBanStatus($uID) {
        $bData = Ban::where("user_id", $uID)->orderBy("until", "desc")->first();

        if($bData && Carbon::create($bData->until)->isAfter(Carbon::now())) {
            if(strpos($bData->reason, "Hummingbird") !== false) {
                $reason = "因为举报数超出阈值而被系统自动删除";
            }
            else {
                $reason = $bData->reason;
            }

            $reason .= ($bData->post_id !== null ? " (PID#" . $bData->post_id . ")" : " (CID#" . $bData->comment_id . ")");

            return ["reason" => $reason, "until" => $bData->until];
        }

        return false;
    }

    /**
     * Create unique_id based on post_id, user_id
     */
    private function _createUniqueId(Int $post_id, Int $user_id): String {
        return "pku" . sha1("-removed-" . $post_id . "_2020_" . $user_id);
    }

    /**
     * Handle file uploads... along with compression, quality check and others
     *
     * @param  Illuminate\Http\Request $request
     * @return array [0]=status, [1]=response|file_extra
     */
    private function _handleImageUpload(Request $request) {
        // compatibility API which accepts base64 data instead.
        if(!$request->has("data")) {
            return [false, $this->errorResponse("InsufficientParameters")];
        }

        if($request->has("data_type")) {
            if(!$request->file("data")->isValid()) {
                return [false, $this->errorResponse("IncorrectBinaryEncoding")];
            }

            $data = $request->file("data")->get();
        }
        else {
            $data = base64_decode($request->input("data"), true);
            if($data === false) {
                return [false, $this->errorResponse("IncorrectBase64EncodingLegacy")];
            }
        }

        // 1MB = 1,048,576 bytes
        if(strlen($data) > 1050000) {
            return [false, $this->errorResponse("ExceededMaximumFileSize1MB")];
        }

        $size = @getimagesizefromstring($data);
        if(!is_array($size) || !isset($size[0], $size[1], $size[2]) || $size[0] < 1) {
            return [false, $this->errorResponse("IncorrectImageFormat")];
        }

        $width = $size[0];
        $height = $size[1];
        $extension = image_type_to_extension($size[2]);

        // Validate data_type
        $data_type = $request->input("data_type");
        if(strpos($data_type, "@") !== false) {
            $data_type_2 = explode("@", $data_type);
            $data_type = $data_type_2[0];
            $quality = intval($data_type_2[1]);
        }

        if($request->has("data_type") && $extension != "." . $data_type) {
            return [false, $this->errorResponse("IncorrectDataType", "", ["expected" => $extension])];
        }

        if($extension != ".png" && $extension != ".jpeg") {
            return [false, $this->errorResponse("UnsupportedUploadImageFormat")];
        }

        $name = sha1(mt_rand(1, 50000) . time()) . "_" . $width . "x" . $height . $extension;

        // Put in storage
        Storage::disk("azure")->put($name, $data, "public");

        return [true, $name, $data];
    }
}
