<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

use App\User;
use App\Mail\RecoverPwdMail;

class UsersController extends Controller
{
    /**
     * Change Password
     * POST /users/changepwd
     *
     * email, password, new-password
     */
    public function changePwd(Request $request) {
        if(!$request->has(["email", "password", "new-password"])) {
            return response()->json(["error" => "InsufficientParameters", "error_msg" => "参数错误"]);
        }

        if(strlen($request->input("new-password")) < 8) {
            return response()->json(["error" => "InsufficientPasswordLength", "error_msg" => "您的密码过于简单，请至少使用8位数以上的密码。"]);
        }

        // Validate username and password
        $uData = User::where("email", $request->input("email"))->first();
        if(!$uData) {
            return response()->json(["error_msg" => "用户名/密码错误", "error" => "NoMatch"]);
        }
        
        if(Hash::check($request->input("password"), $uData->password)) {
            // remake the password
            $uData->password = Hash::make($request->input("new-password"));

            // also update request information
            $uData->last_seen = time();
            $uData->last_seen_ip = $request->ip();
            $uData->save();

            return response()->json(["error" => null]);
        }
        else {
            return response()->json(["error_msg" => "用户名/密码错误", "error" => "NoMatch"]);
        }
    }

    /**
     * Recover Password
     * POST /users/recoverpwd
     *
     * Flood-controlled by IP address - once per 5 minutes
     *
     * email, recaptcha
     */
    public function recoverPwd(Request $request) {
        if($request->has(["email", "recaptcha"])) {
            if(!$request->has("recaptcha")) {
                return response()->json(["error" => "InvalidEmail", "error_msg" => "安全验证失败，请重新尝试"]);
            }

            // verify captcha result
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://www.google.com/recaptcha/api/siteverify");
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'secret' => "-removed-",
                'response' => $request->input("recaptcha"),
                'remoteip' => $_SERVER['REMOTE_ADDR']
            ]);
            $respA = curl_exec($ch);
            $respB = json_decode($respA);
            curl_close($ch);

            if($respA === false || !$respB->success) {
                return response()->json(["error" => "InvalidEmail", "error_msg" => "安全验证失败，请重新尝试"]);
            }

            // check if account exists
            $email = filter_var($request->input("email"), FILTER_VALIDATE_EMAIL);
            if(!$email) {
                return response()->json(["error" => "InvalidEmail", "error_msg" => "邮箱格式错误"]);
            }

            if(Cache::get('_gateway_last_send_rst_' . sha1($request->ip()), 0) + 1800 > time()) {
                // Do not send - flood control.
                return response()->json(["error" => "FloodControl", "error_msg" => "很抱歉，请稍后再试。为安全原因，限制30分钟请求一次密码重置邮件。"]);
            }

            $uData = User::where("email", $email)->whereNotNull("email_verified_at")->first();
            if(!$uData) {
                return response()->json(["error" => null]); // drop silently
            }

            // Reset password link creation
            $resetLink = DB::table("password_resets")->where("email", $email)->first();
            if($resetLink && \Carbon\Carbon::parse($resetLink->created_at)->timestamp + 86400 > time()) {
                return response()->json(["error" => "FloodControl", "error_msg" => "为安全原因，每一个账户仅允许24小时发送一次密码重置邮件。"]);
            }

            DB::table("password_resets")->where("email", $email)->delete();
            $token = "7" . Str::random(32) . "8";

            $resetLink = DB::table("password_resets")->insert(
                ["email" => $email, "token" => $token, "created_at" => now()]
            );

            // Send
            Mail::to($uData->email)->send(new RecoverPwdMail($token));

            Cache::put('_gateway_last_send_rst_' . sha1($request->ip()), time(), 3600);

            return response()->json(["error" => null]); // drop silently
        }
    }

    /**
     * Recover Password Stage 2
     * POST /users/recoverpwd 2
     *
     * token, password
     * return email
     */
    public function recoverPwd2(Request $request) {
        if($request->has(["token", "password"])) {
            // check if token exists
            $resetLink = DB::table("password_resets")->where("token", $request->input("token"))->first();
            if(!$resetLink) {
                return response()->json(["error" => "NoSuchResetToken", "error_msg" => "该密码重置token不存在，请重试!"]);
            }

            if(\Carbon\Carbon::parse($resetLink->created_at)->timestamp + 259200 /* 3 days */ < time()) {
                return response()->json(["error" => "NoSuchResetToken", "error_msg" => "该密码重置token不存在，请重试!"]);
            }

            // retrieve the user...
            $email = $resetLink->email;
            $uData = User::where("email", $email)->whereNotNull("email_verified_at")->first();
            if(!$uData) {
                return response()->json(["error" => "NoSuchResetToken", "error_msg" => "该密码重置token不存在，请重试!"]);
            }

            // reset the password...
            $password = $request->input("password");
            if(strlen($password) < 8) {
                return response()->json(["error" => "InsufficientPasswordLength", "error_msg" => "请设置至少8位长的密码!"]);
            }

            // remake the password
            $uData->password = Hash::make($password);

            // also update request information
            $uData->last_seen = time();
            $uData->last_seen_ip = $request->ip();
            $uData->save();

            DB::table("password_resets")->where("email", $email)->update(["token" => "000reset" . Str::random(64)]);

            // return true

            return response()->json(["error" => null, "email" => $email]);
        }
    }
}
