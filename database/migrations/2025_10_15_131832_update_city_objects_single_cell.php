<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('city_objects', function (Blueprint $table) {
            $table->unsignedTinyInteger('x')->after('parcel_id');
            $table->unsignedTinyInteger('y')->after('x');
        });

        // Migrate existing data (assuming single cell objects)
        DB::statement("
            UPDATE city_objects 
            SET x = JSON_UNQUOTE(JSON_EXTRACT(cells, '$[0].x')),
                y = JSON_UNQUOTE(JSON_EXTRACT(cells, '$[0].y'))
            WHERE JSON_LENGTH(cells) = 1
        ");

        Schema::table('city_objects', function (Blueprint $table) {
            $table->dropColumn('cells');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('city_objects', function (Blueprint $table) {
            $table->json('cells')->after('parcel_id');
        });

        // Migrate back (create single cell arrays)
        DB::statement("
            UPDATE city_objects 
            SET cells = JSON_ARRAY(JSON_OBJECT('x', x, 'y', y))
            WHERE x IS NOT NULL AND y IS NOT NULL
        ");

        Schema::table('city_objects', function (Blueprint $table) {
            $table->dropColumn(['x', 'y']);
        });
    }
};
