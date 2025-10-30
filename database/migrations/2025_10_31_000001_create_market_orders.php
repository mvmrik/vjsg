<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('market_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('tool_type_id');
            $table->enum('side', ['buy','sell']);
            $table->unsignedBigInteger('price'); // integer coins
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('filled_quantity')->default(0);
            $table->enum('status', ['open','partial','filled','cancelled'])->default('open');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['tool_type_id','side','price']);
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('market_orders');
    }
};
