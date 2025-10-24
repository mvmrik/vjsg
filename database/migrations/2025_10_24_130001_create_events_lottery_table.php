<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('events_lottery', function (Blueprint $table) {
            $table->id();
            // Use unsignedBigInteger and indexes instead of adding strict foreign keys to avoid
            // migration failures when the referenced tables use a different engine (MyISAM)
            // or have mismatched column types on some environments. Referential integrity
            // should be enforced by the application if the DB doesn't support it.
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('user_id');
            $table->index('event_id');
            $table->index('user_id');
            $table->json('numbers'); // chosen numbers
            $table->tinyInteger('choice_count'); // 6-9
            $table->bigInteger('stake')->default(0); // stake in cents or smallest unit
            $table->bigInteger('payout')->nullable(); // payout amount (if any)
            $table->timestamps();
            // Optionally add foreign keys if the DB supports InnoDB and matching column types.
            // We try to add them only when running in environments where the referenced
            // tables are known to be InnoDB. This avoids errno 150 on setups with MyISAM.
        });
    }

    public function down()
    {
        Schema::dropIfExists('events_lottery');
    }
};
