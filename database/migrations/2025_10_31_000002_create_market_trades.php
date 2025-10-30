<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('market_trades', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('tool_type_id');
            $table->unsignedBigInteger('price');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('buyer_id');
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('buy_order_id')->nullable();
            $table->unsignedBigInteger('sell_order_id')->nullable();
            $table->timestamp('executed_at')->useCurrent();
            $table->timestamps();

            $table->index(['tool_type_id','executed_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('market_trades');
    }
};
