<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            $table->integer('city_x')->default(0)->after('lng');
            $table->integer('city_y')->default(0)->after('city_x');
            // Добавяне на уникален индекс за предотвратяване на дублиране per user
            $table->unique(['user_id', 'city_x', 'city_y'], 'unique_user_city_coords');
        });
    }

    public function down(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            $table->dropUnique('unique_user_city_coords');
            $table->dropColumn(['city_x', 'city_y']);
        });
    }
};