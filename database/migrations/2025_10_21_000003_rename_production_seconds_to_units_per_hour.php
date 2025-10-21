<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tool_types', function (Blueprint $table) {
            if (Schema::hasColumn('tool_types', 'production_seconds')) {
                $table->renameColumn('production_seconds', 'units_per_hour');
            }
        });
    }

    public function down()
    {
        Schema::table('tool_types', function (Blueprint $table) {
            if (Schema::hasColumn('tool_types', 'units_per_hour')) {
                $table->renameColumn('units_per_hour', 'production_seconds');
            }
        });
    }
};
