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
        Schema::create('object_types', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique(); // machine id, e.g. 'house'
            $table->string('name'); // human readable name
            $table->string('icon')->nullable(); // icon name used in frontend
            $table->integer('build_time_minutes')->default(1); // build time in minutes per object (or total)
            $table->json('meta')->nullable(); // optional extra data (width/height, etc.)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('object_types');
    }
};
