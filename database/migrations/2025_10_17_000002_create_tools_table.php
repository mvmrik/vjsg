<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_id')->constrained('city_objects')->onDelete('cascade');
            $table->foreignId('tool_type_id')->constrained('tool_types')->onDelete('cascade');
            $table->integer('position_x');
            $table->integer('position_y');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('tools');
    }
};