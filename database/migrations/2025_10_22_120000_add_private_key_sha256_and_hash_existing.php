<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add a sha256 lookup column for private key (unique)
            $table->string('private_key_sha256', 64)->nullable()->unique()->after('private_key');
        });

        // Backfill existing users: compute sha256 and bcrypt-hash existing private keys
        $users = DB::table('users')->select('id', 'private_key')->get();
        foreach ($users as $u) {
            if (empty($u->private_key)) continue;
            try {
                $sha = hash('sha256', $u->private_key);
                $bcrypt = Hash::make($u->private_key);
                DB::table('users')->where('id', $u->id)->update([
                    'private_key_sha256' => $sha,
                    'private_key' => $bcrypt
                ]);
            } catch (\Throwable $e) {
                // If something goes wrong, skip updating this user but log it
                \Log::error('Failed to backfill private key for user ' . $u->id . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['private_key_sha256']);
            $table->dropColumn('private_key_sha256');
        });
    }
};
