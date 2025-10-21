<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tool_types', function (Blueprint $table) {
            // production_seconds: how many seconds it takes to produce 1 unit (nullable)
            $table->integer('production_seconds')->nullable()->after('description');
            // produces_tool_type_id: when raw is processed, which tool_type id is produced
            $table->unsignedBigInteger('produces_tool_type_id')->nullable()->after('production_seconds');
            $table->foreign('produces_tool_type_id')->references('id')->on('tool_types')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('tool_types', function (Blueprint $table) {
            $table->dropForeign(['produces_tool_type_id']);
            $table->dropColumn(['produces_tool_type_id', 'production_seconds']);
        });
    }
};
