<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class Comment extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // return parent::toArray($request);

        // -removed-

        // compute image url: allow 60 seconds before pointing to new
        $created_at = $this->created_at->timestamp;

        if($this->type == "image") {
            //if(time() - $created_at > 500) {
            $extra = "https://cdn/-removed-/" . $this->extra;
            //}
            //else {
            //    $extra = Storage::disk('azure')->url($this->extra);
            //}
        }
        else {
            $extra = $this->extra;
        }

        return [
            "cid" => $this->id,
            "pid" => $this->post_id,
            "tag" => $this->tag,
            "timestamp" => $created_at, //-removed-
            "text" => $this->content,
            "type" => $this->type,
            
            "hidden" => $this->hidden,
            "verdict" => $this->verdict,
            
            // additional type metadata
            "extra" => $extra,
            // "bridge" => ($this->secondary_id != null),

            "sequence" => $this->sequence,

            "name" => $this->user_nickname,
        ];
    }
}
