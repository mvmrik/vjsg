<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Person;
use App\Models\Notification;
use App\Models\OccupiedWorker;
use App\Models\ProductionOutput;
use App\Models\Inventory;

class PopulationBirths extends Command
{
    protected $signature = 'population:births';
    protected $description = 'Daily births: add people based on house levels and tool levels (house tools must have level)';

    public function handle()
    {
        $this->info('Starting population births (UTC): ' . now()->utc()->toDateTimeString());

        // Use cached aggregated values when available to avoid expensive SUMs.
        $houseAgg = DB::table('aggregated_object_levels')
            ->where('object_type', 'house')
            ->select('user_id', 'object_level_sum', 'tool_sum', 'total_level')
            ->get()
            ->keyBy('user_id')
            ->toArray();

        // Build maps similar to previous variables for compatibility
        $houseSums = [];
        $toolSums = [];
        foreach ($houseAgg as $uid => $row) {
            $houseSums[$uid] = intval($row->object_level_sum ?? 0);
            $toolSums[$uid] = intval($row->tool_sum ?? 0);
        }

    // Also include users who currently have people and users who have hospitals
    $peopleUsers = DB::table('people')->select('user_id')->distinct()->pluck('user_id')->toArray();
    $hospitalUsers = DB::table('city_objects')->where('object_type', 'hospital')->select('user_id')->distinct()->pluck('user_id')->toArray();

    // Merge keys (user ids) from houses, tools, people and hospitals
    $userIds = array_unique(array_merge(array_keys($houseSums), array_keys($toolSums), $peopleUsers, $hospitalUsers));

        foreach ($userIds as $userId) {
            try {
                // --- Mortality: account for hospitals ---
                try {
                    // New simplified mortality rule (level-threshold based):
                    // - base threshold is level 5 (everyone with level > 5 dies)
                    // - each hospital contributes its level to the threshold
                    // - tools inside hospitals contribute their tool level / 10 to the threshold
                    // - hospital effect is: sum(hospital.level) + sum(tools.level) / 10
                    // - round hospital effect half-up (PHP_ROUND_HALF_UP)
                    // - final threshold = 5 + round(hospital_effect)
                    // Remove ALL people with level > threshold (delete their rows and count them as deaths)

                    // Use cached aggregate for hospital if available (object_level_sum and tool_sum)
                    // Use cached aggregate only. If cache is missing, treat hospital contributions as zero.
                    $hospitalRow = \App\Services\ObjectLevelService::getCachedAggregateRow($userId, 'hospital');
                    if ($hospitalRow !== null) {
                        $hospitalSum = intval($hospitalRow['object_level_sum']);
                        $hospitalToolSum = intval($hospitalRow['tool_sum']);
                    } else {
                        // Missing cache: assume zero and log (cache should be kept up-to-date by object/tool changes)
                        $hospitalSum = 0;
                        $hospitalToolSum = 0;
                        Log::warning('population:births: missing aggregated cache for user ' . $userId . ' object_type hospital');
                    }

                    $hospitalSum = intval($hospitalSum);
                    $hospitalToolSum = intval($hospitalToolSum);

                    // Compute hospital effect and threshold
                    $hospitalEffect = $hospitalSum + ($hospitalToolSum / 10.0);
                    // Round half-up
                    $roundedEffect = intval(round($hospitalEffect, 0, PHP_ROUND_HALF_UP));
                    $thresholdLevel = 5 + $roundedEffect;

                    // Total current population for user (before births)
                    $totalPop = intval(DB::table('people')->where('user_id', $userId)->sum('count'));

                    if ($totalPop > 0) {
                        // Sum counts of people above threshold
                        $toRemoveTotal = intval(DB::table('people')
                            ->where('user_id', $userId)
                            ->where('level', '>', $thresholdLevel)
                            ->sum('count'));

                        if ($toRemoveTotal > 0) {
                            DB::beginTransaction();
                            try {
                                // Delete rows strictly above threshold and record removed count
                                DB::table('people')
                                    ->where('user_id', $userId)
                                    ->where('level', '>', $thresholdLevel)
                                    ->delete();

                                DB::commit();

                                // Record removed total for daily summary
                                $removedForUser = $toRemoveTotal;
                            } catch (\Exception $e) {
                                DB::rollBack();
                                Log::error('population:births: mortality processing failed for user ' . $userId . ': ' . $e->getMessage());
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('population:births: failed to compute hospital capacity for user ' . $userId . ': ' . $e->getMessage());
                }

                // --- Level up: increase level of available (not-occupied) people by 1 ---
                try {
                    // Initialize per-user removed counter (deaths) for daily summary
                    $removedForUser = 0;
                    // Active occupied workers (they are in production and must not be levelled)
                    $nowDt = date('Y-m-d H:i:s', time());
                    $occupiedActive = DB::table('occupied_workers')
                        ->where('user_id', $userId)
                        ->where('occupied_until', '>', $nowDt)
                        ->select('level', DB::raw('SUM(count) as total'))
                        ->groupBy('level')
                        ->pluck('total', 'level')
                        ->toArray();

                    // Level up only the free (not occupied) people. Lock rows for update to avoid races.
                    DB::beginTransaction();
                    try {
                        $peopleRows = DB::table('people')
                            ->where('user_id', $userId)
                            ->select('level', 'count')
                            ->orderBy('level')
                            ->lockForUpdate()
                            ->get();

                        foreach ($peopleRows as $row) {
                            $level = intval($row->level);
                            $count = intval($row->count);
                            $occupied = intval($occupiedActive[$level] ?? 0);
                            $available = max(0, $count - $occupied);
                            if ($available <= 0) continue;

                            $nextLevel = $level + 1;

                            // Decrement current level
                            $newCount = $count - $available;
                            if ($newCount <= 0) {
                                DB::table('people')
                                    ->where('user_id', $userId)
                                    ->where('level', $level)
                                    ->delete();
                            } else {
                                DB::table('people')
                                    ->where('user_id', $userId)
                                    ->where('level', $level)
                                    ->update(['count' => $newCount]);
                            }

                            // Increment next level (create if missing)
                            $existing = DB::table('people')
                                ->where('user_id', $userId)
                                ->where('level', $nextLevel)
                                ->lockForUpdate()
                                ->first();

                            if ($existing) {
                                DB::table('people')
                                    ->where('user_id', $userId)
                                    ->where('level', $nextLevel)
                                    ->update(['count' => intval($existing->count) + $available]);
                            } else {
                                DB::table('people')
                                    ->insert([
                                        'user_id' => $userId,
                                        'level' => $nextLevel,
                                        'count' => $available
                                    ]);
                            }
                        }

                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error('population:births: level-up failed for user ' . $userId . ': ' . $e->getMessage());
                    }
                } catch (\Exception $e) {
                    Log::error('population:births: failed to compute level-up for user ' . $userId . ': ' . $e->getMessage());
                }

                // Reconcile occupied workers (now after level-up) — this no longer creates notifications, only logs.
                try {
                    $this->reconcileOccupiedWorkers($userId);
                } catch (\Exception $e) {
                    Log::error('population:births: reconcileOccupiedWorkers failed for user ' . $userId . ': ' . $e->getMessage());
                }

                // --- Daily income from aggregated building levels ---
                try {
                    $incomeRows = DB::table('aggregated_object_levels')
                        ->where('user_id', $userId)
                        ->get();

                    $rates = config('game.daily_income_rates', []);
                    $incomeByType = [];
                    $incomeTotal = 0;
                    foreach ($incomeRows as $r) {
                        $otype = $r->object_type;
                        $rate = intval($rates[$otype] ?? 0);
                        if ($rate <= 0) continue;
                        $units = intval($r->total_level ?? (intval($r->object_level_sum ?? 0) + intval($r->tool_sum ?? 0)));
                        if ($units <= 0) continue;
                        $inc = intval($units * $rate);
                        if ($inc <= 0) continue;
                        $incomeByType[$otype] = ($incomeByType[$otype] ?? 0) + $inc;
                        $incomeTotal += $inc;
                    }

                    if ($incomeTotal > 0) {
                        // Credit the user balance
                        DB::table('users')->where('id', $userId)->increment('balance', $incomeTotal);

                        // Create aggregated income notification
                        try {
                            $title = __('notifications.daily_income_title');
                            $detailsParts = [];
                            foreach ($incomeByType as $otype => $amt) {
                                $detailsParts[] = ucfirst($otype) . ' ' . $amt;
                            }
                            $details = implode(', ', $detailsParts);
                            $message = __('notifications.daily_income_message', ['details' => $details, 'total' => $incomeTotal]);

                            Notification::create([
                                'user_id' => $userId,
                                'title' => $title,
                                'message' => $message,
                                'type' => 'success',
                                'is_read' => false,
                                'data' => json_encode(['incomes' => $incomeByType, 'total' => $incomeTotal])
                            ]);
                        } catch (\Exception $e) {
                            Log::error('population:births: failed to create income notification for user ' . $userId . ': ' . $e->getMessage());
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('population:births: failed to compute daily incomes for user ' . $userId . ': ' . $e->getMessage());
                }

                // --- Births: add people based on houses/tools ---
                $houses = intval($houseSums[$userId] ?? 0);
                $tools = intval($toolSums[$userId] ?? 0);
                $toAdd = $houses + $tools;

                if ($toAdd <= 0) {
                    continue;
                }

                // Efficiently upsert/increment level=1 people counts
                $person = Person::firstOrCreate([
                    'user_id' => $userId,
                    'level' => 1,
                ], [
                    'count' => 0,
                ]);

                // Use increment to avoid race conditions and extra selects
                $person->increment('count', $toAdd);

                $this->info("User {$userId}: +{$toAdd} people (houses: {$houses}, tools: {$tools})");

                // Create a single daily summary notification (born + died)
                try {
                    $born = $toAdd;
                    $died = intval($removedForUser ?? 0);

                    // Only create summary if anything changed
                    if ($born > 0 || $died > 0) {
                        $title = __('notifications.population_daily_summary_title');
                        $message = __('notifications.population_daily_summary_message', ['born' => $born, 'died' => $died]);
                        Notification::create([
                            'user_id' => $userId,
                            'title' => $title,
                            'message' => $message,
                            'type' => 'success',
                            'is_read' => false,
                            'data' => json_encode([
                                'born' => $born,
                                'died' => $died,
                                'houses' => $houses,
                                'tools' => $tools
                            ])
                        ]);
                    }
                } catch (\Exception $e) {
                    // log and continue
                    Log::error('population:births: failed to create daily summary notification for user ' . $userId . ': ' . $e->getMessage());
                }
            } catch (\Exception $e) {
                // Log and continue with other users
                Log::error('population:births error for user ' . $userId . ': ' . $e->getMessage(), ['exception' => $e]);
                $this->error('Error processing user ' . $userId . '. See log for details.');
                continue;
            }
        }

        $this->info('Population births finished.');

        return 0;
    }

    /**
     * Cancel productions when occupied workers at a given level exceed available people at that level.
     * Deletes occupied_workers rows for that level, clears ready_at on the related city_objects,
     * removes production_outputs and adjusts inventory.temp_count accordingly.
     */
    protected function reconcileOccupiedWorkers(int $userId)
    {
        // Build available people map by level
        $peopleByLevel = DB::table('people')
            ->where('user_id', $userId)
            ->select('level', DB::raw('SUM(count) as total'))
            ->groupBy('level')
            ->pluck('total', 'level')
            ->toArray();

        // Build occupied workers sum by level
        $occupiedSums = DB::table('occupied_workers')
            ->where('user_id', $userId)
            ->select('level', DB::raw('SUM(count) as total'))
            ->groupBy('level')
            ->pluck('total', 'level')
            ->toArray();

            foreach ($occupiedSums as $level => $assigned) {
                $available = intval($peopleByLevel[$level] ?? 0);
                if ($assigned <= $available) continue;

                // Too many assigned at this level -> DO NOT cancel productions or clear ready_at.
                // Per user request, do NOT create notifications. Log a warning for server operators.
                Log::warning('population:births: reconcileOccupiedWorkers over-assigned for user ' . $userId . ', level ' . $level . ': assigned=' . $assigned . ' available=' . $available);
            }
    }
}
