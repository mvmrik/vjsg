<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MarketRecomputeFees extends Command
{
    protected $signature = 'market:recompute-fees {--user_id= : (optional) user id to recompute}';
    protected $description = 'Recompute market fees (fee_bps) for all users or a single user';

    public function handle()
    {
        $userId = $this->option('user_id');
        if ($userId) {
            $this->info("Recomputing fee for user {$userId}");
            $fee = \App\Services\MarketService::recomputeUserFee(intval($userId));
            $this->info("User {$userId} fee_bps set to {$fee}");
            return 0;
        }

        $this->info('Recomputing fees for all users...');
        $users = DB::table('users')->select('id')->pluck('id');
        $count = 0;
        foreach ($users as $id) {
            try {
                $fee = \App\Services\MarketService::recomputeUserFee(intval($id));
                $this->line("user {$id} -> fee_bps={$fee}");
                $count++;
            } catch (\Exception $e) {
                $this->error("Failed for user {$id}: " . $e->getMessage());
            }
        }
        $this->info("Recomputed fees for {$count} users");
        return 0;
    }
}
