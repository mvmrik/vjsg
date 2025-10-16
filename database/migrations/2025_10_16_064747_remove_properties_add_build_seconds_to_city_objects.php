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
            // Add build_seconds column if it doesn't exist
            if (!Schema::hasColumn('city_objects', 'build_seconds')) {
                $table->integer('build_seconds')->nullable()->after('ready_at');
            }
            
            // Remove old properties column if it exists
            if (Schema::hasColumn('city_objects', 'properties')) {
                $table->dropColumn('properties');
            }
            
            // Remove workers column if it exists (not needed, using occupied_workers table)
            if (Schema::hasColumn('city_objects', 'workers')) {
                $table->dropColumn('workers');
            }
            
            // Remove building_type_id if it exists (not used)
            if (Schema::hasColumn('city_objects', 'building_type_id')) {
                $table->dropColumn('building_type_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('city_objects', function (Blueprint $table) {
            // Re-add properties as JSON if needed
            if (!Schema::hasColumn('city_objects', 'properties')) {
                $table->json('properties')->nullable();
            }
            
            // Remove build_seconds
            if (Schema::hasColumn('city_objects', 'build_seconds')) {
                $table->dropColumn('build_seconds');
            }
        });
    }
};
