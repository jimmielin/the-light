<?php
/**
 * @package         com.ngen.gateway
 * @author          -removed-
 * @copyright       (c) 2020 authors.
 * @license         TBD
 *
 * This is the "Gateway" Application. It implements a centralized app interface,
 * an OAuth server, @pku.edu.cn email authenticator via backend/ -removed-
 * and allows -removed-.
 */

use Illuminate\Support\Facades\Route;

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

Route::view('/', 'gateway');
Route::view('/users/recoverpw', 'recoverpw');

Route::get('/verify/{token}', 'VerifyController@verifyFromEmail');

Route::view('/users/cp', 'usercp');