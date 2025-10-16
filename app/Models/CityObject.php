<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CityObject extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'parcel_id',
        'object_type',
        'level',
        'x',
        'y',
        'ready_at',
        'build_seconds'
    ];

    protected $casts = [
        // ready_at is now an integer timestamp, no casting needed
    ];

    // No $dates array needed anymore

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parcel()
    {
        return $this->belongsTo(Parcel::class);
    }
}
