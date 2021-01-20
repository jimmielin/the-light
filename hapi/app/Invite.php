<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Invite extends Model
{
    // security: allow mass assignment on code, remaining as entry vector
    // is sanitized in UsersController@invites
    protected $fillable = ['code', 'remaining'];

    public function user() { return $this->belongsTo('App\User'); }
}
