<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Comment extends Model
{
    
    /**
     * Get parent post.
     */
    public function post() { return $this->belongsTo('App\Post'); }
}
