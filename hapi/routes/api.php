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
 *
 * Auto-generated API documentation is available at /docs in debug mode and some.
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\UsersController;
use App\Http\Controllers\PostsController;
use App\Http\Controllers\FavoritesController;
use App\Http\Controllers\FlagsController;
use App\Http\Controllers\MessagesController;

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

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });

/**
 * Access Routes
 */

Route::get('/users/invites', 'UsersController@invites');

/**
 * Hole Routes
 * Note that {id}, {after} are auto \d+ constrained
 */

// List
Route::get('/holes/list/{after?}', [PostsController::class, 'list'])->name('getlist');

Route::get('/holes/attention/{after?}', [PostsController::class, 'getFavorite'])->name('getattention');
Route::get('/holes/search/{after?}', [PostsController::class, 'search'])->name('search');

// View
Route::get('/holes/view/{id}/{after?}', [PostsController::class, 'view'])->name('gethole');

// Make posts and replies
Route::post('/holes/post', [PostsController::class, 'post'])->name('dopost');
Route::post('/holes/reply/{id}', [PostsController::class, 'reply'])->name('docomment');

Route::put('/holes/attention/do/{id}', 'FavoritesController@doFavorite')->name('favorite-switch');

// Flag system
Route::post('/holes/flag/{id}', 'FlagsController@post')->name('flag-post');
Route::post('/comments/flag/{id}', 'FlagsController@comment')->name('flag-comment');

/**
 * System/Messaging
 */
Route::get('/messages/list', 'MessagesController@view')->name('messages');

/**
 * Admin Routes
 */
Route::get('/holes/flag/{id}', 'FlagsController@viewPost')->name('view-flags-post');
Route::get('/comments/flag/{id}', 'FlagsController@viewComment')->name('view-flags-comment');

Route::post('/holes/tag/{id}', 'PostsController@tagPost')->name('tag-post');
Route::post('/comments/tag/{id}', 'PostsController@tagComment')->name('tag-comment');

Route::post('/holes/edit/{id}', 'PostsController@editPost')->name('edit-post');
Route::post('/comments/edit/{id}', 'PostsController@editComment')->name('edit-comment');

Route::post('/holes/unflag/{id}', 'FlagsController@unPost')->name('unflag-post');
Route::post('/comments/unflag/{id}', 'FlagsController@unComment')->name('unflag-comment');

