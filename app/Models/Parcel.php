<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parcel extends Model
{
    protected $fillable = [
        'user_id',
        'lat',
        'lng',
        'type',
        'city_x',
        'city_y'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
