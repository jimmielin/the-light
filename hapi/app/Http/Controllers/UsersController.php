<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Ban;
use App\Comment;
use App\Message;
use App\Invite;

use Carbon\Carbon;

/**
 * @group User and Permission
 */
class UsersController extends ApiController
{
    /**
     * Get Info
     *
     * Retrieve a list of flags which indicate user permission to show and hide UX.
     * Also returns the application version.
     *
     * Entitlements that the user does not have access to are *hidden*.
     *
     * @queryParam user_token required
     * @response {"entitlements":{"EditingText":true,"EditingTypeExtra":true,"Tagging":true,"ViewingDeleted":true,"ViewingFlags":true,"UndoBan":true,"Sudoers":true},"ring":0,"ban_until":null,"latest_message":null,"alerts":[{"type":"info","is_html":true,"message":"Test"}],"version":"20200913","error":null}
     */
    public function info(Request $request) {
        Auth::loginUsingId($request->uData->id, true);

        $filtered = Arr::where($request->uData->entitlements, function($v) { return $v; });
        $bData = Ban::where("user_id", $request->uData->id)->orderBy("until", "desc")->first();
        if($bData) {
            if($bData->comment_id != null) {
                $bcData = Comment::find($bData->comment_id);
                if($bcData) {
                    $bcBlurb = "树洞#" . $bcData->post_id . "第[" . $bcData->sequence . "]个评论";
                }
                else {
                    $bcBlurb = "评论#" . $bData->comment_id;
                }
            }
        }

        $mData = Message::where("user_id", $request->uData->id)->latest()->first();

        $alert1 = [
            "type" => "info",
            "is_html" => true,
            "message" => "Code is immortal. May the Force be with you.",
        ];

        $alerts = [$alert1];

        // -- removed --

        if($bData) {
            if(Carbon::create($bData->until)->isAfter(Carbon::now())) {
                $alerts[] = [
                    "type" => "warning",
                    "is_html" => false,
                    "message" => "您因为" . ($bData->post_id !== null ? "树洞#" . $bData->post_id : $bcBlurb) . "的举报数超过阈值或被判定违反树洞规则而被封禁至" . $bData->until
                ];
            }
        }

        if(!count($filtered)) $filtered = (object) null;
        return $this->successResponse([
            "entitlements" => $filtered,
            "ring" => $request->uData->ring,
            "remember_token" => $request->uData->remember_token,

            "ban_until" => ($bData ? Carbon::create($bData->until)->timestamp : null),
            "latest_message" => ($mData ? $mData->created_at->timestamp : null),

            "alerts" => $alerts,

            "version" => config("app.version")
        ]);
    }

    /**
     * @group Invites
     * Retrieve invitation code
     *
     * Retrieve invitation code for private / public beta.
     * If there is no invite code for this user it also generates as side-effect (ugh)
     *
     * @queryParam user_token required
     * @response {"code":"A95027CF8","remaining":2,"error":null}
     */
    public function invites(Request $request) {

        if(!$request->uData->invite) {
            $code = strtoupper(bin2hex(random_bytes(5)));
            $remaining = 5;
            $request->uData->invite()->create([
                "code" => $code,
                "remaining" => 5 // always can be increased...
            ]);
        }
        else {
            $code = $request->uData->invite->code;
            $remaining = $request->uData->invite->remaining;
            if($remaining < 0) $remaining = 0;
        }

        return $this->successResponse(["code" => $code, "remaining" => $remaining]);
    }
}
