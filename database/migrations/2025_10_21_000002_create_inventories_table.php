<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            // tool_type_id is the produced item/tool id
            $table->foreignId('tool_type_id')->constrained('tool_types')->onDelete('cascade');
            $table->integer('count')->default(0);
            // temporary count while production is ongoing
            $table->integer('temp_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'tool_type_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('inventories');
    }
};
