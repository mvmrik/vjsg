<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('object_type_tool_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_type_id')->constrained('object_types')->onDelete('cascade');
            $table->foreignId('tool_type_id')->constrained('tool_types')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('object_type_tool_type');
    }
};