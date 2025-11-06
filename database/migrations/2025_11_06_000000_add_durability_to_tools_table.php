<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Add durability (0-100) and remove old level column if present.
        if (!Schema::hasColumn('tools', 'durability')) {
            Schema::table('tools', function (Blueprint $table) {
                $table->unsignedTinyInteger('durability')->default(100)->after('tool_type_id');
            });
        }

        // If an old 'level' column exists, copy or set reasonable defaults then drop it.
        if (Schema::hasColumn('tools', 'level')) {
            // existing project used small default 1; set durability to 100 for all existing rows
            DB::table('tools')->update(['durability' => 100]);

            Schema::table('tools', function (Blueprint $table) {
                $table->dropColumn('level');
            });
        }
    }

    public function down()
    {
        // Recreate old 'level' column (default 1) and drop 'durability'
        if (!Schema::hasColumn('tools', 'level')) {
            Schema::table('tools', function (Blueprint $table) {
                $table->unsignedTinyInteger('level')->default(1)->after('tool_type_id');
            });
            DB::table('tools')->update(['level' => 1]);
        }

        if (Schema::hasColumn('tools', 'durability')) {
            Schema::table('tools', function (Blueprint $table) {
                $table->dropColumn('durability');
            });
        }
    }
};
