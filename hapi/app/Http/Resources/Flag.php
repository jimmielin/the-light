<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Crypt;

class Flag extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            "id" => $this->id,
            "post_id" => $this->post_id,
            "comment_id" => $this->comment_id,
            "type" => $this->type,

            "content" => $this->content,
            "user_representation" => Crypt::encryptString($this->user_id),
            "created_at" => $this->created_at->timestamp
        ];
    }
}
