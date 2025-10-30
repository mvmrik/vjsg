<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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

        // Sum of house levels per user (only built/upgraded houses with level > 0 contribute).
        $houseSums = DB::table('city_objects')
            ->select('user_id', DB::raw('SUM(level) as house_sum'))
            ->where('object_type', 'house')
            ->groupBy('user_id')
            ->pluck('house_sum', 'user_id')
            ->toArray();

        // Sum of tool levels for tools that belong to houses, per user
        // Sum of tool levels for tools that belong to houses, per user.
        $toolSums = DB::table('tools')
            ->join('city_objects', 'tools.object_id', '=', 'city_objects.id')
            ->where('city_objects.object_type', 'house')
            ->select('city_objects.user_id as user_id', DB::raw('SUM(tools.level) as tools_sum'))
            ->groupBy('city_objects.user_id')
            ->pluck('tools_sum', 'user_id')
            ->toArray();

    // Also include users who currently have people and users who have hospitals
    $peopleUsers = DB::table('people')->select('user_id')->distinct()->pluck('user_id')->toArray();
    $hospitalUsers = DB::table('city_objects')->where('object_type', 'hospital')->select('user_id')->distinct()->pluck('user_id')->toArray();

    // Merge keys (user ids) from houses, tools, people and hospitals
    $userIds = array_unique(array_merge(array_keys($houseSums), array_keys($toolSums), $peopleUsers, $hospitalUsers));

        foreach ($userIds as $userId) {
            try {
                // --- Mortality: account for hospitals ---
                try {
                    // Sum hospital levels per user (only built hospitals with level > 0 contribute)
                    $hospitalSum = DB::table('city_objects')
                        ->where('object_type', 'hospital')
                        ->where('user_id', $userId)
                        ->sum('level');

                    // Sum tool levels for tools attached to hospitals
                    $hospitalToolSum = DB::table('tools')
                        ->join('city_objects', 'tools.object_id', '=', 'city_objects.id')
                        ->where('city_objects.object_type', 'hospital')
                        ->where('city_objects.user_id', $userId)
                        ->sum('tools.level');

                    $hospitalCapacity = intval($hospitalSum) + intval($hospitalToolSum);

                    // Total current population for user (before births)
                    $totalPop = intval(DB::table('people')->where('user_id', $userId)->sum('count'));

                    if ($hospitalCapacity < $totalPop) {
                        $deficit = $totalPop - $hospitalCapacity;

                        // Cap mortality so it can never reach 100% of the population.
                        // Maximum removable people is 80% of the total population.
                        $maxRemovable = intval(floor($totalPop * 0.8));
                        // Determine how many to remove (can't exceed deficit nor maxRemovable)
                        $toRemoveTotal = min($deficit, $maxRemovable);

                        // Keep original (pre-scale) for bounds
                        $originalToRemove = $toRemoveTotal;

                        // Scale mortality down so hospitals are more effective: divide removals by 10
                        // (this implements: 10x fewer deaths; e.g. previous 80 -> now 8)
                        $toRemoveTotal = intval(floor($toRemoveTotal / 10));

                        // Enforce a minimum mortality floor of 5% of population (rounded up)
                        // This guarantees some mortality when there is a deficit, but still
                        // doesn't exceed the original computed removals.
                        $minPercent = 0.05; // 5%
                        $minRemovable = intval(ceil($totalPop * $minPercent));
                        if ($minRemovable < 1) $minRemovable = 1;
                        if ($originalToRemove > 0 && $toRemoveTotal < $minRemovable) {
                            // Respect original bound (can't remove more than originalToRemove)
                            $toRemoveTotal = min($minRemovable, $originalToRemove);
                        }

                        // If capped removal is zero, skip deletion (we don't remove everybody)
                        if ($toRemoveTotal > 0) {
                            // Remove people from highest levels first (descending level)
                            DB::beginTransaction();
                            try {
                                $levels = Person::where('user_id', $userId)
                                    ->where('count', '>', 0)
                                    ->orderBy('level', 'desc')
                                    ->get();

                                $remaining = $toRemoveTotal;
                                foreach ($levels as $lvlRow) {
                                    if ($remaining <= 0) break;
                                    $available = intval($lvlRow->count);
                                    if ($available <= 0) continue;
                                    $take = min($available, $remaining);
                                    // decrement count; if becomes zero, delete the row to avoid empty-level rows
                                    $newCount = $available - $take;
                                    if ($newCount <= 0) {
                                        $lvlRow->delete();
                                    } else {
                                        $lvlRow->count = $newCount;
                                        $lvlRow->save();
                                    }
                                    $remaining -= $take;
                                }

                                DB::commit();

                                // Create notification about deaths
                                $title = __('notifications.population_decrease_title');
                                $message = __('notifications.population_decrease_message', ['count' => $toRemoveTotal]);
                                Notification::create([
                                    'user_id' => $userId,
                                    'title' => $title,
                                    'message' => $message,
                                    'type' => 'danger',
                                    'is_read' => false,
                                    'data' => json_encode([
                                        'removed' => $toRemoveTotal,
                                        'hospital_capacity' => $hospitalCapacity,
                                        'total_before' => $totalPop,
                                        'deficit' => $deficit,
                                        'max_removable' => $maxRemovable
                                    ])
                                ]);
                            } catch (\Exception $e) {
                                DB::rollBack();
                                \Log::error('population:births: mortality processing failed for user ' . $userId . ': ' . $e->getMessage());
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('population:births: failed to compute hospital capacity for user ' . $userId . ': ' . $e->getMessage());
                }

                // Reconcile occupied workers: if assigned workers at a level exceed available people at that level,
                // cancel affected productions (delete occupied records, clear ready_at, remove production outputs and adjust temp_count).
                try {
                    $this->reconcileOccupiedWorkers($userId);
                } catch (\Exception $e) {
                    \Log::error('population:births: reconcileOccupiedWorkers failed for user ' . $userId . ': ' . $e->getMessage());
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

                // Create a notification for the user about population increase
                try {
                    $title = __('notifications.population_increase_title');
                    $message = __('notifications.population_increase_message', ['count' => $toAdd]);
                    Notification::create([
                        'user_id' => $userId,
                        'title' => $title,
                        'message' => $message,
                        'type' => 'success',
                        'is_read' => false,
                        'data' => json_encode([
                            'added' => $toAdd,
                            'houses' => $houses,
                            'tools' => $tools
                        ])
                    ]);
                } catch (\Exception $e) {
                    // log and continue
                    \Log::error('population:births: failed to create notification for user ' . $userId . ': ' . $e->getMessage());
                }
            } catch (\Exception $e) {
                // Log and continue with other users
                \Log::error('population:births error for user ' . $userId . ': ' . $e->getMessage(), ['exception' => $e]);
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
            // Instead, notify the user and log the condition. Occupied workers remain as-is.
            try {
                $title = __('notifications.production_overassigned_title');
                $message = __('notifications.production_overassigned_message', ['assigned' => $assigned, 'available' => $available, 'level' => $level]);
                Notification::create([
                    'user_id' => $userId,
                    'title' => $title,
                    'message' => $message,
                    'type' => 'warning',
                    'is_read' => false,
                    'data' => json_encode([
                        'assigned' => $assigned,
                        'available' => $available,
                        'level' => $level
                    ])
                ]);

                \Log::warning('population:births: reconcileOccupiedWorkers over-assigned for user ' . $userId . ', level ' . $level . ': assigned=' . $assigned . ' available=' . $available);
            } catch (\Exception $e) {
                \Log::error('population:births: reconcileOccupiedWorkers notify failed for user ' . $userId . ', level ' . $level . ': ' . $e->getMessage());
            }
        }
    }
}
