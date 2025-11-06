<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tool extends Model
{
    use HasFactory;

    protected $fillable = ['object_id', 'tool_type_id', 'position_x', 'position_y', 'durability'];

    // Tools start with full durability (100)
    protected $attributes = [
        'durability' => 100,
    ];

    public function object()
    {
        return $this->belongsTo(CityObject::class, 'object_id');
    }

    public function toolType()
    {
        return $this->belongsTo(ToolType::class);
    }
}