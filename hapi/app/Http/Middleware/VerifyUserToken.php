<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

use App\User;

class VerifyUserToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if(!$request->has("user_token")) {
            return response()->json(["error_msg" => "用户参数错误", "error" => "MissingUserToken"], 400);
        }

        // Verify user token correctness and store within request object
        // User tokens can only be accepted in GET query parameters
        $request->uData = User::where("user_token", $request->query("user_token", "none"))->whereNotNull("email_verified_at")->first();
        if(!$request->uData) {
            return response()->json(["error_msg" => "登录失效，请重试!", "error" => "Unauthenticated"]);
        }

        // Authenticate the user in the legacy mode
        // and remember, so pillory can be seen
        Auth::loginUsingId($request->uData->id);

        return $next($request);
    }
}
