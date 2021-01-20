<?php

namespace App\Observers;

use App\User;
use Illuminate\Support\Str;

class UserObserver
{
    /**
     * Handle the user "creating" event.
     *
     * @param  \App\User  $user
     * @return void
     */
    public function creating(User $user)
    {
        if(strlen($user->user_token) != 34) {
            $user->user_token = "3" . Str::random(33);
        }
    }
}
