<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Flag extends Model
{
    //
    public function user() { return $this->belongsTo('App\User'); }
    public function post() { return $this->belongsTo('App\Post'); }
    public function comment() { return $this->belongsTo('App\Comment'); }
}
