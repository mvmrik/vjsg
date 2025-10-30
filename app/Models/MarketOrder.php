<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketOrder extends Model
{
    protected $table = 'market_orders';
    protected $guarded = [];

    public function remaining()
    {
        return $this->quantity - $this->filled_quantity;
    }
}
