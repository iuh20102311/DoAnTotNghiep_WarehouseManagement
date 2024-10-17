<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    use HasFactory;
    protected $table = 'session';
    protected $fillable = ['user_id','token','created_at','updated_at'];
    protected $primaryKey = 'id';
    public $timestamps = true;
}