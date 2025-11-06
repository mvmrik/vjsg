<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('users')) return;

        if (!Schema::hasColumn('users', 'fee_bps')) return;

        $driver = DB::getDriverName();
        try {
            if ($driver === 'mysql') {
                // MySQL: alter column to set new default
                DB::statement('ALTER TABLE `users` MODIFY `fee_bps` INT UNSIGNED NOT NULL DEFAULT 10000');
            } elseif ($driver === 'pgsql') {
                DB::statement('ALTER TABLE "users" ALTER COLUMN fee_bps SET DEFAULT 10000');
            } else {
                // SQLite and others: cannot easily change default, ensure existing NULLs set and keep value for new inserts via application-level handling
                // As fallback, update any NULL or existing rows with default 1000 to 10000 only for new users, leave existing users intact.
                DB::table('users')->whereNull('fee_bps')->update(['fee_bps' => 10000]);
            }
        } catch (\Exception $e) {
            // Log and continue; migration should not break on unsupported platforms
            try { \Illuminate\Support\Facades\Log::error('Failed to alter users.fee_bps default: ' . $e->getMessage()); } catch (\Exception $_) {}
        }
    }

    public function down()
    {
        if (!Schema::hasTable('users')) return;
        if (!Schema::hasColumn('users', 'fee_bps')) return;

        $driver = DB::getDriverName();
        try {
            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE `users` MODIFY `fee_bps` INT UNSIGNED NOT NULL DEFAULT 1000');
            } elseif ($driver === 'pgsql') {
                DB::statement('ALTER TABLE "users" ALTER COLUMN fee_bps SET DEFAULT 1000');
            } else {
                // SQLite fallback: update NULLs back to 1000 only if they were set by this migration is not easily reversible.
            }
        } catch (\Exception $e) {
            try { \Illuminate\Support\Facades\Log::error('Failed to revert users.fee_bps default: ' . $e->getMessage()); } catch (\Exception $_) {}
        }
    }
};
