<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OccupiedWorker extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'level',
        'count',
        'occupied_until',
        'city_object_id'
    ];

    protected $casts = [
        'occupied_until' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cityObject()
    {
        return $this->belongsTo(CityObject::class);
    }
}
