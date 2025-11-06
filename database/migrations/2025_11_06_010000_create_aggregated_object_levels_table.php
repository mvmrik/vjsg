<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('aggregated_object_levels')) {
            Schema::create('aggregated_object_levels', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('object_type')->index();
                $table->integer('total_level')->default(0);
                $table->timestamps();
                $table->unique(['user_id', 'object_type']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('aggregated_object_levels');
    }
};
