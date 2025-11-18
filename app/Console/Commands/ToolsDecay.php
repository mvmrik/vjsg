<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ToolsDecay extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tools:decay';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daily decay of installed tools: reduce durability and remove broken tools';

    /**
     * Execute the console command.
     * 
     * Applies daily tool decay based on user's workshop level:
     * - No workshops (0 level): 100% decay per day (tools last 1 day)
     * - Each workshop level reduces decay by 0.99%
     * - Max 100 workshop levels = 1% decay (100 days lifespan)
     * 
     * Formula: decay_percent = max(1, 100 - (workshopLevel * 0.99))
     * 
     * NOTE: This command MUST run AFTER population:births to ensure
     * tools are counted/used before they decay and potentially get removed.
     */
    public function handle()
    {
        $this->info('Starting tools decay run');

        DB::beginTransaction();
        try {
            // Get all users with tools
            $usersWithTools = DB::table('tools')
                ->join('city_objects', 'tools.object_id', '=', 'city_objects.id')
                ->select('city_objects.user_id')
                ->distinct()
                ->pluck('user_id');

            // Process each user separately to apply different decay rates
            $totalDeleted = 0;
            $affectedPairs = [];

            foreach ($usersWithTools as $userId) {
                // Get workshop level and calculate decay rate
                $decayPercent = \App\Services\ObjectLevelService::getToolsDecayRate($userId);
                
                $this->info("User {$userId}: Decay rate {$decayPercent}%");

                // Apply decay to this user's tools
                DB::statement(
                    'UPDATE tools 
                     JOIN city_objects ON tools.object_id = city_objects.id 
                     SET tools.durability = GREATEST(tools.durability - ?, 0) 
                     WHERE city_objects.user_id = ? AND tools.durability IS NOT NULL',
                    [$decayPercent, $userId]
                );

                // Find affected (user_id, object_type) pairs for this user's broken tools
                $userAffectedPairs = DB::table('tools')
                    ->join('city_objects', 'tools.object_id', '=', 'city_objects.id')
                    ->where('city_objects.user_id', $userId)
                    ->where('tools.durability', '<=', 0)
                    ->select('city_objects.user_id', 'city_objects.object_type')
                    ->distinct()
                    ->get();

                foreach ($userAffectedPairs as $pair) {
                    $affectedPairs[] = $pair;
                }

                // Remove broken tools for this user
                $deleted = DB::table('tools')
                    ->join('city_objects', 'tools.object_id', '=', 'city_objects.id')
                    ->where('city_objects.user_id', $userId)
                    ->where('tools.durability', '<=', 0)
                    ->delete();

                $totalDeleted += $deleted;
            }

            // Recompute cached aggregates and related systems for affected pairs
            foreach ($affectedPairs as $p) {
                $uid = $p->user_id;
                $otype = $p->object_type;
                try {
                    \App\Services\ObjectLevelService::recomputeAndStore($uid, $otype);
                    if ($otype === 'bank') {
                        \App\Services\MarketService::recomputeUserFee($uid);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to recompute aggregate after tools decay for user ' . $uid . ' type ' . $otype . ': ' . $e->getMessage());
                }
            }

            DB::commit();

            $this->info('Tools decay completed. Removed ' . $totalDeleted . ' broken tools.');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Tools decay failed: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
