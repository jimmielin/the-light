<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;

use App\Ban;
use App\User;

class BanController extends Controller
{
    public function list(Request $request) {
        // Check for authentication token, if present, authenticate
        if($request->has("rr_token")) {
            $rr_token = $request->input("rr_token");
            if(strlen($rr_token) < 8) {

            }
            else {

                $uData = User::where("remember_token", $request->input("rr_token"))->first();
                if($uData) {
                    Auth::loginUsingId($uData->id);
                }

            }
            
            return redirect("/pillory");
        }

        if(!Auth::check()) {
            return view('login-prompt');
        }

        $bDatas = Ban::latest()->take(25)->get(["post_id", "comment_id", "created_at", "until", "verdict"]);
        return view('pillory', ["bDatas" => $bDatas]);
    }
}
