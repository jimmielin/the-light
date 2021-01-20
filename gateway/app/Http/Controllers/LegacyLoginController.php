<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

use App\User;

class LegacyLoginController extends Controller
{
    public function login(Request $request) {
        if(!$request->has(["username", "password"]) && !$request->has(["email", "password"])) {
            return response()->json(["error_msg" => "参数错误", "error" => "InsufficientParameters"]);
        }

        if($request->has("username")) {
            $uData = User::where("name", $request->input("username"))->first();
        }
        else {
            $uData = User::where("email", $request->input("email"))->first();

            // Legacy support: Also match name if available
            if(!$uData) {
                $uData = User::where("name", $request->input("email"))->first();
            }
        }

        if(!$uData || $uData->email_verified_at === null) {
            return response()->json(["error_msg" => "用户名/密码错误", "error" => "NoMatch"]);
        }

        if(Hash::check($request->input("password"), $uData->password)) {
            // also update request information
            $uData->last_seen = time();
            $uData->last_seen_ip = $request->ip();
            $uData->save();

            return response()->json(["error" => null, "user_token" => $uData->user_token, "ring" => $uData->ring, "updated_at" => $uData->updated_at]);
        }
        else {
            return response()->json(["error_msg" => "用户名/密码错误", "error" => "NoMatch"]);
        }
    }

    public function info(Request $request) {
        if(!$request->query("user_token")) {
            return response()->json(["error_msg" => "参数错误", "error" => "MissingUserToken"]);
        }

        $uData = User::where("user_token", ($user_token = $request->query("user_token", "none")))->whereNotNull("user_verified_at")->first();

        if(!$uData) {
            return response()->json(["error_msg" => "登录信息不正确", "error" => "Unauthenticated"]);
        }

        // Update field if user table data is stale...
        if(time() - $uData->last_seen > 600 || $uData->last_seen_ip != $request->ip()) {
            $uData->last_seen = time();
            $uData->last_seen_ip = $request->ip();
            $uData->save();
        }

        return response()->json([
            "error" => null,
            "username" => $uData->name,
            "ring" => $uData->ring,
            "updated_at" => $uData->updated_at
        ]);
    }
}
