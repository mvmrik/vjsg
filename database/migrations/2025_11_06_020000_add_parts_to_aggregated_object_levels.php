<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('aggregated_object_levels')) {
            Schema::table('aggregated_object_levels', function (Blueprint $table) {
                if (!Schema::hasColumn('aggregated_object_levels', 'object_level_sum')) {
                    $table->integer('object_level_sum')->default(0)->after('object_type');
                }
                if (!Schema::hasColumn('aggregated_object_levels', 'tool_sum')) {
                    $table->integer('tool_sum')->default(0)->after('object_level_sum');
                }
                if (!Schema::hasColumn('aggregated_object_levels', 'total_level')) {
                    $table->integer('total_level')->default(0)->after('tool_sum');
                }
            });

            // Initialize new columns from existing total_level where present
            DB::statement('UPDATE aggregated_object_levels SET object_level_sum = total_level, tool_sum = 0 WHERE object_level_sum = 0 AND tool_sum = 0');
        }
    }

    public function down()
    {
        if (Schema::hasTable('aggregated_object_levels')) {
            Schema::table('aggregated_object_levels', function (Blueprint $table) {
                if (Schema::hasColumn('aggregated_object_levels', 'object_level_sum')) {
                    $table->dropColumn('object_level_sum');
                }
                if (Schema::hasColumn('aggregated_object_levels', 'tool_sum')) {
                    $table->dropColumn('tool_sum');
                }
                if (Schema::hasColumn('aggregated_object_levels', 'total_level')) {
                    $table->dropColumn('total_level');
                }
            });
        }
    }
};
