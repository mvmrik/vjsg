<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ObjectType extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'icon',
        'build_time_minutes',
        'meta'
    ];

    protected $casts = [
        'meta' => 'array'
    ];
}
