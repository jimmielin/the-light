<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

use App\User;
use App\Invite;
use App\Mail\VerifyEmail;

use phpseclib\Crypt\RSA;

class VerifyController extends Controller
{
    public function verifyFromEmail(Request $request, String $token) {
        // verify if there is a token ...
        if(strlen($token) > 0) {
            // verify if user_token is present in database
            $uData = User::where("user_token", $token)->first();
            if($uData) {
                // If yes, verify email
                if($uData->email_verified_at === null) {
                    if($uData->invited_code) {
                        // decrement the invite code usage ...
                        $iData = Invite::where("code", $uData->invited_code)->first();
                        if($iData) {
                            $iData->remaining = $iData->remaining - 1;
                            $iData->save();
                        }
                    }

                    // reset user_token for safety
                    $uData->user_token = "5" . Str::random(33);

                    $uData->email_verified_at = now();
                    $uData->save();
                    // Save email verification status
                }

                // If ever logged in, do not reveal information
                if($uData->last_seen_ip == "0.0.0.1") {
                    $email = $uData->email;
                }
                else {
                    $email = "注册邮箱";
                }

                return view("verify.success", ["email" => $email]);
            } else {
                return view("verify.error");
            }
        } else {
            return redirect("/");
        }
    }

    public function sendValidEmail(Request $request) {
        if($request->has(["email", "password", "invite"])) {
            // validate reCAPTCHA ...
            if(!$request->has("recaptcha")) {
                return response()->json(["success" => false, "error" => "CaptchaValidationFailed"]);
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
                return response()->json(["success" => false, "error" => "CaptchaValidationFailed"]);
            }

            $password = $request->input("password");
            $invited_code = strtoupper(trim($request->input("invite"))); // note invite codes are all upper

            if(strlen($password) < 8) {
                return response()->json(["success" => false, "error" => "InsufficientPasswordLength"]);
            }

            // check if email is a duplicate
            $email = filter_var($request->input("email"), FILTER_VALIDATE_EMAIL);
            if(!$email) {
                return response()->json(["success" => false, "error" => "InvalidEmail"]);
            }

            $uData = User::where("email", $email)->whereNotNull("email_verified_at")->first();
            if($uData) {
                return response()->json(["success" => false, "error" => "DuplicateEmail"]);
            }
            
            $segs = explode('@', $email);
            $domain = $segs[1];
            if(strpos($domain, "pku.edu.cn") === false && strpos($domain, "bjmu.edu.cn") === false) {
                return response()->json(["success" => false, "error" => "MustUsePKUEmail"]);
            }

            // Restricted emails...
            $restrictedEmails = ["-removed-"];
            if(in_array($email, $restrictedEmails)) {
                return response()->json(["success" => false, "error" => "DuplicateEmail"]);
            }

            // Check if there is already a user, if yes then do not insert, simply resend the valid
            // email
            $uData = User::where("email", $email)->first();
            if($uData) {
                // Check if the password matches. if not, remake the hash
                if(!Hash::check($password, $uData->password)) {
                    $uData->password = Hash::make($password);
                    $uData->save();
                }

                $isExisting = true;
            }
            else {
                // check for invite code.
                // note - we do not deduct invite code uses here, only after user is validated
                // this means that more users may be able to be crammed into an invite code than it is
                // supposed to... but we really don't care
                
                /**
                 * hpl 10/21/20 no longer used
                $iData = Invite::where("code", $invited_code)->first();
                if(!$iData) {
                    return response()->json(["success" => false, "error" => "InviteCodeInvalid", "invite" => $invited_code]);
                }

                if($iData->remaining <= 0) {
                    return response()->json(["success" => false, "error" => "InviteCodeExhausted"]);
                }
                */

                // generate user_token and payload
                // all NG user_tokens start with 6 when validated with ShortEINPayload
                $user_token = "6" . Str::random(32) . "8";

                // Create the user in the database, but do not set email_verified_at,
                // which is a prerequisite. Generate a username using the first portion of the email
                $uData = new User;
                $uData->name = $segs[0];
                $uData->email = $email;
                $uData->password = Hash::make($password);
                $uData->user_token = $user_token;
                $uData->first_seen_ip = $request->ip();
                $uData->invited_code = $invited_code;
                $uData->save();

                $isExisting = false;
            }
            
            // ONLY send the validation code *if* we have not sent a validation email to this user within 1 minute
            // and also, do not send more than 3 mails...

            // Send the verification email - user will click user_token for validation
            // as user_token is NOT revealed at any time before this, this is a sound validation token
            if($isExisting && Cache::get('_gateway_last_send_' . sha1($request->ip()), 0) + 50 > time()) {
                // Do not send - flood control.
                return response()->json([
                    "success" => false,
                    "error" => "ResendVerificationEmailFloodControl"
                ]);
            }

            Mail::to($uData->email)->send(new VerifyEmail($uData));
            Cache::put('_gateway_last_send_' . sha1($request->ip()), time(), 600);

            return response()->json([
                "success" => true,
                "auth_method" => "EmailShortEINPayload"
            ]);
        }
    }
    
