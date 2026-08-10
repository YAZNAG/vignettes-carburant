<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TwoFactorRecoveryCode extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'code', 'used_at', 'created_at'];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
