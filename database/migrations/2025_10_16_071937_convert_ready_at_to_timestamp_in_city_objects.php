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
        Schema::table('city_objects', function (Blueprint $table) {
            // Drop the old datetime column
            $table->dropColumn('ready_at');
        });
        
        Schema::table('city_objects', function (Blueprint $table) {
            // Add new integer timestamp column (UNIX timestamp in seconds)
            $table->unsignedBigInteger('ready_at')->nullable()->after('y');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('city_objects', function (Blueprint $table) {
            $table->dropColumn('ready_at');
        });
        
        Schema::table('city_objects', function (Blueprint $table) {
            $table->dateTime('ready_at')->nullable()->after('y');
        });
    }
};
