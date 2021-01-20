<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Crypt;

class Ban extends JsonResource
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

        return [
            "id" => $this->id,
            "post_id" => $this->post_id,
            "comment_id" => $this->comment_id,

            "reason" => $this->reason,
            "created_at" => $this->created_at->timestamp,
            "until" => $this->until,

            "user_representation" => Crypt::encryptString($this->user_id),
            "user_id" => ($request->uData->entitlements["Sudoers"] ? $this->user_id : null)
        ];
    }
}
