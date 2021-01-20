<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

use App\Comment;
use App\Favorite;
use App\Flag;
use App\Post;
use App\User;
use App\Observers\PostObserver;
use App\Observers\CommentObserver;
use App\Observers\FavoriteObserver;
use App\Observers\FlagObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Fix some mariadb error
        Schema::defaultStringLength(191); 

        Post::observe(PostObserver::class);
        Comment::observe(CommentObserver::class);
        Favorite::observe(FavoriteObserver::class);
        Flag::observe(FlagObserver::class);

    }
}
