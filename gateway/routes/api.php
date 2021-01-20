<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

// Verification App
Route::post('/verify/generate', 'VerifyController@generatePayload');
Route::post('/verify/confirm',  'VerifyController@verify');
Route::post('/verify/send',  'VerifyController@sendValidEmail');

// Common - user information
Route::post('/legacy/login', 'LegacyLoginController@login');
Route::get('/legacy/info',   'LegacyLoginController@info');

// User actions
Route::post('/users/changepwd', 'UsersController@changePwd');
Route::post('/users/recoverpwd', 'UsersController@recoverPwd');
Route::post('/users/recoverpwd2', 'UsersController@recoverPwd2');
Route::post('/users/info', 'UsersController@exportinfo');

// Internal - called from Python only
Route::post('/verify/intp', 'VerifyController@internalRecv');

// Mail
Route::post('/mails/inbound', function(Request $request) {
    Log::info("Received mail: ", print_r($request->all(), true));
});