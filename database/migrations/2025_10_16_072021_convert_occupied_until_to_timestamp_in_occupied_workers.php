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
        Schema::table('occupied_workers', function (Blueprint $table) {
            $table->dropColumn('occupied_until');
        });
        
        Schema::table('occupied_workers', function (Blueprint $table) {
            $table->unsignedBigInteger('occupied_until')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('occupied_workers', function (Blueprint $table) {
            $table->dropColumn('occupied_until');
        });
        
        Schema::table('occupied_workers', function (Blueprint $table) {
            $table->dateTime('occupied_until')->nullable();
        });
    }
};
