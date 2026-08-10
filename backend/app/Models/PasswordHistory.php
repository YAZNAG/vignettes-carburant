<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordHistory extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'password', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
