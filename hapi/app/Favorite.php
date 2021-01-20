<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Favorite extends Model
{
    /**
     * Post that this belongs to
     */
    public function post() { return $this->belongsTo('App\Post'); }
    
}
