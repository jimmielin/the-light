<?php

namespace App\Observers;

use App\Favorite;

class FavoriteObserver
{
    /**
     * Handle the favorite "created" event.
     *
     * @param  \App\Favorite  $favorite
     * @return void
     */
    public function created(Favorite $favorite)
    {
        // Update number of favorites in foreign constraint
        $post = $favorite->post;
        $post->favorite_count = Favorite::where("post_id", $post->id)->count();
        $post->timestamps = false;
        $post->save();
    }

    /**
     * Handle the favorite "deleted" event.
     * WARNING: Note that the model DELETED event only trips if you call this from the MODEL!
     *
     * @param  \App\Favorite  $favorite
     * @return void
     */
    public function deleted(Favorite $favorite)
    {
        // Update number of favorites in foreign constraint
        $post = $favorite->post;
        $post->favorite_count = Favorite::where("post_id", $post->id)->count();
        $post->timestamps = false;
        $post->save();
    }
}
