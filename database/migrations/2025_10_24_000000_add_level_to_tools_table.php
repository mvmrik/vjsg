<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tools', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->default(1)->after('tool_type_id');
        });
    }

    public function down()
    {
        Schema::table('tools', function (Blueprint $table) {
            $table->dropColumn('level');
        });
    }
};
