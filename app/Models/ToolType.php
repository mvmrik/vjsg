<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ToolType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'icon'];

    public function objectTypes()
    {
        return $this->belongsToMany(ObjectType::class, 'object_type_tool_type');
    }

    public function tools()
    {
        return $this->hasMany(Tool::class);
    }
}