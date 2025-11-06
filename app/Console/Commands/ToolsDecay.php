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

    public function handle()
    {
        $this->info('Starting tools decay run');

        DB::beginTransaction();
        try {
            // Decrement durability by 1 for all tools that have durability > 0
            // Use a single fast UPDATE query for scale
            DB::statement('UPDATE tools SET durability = GREATEST(durability - 1, 0) WHERE durability IS NOT NULL');

            // Find affected (user_id, object_type) pairs for objects which lost tools
            $affectedPairs = DB::table('tools')
                ->join('city_objects', 'tools.object_id', '=', 'city_objects.id')
                ->where('tools.durability', '<=', 0)
                ->select('city_objects.user_id', 'city_objects.object_type')
                ->distinct()
                ->get();

            // Remove all tools that reached 0 durability
            $deleted = DB::table('tools')->where('durability', '<=', 0)->delete();

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

            $this->info('Tools decay completed. Removed ' . $deleted . ' broken tools.');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Tools decay failed: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
