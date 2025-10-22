<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRememberToken extends Model
{
    protected $table = 'user_remember_tokens';

    protected $fillable = [
        'user_id',
        'selector',
        'validator_hash',
        'device_name',
        'user_agent',
        'ip',
        'last_used_at',
        'expires_at',
    ];

    protected $dates = ['last_used_at', 'expires_at', 'created_at', 'updated_at'];

    public $timestamps = true;

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
