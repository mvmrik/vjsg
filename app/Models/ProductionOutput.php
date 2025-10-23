<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionOutput extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'city_object_id', 'tool_type_id', 'count'];
}
