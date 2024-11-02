<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    protected $fillable = ['user_id', 'token', 'expires_at'];

    protected $table = 'sessions';

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}