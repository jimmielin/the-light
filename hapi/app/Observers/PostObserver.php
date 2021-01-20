<?php

namespace App\Observers;

use App\Post;

use Illuminate\Support\Facades\Storage;

class PostObserver
{
    public $fold_verdicts = ["政治相关", "性相关", "引战", "偏离话题", "未经证实的传闻", "令人不适", "折叠", "NSFW"]; // "未知"

    /**
     * Handle the post "creating" event.
     *
     * Updating only triggers if using model, not if using DB!!
     * @param \App\Post     $pData
     * @return void
     */
    public function creating(Post $pData) {
        // Match keywords....
        foreach($this->fold_verdicts as $v) {
            if(!is_null($pData->secondary_id)) {
                $regex = '/#' . $v . '/siu';
            }
            else {
                $regex = '/^#' . $v . '(\\s|$)/siu';
            }

            if(preg_match($regex, $pData->content)) {
                // do fold...
                $pData->hidden = 1;

                if($v == "折叠") {
                    $pData->verdict = "令人不适";
                }
                elseif($v == "NSFW") {
                    $pData->verdict = "性相关";
                }
                else {
                    $pData->verdict = $v;
                }

                break;
            }
        }
    }

    /**
     * Handle the post "updating" event.
     *
     * @param  \App\Post  $pData
     * @return void
     */
    public function updating(Post $pData)
    {
        // Update post image path to new secret if deleted
        if($pData->type == "image" && $pData->hidden == 2) {
            $newsecret = strtolower(bin2hex(random_bytes(8)));

            if(Storage::disk('azure')->exists($pData->extra)) {
                Storage::disk("azure")->move($pData->extra, $newsecret . "_rm_" . $pData->extra);
            }

            if(Storage::disk('sftp')->exists($pData->extra)) {
                Storage::disk("sftp")->delete($pData->extra);
                // azure blob will sync it through. just delete from GIA
            }

            $pData->extra = $newsecret . "_rm_" . $pData->extra;
        }
    }
}
