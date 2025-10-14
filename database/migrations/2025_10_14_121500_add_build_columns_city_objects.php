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
            if (!Schema::hasColumn('city_objects', 'ready_at')) {
                $table->dateTime('ready_at')->nullable()->after('cells');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('city_objects', function (Blueprint $table) {
            if (Schema::hasColumn('city_objects', 'ready_at')) {
                $table->dropColumn('ready_at');
            }
        });
    }
};
