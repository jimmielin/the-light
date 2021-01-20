<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Ban extends Model
{
    public function post() { return $this->belongsTo('App\Post'); }
    public function comment() { return $this->belongsTo('App\Comment'); }
}
