<?php
/**
 * @package         com.ngen.hole
 * @author          -removed-
 * @copyright       (c) 2020 authors
 * @license         GNU General Public License 2.0, excluding later versions
 *
 * This is the "Hole" application. It implements the Hole API (hapi).
 * Most of the logic is compatible but rewritten for better flow
 * and formalized APIs.
 */

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\BanController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Route::get('/', function () {
//     return view('welcome');
// });

Route::view('/', "welcome");

Route::group(['middleware' => ['web', App\Http\Middleware\VerifyUserToken::class, App\Http\Middleware\BuildUserData::class]], function () {
    Route::get('/api/users/info', 'UsersController@info');
});


Route::view('/rules', "rules");
Route::view('/rules-bridge', "rules-bridge");

// Route::middleware([\App\Http\Middleware\VerifyUserToken::class])->group(function() {
Route::get('/pillory', [BanController::class, 'list']);

//Route::view('/about', 'about');