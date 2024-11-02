<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlacklistToken extends Model
{
    protected $fillable = ['token', 'user_id', 'expires_at'];

    protected $table = 'blacklist_tokens';
}