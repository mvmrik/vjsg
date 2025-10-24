<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('events_lottery', function (Blueprint $table) {
            $table->boolean('settled')->default(false)->after('payout');
        });
    }

    public function down()
    {
        Schema::table('events_lottery', function (Blueprint $table) {
            $table->dropColumn('settled');
        });
    }
};
