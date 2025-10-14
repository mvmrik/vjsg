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
        'cells',
        'properties',
        'ready_at'
    ];

    protected $casts = [
        'properties' => 'array',
        'cells' => 'array'
    ];

    protected $dates = [
        'ready_at'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parcel()
    {
        return $this->belongsTo(Parcel::class);
    }
}
