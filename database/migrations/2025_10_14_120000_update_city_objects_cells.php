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
            // Add JSON column for explicit cells (array of {x,y})
            $table->json('cells')->nullable()->after('object_type');

            // Drop rectangle columns if present
            if (Schema::hasColumn('city_objects', 'x')) {
                $table->dropColumn(['x', 'y', 'width', 'height']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('city_objects', function (Blueprint $table) {
            // Re-add rectangle columns (fallback)
            if (!Schema::hasColumn('city_objects', 'x')) {
                $table->integer('x')->default(0);
                $table->integer('y')->default(0);
                $table->integer('width')->default(1);
                $table->integer('height')->default(1);
            }
            if (Schema::hasColumn('city_objects', 'cells')) {
                $table->dropColumn('cells');
            }
        });
    }
};
