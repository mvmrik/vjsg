<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('inventories', function (Blueprint $table) {
            if (!Schema::hasColumn('inventories', 'reserved_count')) {
                $table->unsignedInteger('reserved_count')->default(0)->after('count');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'reserved_balance')) {
                $table->bigInteger('reserved_balance')->default(0)->after('balance');
            }
            if (!Schema::hasColumn('users', 'fee_bps')) {
                // fee in basis points; default 1000 = 10%
                $table->unsignedInteger('fee_bps')->default(1000)->after('reserved_balance');
            }
        });

        Schema::create('market_treasury', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('balance')->default(0);
            $table->timestamps();
        });
        // Insert initial treasury row
        DB::table('market_treasury')->insert([
            'balance' => 0,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    public function down()
    {
        Schema::table('inventories', function (Blueprint $table) {
            if (Schema::hasColumn('inventories', 'reserved_count')) {
                $table->dropColumn('reserved_count');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'reserved_balance')) {
                $table->dropColumn('reserved_balance');
            }
            if (Schema::hasColumn('users', 'fee_bps')) {
                $table->dropColumn('fee_bps');
            }
        });

        Schema::dropIfExists('market_treasury');
    }
};
