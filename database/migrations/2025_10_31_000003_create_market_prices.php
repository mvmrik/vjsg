<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('market_prices', function (Blueprint $table) {
            $table->unsignedInteger('tool_type_id')->primary();
            $table->unsignedBigInteger('last_price')->nullable();
            $table->unsignedBigInteger('vwap_24h')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('market_prices');
    }
};
