<?php

namespace App\Observers;

use App\Comment;
use App\Post;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CommentObserver
{
    public $fold_verdicts = ["政治相关", "性相关", "引战", "偏离话题", "未经证实的传闻", "令人不适", "折叠", "NSFW"]; // "未知"

    /**
     * Update related post counts.
     */
    private function updatePostCommentCount(Comment $comment) {
        $pData = $comment->post;
        $pData->reply_count = Comment::where("hidden", "<=", 1)->where("post_id", $pData->id)->count();
        $pData->true_reply_count = Comment::where("post_id", $pData->id)->count();
        $pData->updated_at = now(); // force update for comment $
        $pData->save();
    }

    /**
     * Handle the comment "creating" event.
     *
     * WARNING/FIXME: Note only the "creating" event is qualified to handle the `user_id_enc`
     * attribute. If you update the model otherwise, you cannot change the user fields or
     * the post 
     * @param \App\Comment   $cData
     * @return void
     */
    public function creating(Comment $cData)
    {
        // Reconstruct user hashmap
        $pData = $cData->post;
        $uID = $cData->user_id; // this virtual field MUST be present

        // Remove the temporary
        unset($cData->user_id);

        // Check if bridge to use alternate name set
        $isBridge = isset($cData->secondary_id) && $cData->secondary_id !== null;

        // Check if creating user handling event is silenced - case when user_id saved is 9999999
        // for bridge.
        // If you are on bridge, everything you will handle on your own in regards to the userhashmap
        $cData->user_id_enc = Crypt::encryptString(strval($uID));
        if($uID != 9999999) {
            // Retrieve the Post author map information
            if(!isset($pData->user_map_enc)) {
                Log::info("user_map_enc does not exist in p#{$pData->id}. rebuilding");
                $userMap = $pData->rebuildUserMap($isBridge);
            }
            else {
                try {
                    $userMap = unserialize(Crypt::decryptString($pData->user_map_enc));
                } catch (DecryptException $e) {
                    Log::warning("user_map_enc decryption failed in p#{$pData->id}. rebuilding");
                    // have to rebuild the user map. this needs to be handled higher up
                    $userMap = $pData->rebuildUserMap($isBridge);
                }
            }

            // Append to usermap.
            if(!isset($userMap[$uID])) {
                $userMap[$uID] = $pData->getNextName(count($userMap)-1, $isBridge);
                // Save usermap. TX 1
                $pData->user_map_enc = Crypt::encryptString(serialize($userMap));
                $pData->save();
            }

            // Write the name
            $cData->user_nickname = $userMap[$uID];
        }

        // Count sequence
        $cData->sequence = $pData->true_reply_count + 1;

        // For self-fold
        // Match keywords....
        foreach($this->fold_verdicts as $v) {
            if(!is_null($pData->secondary_id)) {
                $regex = '/#' . $v . '/siu';
            }
            else {
                $regex = '/^#' . $v . '(\\s|$)/siu';
            }

            if(preg_match($regex, $cData->content)) {
                // do fold...
                $cData->hidden = 1;

                if($v == "折叠") {
                    $cData->verdict = "令人不适";
                }
                elseif($v == "NSFW") {
                    $cData->verdict = "性相关";
                }
                else {
                    $cData->verdict = $v;
                }

                break;
            }
        }

        // Do not save, this is in-event
    }

    /**
     * Handle the comment "created" event.
     *
     * @param  \App\Comment  $comment
     * @return void
     */
    public function created(Comment $comment)
    {
        $this->updatePostCommentCount($comment);
    }

    /**
     * Handle the comment "updated" event.
     *
     * @param  \App\Comment  $comment
     * @return void
     */
    public function updated(Comment $comment)
    {
        $this->updatePostCommentCount($comment);
    }

    /**
     * Handle the comment "updating" event.
     * @param \App\Comment $comment
     * @return void
     */
    public function updating(Comment $comment) {
        // Update comment image path to new secret if deleted
        if($comment->type == "image" && $comment->hidden == 2) {
            $newsecret = strtolower(bin2hex(random_bytes(8)));

            if(Storage::disk('azure')->exists($comment->extra)) {
                Storage::disk("azure")->move($comment->extra, $newsecret . "_rm_" . $comment->extra);
            }

            if(Storage::disk('sftp')->exists($comment->extra)) {
                Storage::disk("sftp")->delete($comment->extra);
                // azure blob will sync it through. just delete from GIA
            }

            $comment->extra = $newsecret . "_rm_" . $comment->extra;
        }
    }

    /**
     * Handle the comment "deleted" event.
     *
     * @param  \App\Comment  $comment
     * @return void
     */
    public function deleted(Comment $comment)
    {
        $this->updatePostCommentCount($comment);
    }
}
