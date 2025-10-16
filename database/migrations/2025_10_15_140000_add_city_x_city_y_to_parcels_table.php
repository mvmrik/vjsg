<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            // Add columns only if they don't exist
            if (!Schema::hasColumn('parcels', 'city_x')) {
                $table->integer('city_x')->default(0)->after('lng');
            }
            if (!Schema::hasColumn('parcels', 'city_y')) {
                $table->integer('city_y')->default(0)->after('city_x');
            }
        });
        
        // Add unique index in separate statement to avoid conflicts
        try {
            Schema::table('parcels', function (Blueprint $table) {
                $table->unique(['user_id', 'city_x', 'city_y'], 'unique_user_city_coords');
            });
        } catch (\Exception $e) {
            // Index might already exist, ignore error
        }
    }

    public function down(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            $table->dropUnique('unique_user_city_coords');
            $table->dropColumn(['city_x', 'city_y']);
        });
    }
};