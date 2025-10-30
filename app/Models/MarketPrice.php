<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketPrice extends Model
{
    protected $table = 'market_prices';
    protected $primaryKey = 'tool_type_id';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];
}
