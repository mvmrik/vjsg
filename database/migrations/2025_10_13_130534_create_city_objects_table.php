<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('city_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('parcel_id')->constrained('parcels')->onDelete('cascade');
            $table->string('object_type'); // e.g., 'house', 'tree', etc.
            $table->integer('x'); // position in the grid (0-9 for 10x10)
            $table->integer('y');
            $table->integer('width'); // in grid cells
            $table->integer('height');
            $table->json('properties')->nullable(); // additional properties
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('city_objects');
    }
};