    public function generatePayload(Request $request) {
        if($request->has(["username", "password", "invite"])) {
            $username = $request->input("username");
            $password = $request->input("password");
            $invited_code = strtoupper(trim($request->input("invite"))); // note invite codes are all upper

            if(strlen($password) < 8) {
                return response()->json(["success" => false, "error" => "InsufficientPasswordLength"]);
            }

            if(strlen($username) < 2) {
                return response()->json(["success" => false, "error" => "InsufficientUsernameLength"]);
            }

            // verify if username is existent
            $uCount = User::where("name", $username)->count();
            if($uCount > 0) {
                return response()->json(["success" => false, "error" => "UsernameTaken"]);
            }

            // check for invite code.
            // note - we do not deduct invite code uses here, only in the email.
            // this means that more users may be able to be crammed into an invite code than it is
            // supposed to... but we really don't care
            $iData = Invite::where("code", $invited_code)->first();
            if(!$iData) {
                return response()->json(["success" => false, "error" => "InviteCodeInvalid", "invite" => $invited_code]);
            }

            if($iData->remaining <= 0) {
                return response()->json(["success" => false, "error" => "InviteCodeExhausted"]);
            }

            // generate user_token and payload
            // all NG user_tokens start with 3
            $user_token = "3" . Str::random(33);

            $rsa = new RSA();
            $rsa->loadKey(config('app.gateway_pubkey'));

            $payload = ["username" => $username, "password" => $password, "user_token" => $user_token, "ip" => $request->ip(), "invited_code" => $invited_code, "version" => "1.2"];
            $ciphertext = "PH3{" . base64_encode($rsa->encrypt(json_encode($payload))) . "}";

            return response()->json([
                "success" => true,
                "auth_method" => "OnlineEncipheredEIN",
                "user_token" => $user_token,
                "cipher" => $ciphertext
            ]);
        }
        else {
            return response()->json([
                "success" => false,
                "error"   => "IncompleteParameters"
            ]);
        }
    }


    public function verify(Request $request) {
        if($request->has("user_token")) {
            // verify if user_token is present in database
            $uData = User::where("user_token", $request->input("user_token"))->first();
            if($uData) {
                // If yes, verify email
                if($uData->email_verified_at === null) {
                    if($uData->invited_code) {
                        // decrement the invite code usage ...
                        $iData = Invite::where("code", $uData->invited_code)->first();
                        if($iData) {
                            $iData->remaining = $iData->remaining - 1;
                            $iData->save();
                        }
                    }

                    // reset user_token for safety
                    $uData->user_token = "5" . Str::random(33);

                    $uData->email_verified_at = now();
                    $uData->save();
                    // Save email verification status
                }

                // If ever logged in, do not reveal information
                if($uData->last_seen_ip == "0.0.0.1") {
                    $email = $uData->email;
                }
                else {
                    $email = "注册邮箱";
                }

                return response()->json(["success" => true, "email" => $email]);
            } else {
                return response()->json(["success" => false, "error" => "OnlineVerificationNotPresent"]);
            }
        } else {
            return response()->json([
                "success" => false,
                "error"   => "IncompleteParameters"
            ]);
        }
    }

    /**
     * Internal receive
     */
    public function internalRecv(Request $request) {
        if($request->has(["secret", "payload", "sender"])) {
            if($request->input("secret") != "G7WxNqrPWYh59Xwt9JmyshzxJkan4AZpV6fk8jLgCUPquBpN" && $request->input("secret") != "aS9mh88acdCwZhL5ubZCKNy7thMHbawSwnpxq4432jJKacvR") {
                abort(403);
            }

            // Handle the payload by decrypting it
            $rsa = new RSA();
            $rsa->loadKey(config('app.gateway_privkey'));

            $payload = str_replace('&#43;', '+', $request->input("payload"));
            $payload = str_replace('&#45;', '-', $payload);
            $payload = str_replace('&#47;', '/', $payload);
            $payload = str_replace('&#61;', '=', $payload);

            $payload_dec = $rsa->decrypt(base64_decode($payload));
            // attempt to json_decode
            $payload_ddec = json_decode($payload_dec, true);
            if(is_array($payload_ddec)) {
                // ok, verify
                if(isset($payload_ddec["username"], $payload_ddec["password"], $payload_ddec["user_token"])) {
                    // check if user exists with duplicate email. if yes, then update instead ...
                    $ucData = User::where("email", $request->input("sender"))->first();
                    // do not update. decide on ux later. for now, return a false response
                    if($ucData) {
                        return response()->json(["success" => false, "error" => "EmailAlreadyExists"]);
                    }

                    // insert into database
                    $uData = new User;
                    $uData->name = $payload_ddec["username"];
                    $uData->email = $request->input("sender");
                    $uData->email_verified_at = now();
                    $uData->password = Hash::make($payload_ddec["password"]);
                    // $uData->created_at = now();
                    // $uData->updated_at = now();
                    $uData->user_token = $payload_ddec["user_token"];
                    $uData->first_seen_ip = $payload_ddec["ip"];
                    if(isset($payload_ddec["invited_code"])) {
                        // decrement the invite code usage ...
                        $iData = Invite::where("code", $payload_ddec["invited_code"])->first();
                        if($iData) {
                            $iData->remaining = $iData->remaining - 1;
                            $iData->save();
                            $uData->invited_code = $payload_ddec["invited_code"];
                        }
                        else {
                            $uData->invited_code = "UNK";
                        }
                    }
                    $uData->save();

                    return response()->json(["success" => true]);
                }
            }
            else {
                return response()->json(["success" => false, "error" => "MalformedPayloadInner"]);
            }

        } else {
            abort(404);
        }
    }
}
