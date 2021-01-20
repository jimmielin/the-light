<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyBridgeRequest
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
        if(!$request->has(["data", "digest"])) {
            return response()->json(["error_msg" => "请求格式错误", "code" => 1]);
        }

        // Verify SHA256 Digest
        $data = $request->input("data");
        $digest = $request->input("digest");
        if(hash("sha256", $data . config("app.api_bridge_shared_secret")) != $digest) {
            return response()->json(["error_msg" => "Digest错误", "error" => "InvalidDigest"]);
            //dont do anything for now
        }

        // Decode request - save as array and not object for safety
        $request->bridge = json_decode($data, true);
        if($request->bridge === null) {
            return response()->json(["error_msg" => "JSON解析失败", "code" => 1]);
        }

        return $next($request);
    }
}
