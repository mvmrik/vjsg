<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'tool_type_id', 'count', 'temp_count'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function toolType()
    {
        return $this->belongsTo(ToolType::class);
    }
}
