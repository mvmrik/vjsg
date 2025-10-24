<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'is_locked')) {
                $table->dropColumn('is_locked');
            }
        });
    }

    public function down()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->after('is_active');
        });
    }
};
