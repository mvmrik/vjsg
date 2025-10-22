<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // This migration converts existing plain sha256 lookup values into an HMAC-based
        // lookup using a server-side secret. It writes the HMAC into `private_key`
        // and drops the `private_key_sha256` column.

        $secret = env('PRIVATE_KEY_LOOKUP_KEY');
        if (empty($secret)) {
            // Fail fast: instruct the operator to set the env var
            throw new \RuntimeException('PRIVATE_KEY_LOOKUP_KEY must be set in .env before running this migration.');
        }

        $users = DB::table('users')->select('id', 'private_key_sha256')->get();
        foreach ($users as $u) {
            if (empty($u->private_key_sha256)) continue;
            try {
                $hmac = hash_hmac('sha256', $u->private_key_sha256, $secret);
                DB::table('users')->where('id', $u->id)->update(['private_key' => $hmac]);
            } catch (\Throwable $e) {
                \Log::error('Failed to convert private_key for user ' . $u->id . ': ' . $e->getMessage());
            }
        }

        // Drop the sha256 column
        if (Schema::hasColumn('users', 'private_key_sha256')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('private_key_sha256');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We cannot recover the original sha256 values once dropped. Throw to avoid accidental rollback.
        throw new \RuntimeException('This migration is irreversible.');
    }
};
