<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class Post extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request) {
        // return parent::toArray($request);
        // compute image url: allow 60 seconds before pointing to new
        $created_at = $this->created_at->timestamp;

        /*
         * server config -removed-
         */

        if($this->type == "image") {
            //if(time() - $created_at > 15) {
            $extra = "https://cdn-removed-/" . $this->extra;
            //}
            //else {
            //    $extra = Storage::disk('azure')->url($this->extra);
            //}
        }
        else {
            $extra = $this->extra;
        }

        $out = [
            "pid" => $this->id,
            "hot" => $this->updated_at->timestamp,
            "timestamp" => $created_at,
            "reply" => ($request->uData->entitlements["ViewingDeleted"] ? $this->true_reply_count : $this->reply_count),
            "likenum" => $this->favorite_count,
            "tag" => $this->tag,
            "text" => $this->content,
            "type" => $this->type,
            
            "hidden" => $this->hidden,
            "verdict" => $this->verdict,

            "extra" => $extra,
            // "bridge" => ($this->secondary_id != null)
        ];

        if(isset($this->favorite)) $out["attention"] = $this->favorite;

        return $out;
    }
}
