<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\User;
use App\Favorite;
use App\Post;

use App\Http\Resources\Post as PostResource;

class FavoritesController extends ApiController
{

    /**
     * @group Attentions
     *
     * Add/Remove Attention
     * Follows/unfollows a given post. As a `PUT` request, if the action already has been taken, it will be 
     * silently dropped.
     *
     * @queryParam user_token  required
     * @queryParam switch      required            1 = Add attention; 0 = Remove attention.
     * @urlParam   id          required            Target PID
     * @response { "success": true, "data": { "pid": 1, "text": "Test", "type": "text", "timestamp": 1234567890, "likenum": 2, "reply": 25, "tag": null, "extra": null, "attention": true } }
     * @response { "error_msg": "找不到该树洞", "error": "NotFound" }
     */
    public function doFavorite(Request $request, $id) {
        if($request->query("switch") === null) {
            return $this->errorResponse("InsufficientParameters");
        }

        $pData = Post::find($id);
        if(!$pData) {
            return $this->errorResponse("NotFound");
        }

        // Verify ring privileges. Ring 0, 1 can access all hidden posts
        if($request->uData->ring > 1 && $pData->hidden > 1) {
            return $this->errorResponse("NotFound");
        }

        $switch = intval($request->query("switch")) === 1;
        if($switch) {
            // Verify favorite relationship - if it exists, do not refresh it
            $fData = Favorite::where("user_id", $request->uData->id)->where("post_id", $pData->id)->count();

            if($fData > 0) { 
                $pData->favorite = true;
                return $this->successResponse(["data" => new PostResource($pData)]);
            }

            $fData = new Favorite;
            $fData->user_id = $request->uData->id;
            $fData->post_id = $pData->id;
            $fData->save();
        } else {
            $fData = Favorite::where("user_id", $request->uData->id)->where("post_id", $pData->id)->first();
            if($fData) $fData->delete();
        }

        // Refresh data
        $pData = Post::find($id);
        $pData->favorite = (boolean) $switch;

        return $this->successResponse(["data" => new PostResource($pData)]);
    }

}
