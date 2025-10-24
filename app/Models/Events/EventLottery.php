<?php

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventLottery extends Model
{
    use HasFactory;

    protected $table = 'events_lottery';
    protected $guarded = [];

    protected $casts = [
        'numbers' => 'array',
        'settled' => 'boolean',
        'meta' => 'array',
    ];

    protected $attributes = [
        'settled' => false,
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
