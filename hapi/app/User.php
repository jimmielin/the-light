<?php

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Rennokki\QueryCache\Traits\QueryCacheable;

class User extends Authenticatable
{
    use Notifiable;
    use QueryCacheable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public $entitlements = [];


    /**
     * Hole DB Relationships
     * @var array
     */

    public function favorites() { return $this->hasMany('App\Favorite'); }

    public function favorite_holes() {
        return $this->belongsToMany('App\Post', 'favorites');
    }

    public function messages() { return $this->hasMany('App\Message'); }

    public function invite() { return $this->hasOne('App\Invite', 'user_id'); }
}
