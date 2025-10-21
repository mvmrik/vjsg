<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ToolType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'icon', 'units_per_hour', 'produces_tool_type_id'];

    public function objectTypes()
    {
        return $this->belongsToMany(ObjectType::class, 'object_type_tool_type');
    }

    public function tools()
    {
        return $this->hasMany(Tool::class);
    }

    public function produces()
    {
        return $this->belongsTo(ToolType::class, 'produces_tool_type_id');
    }
}