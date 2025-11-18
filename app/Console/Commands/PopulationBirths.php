<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;
use App\Models\User;
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

        // Get all users who have any aggregated buildings or people
        // We use aggregated_object_levels for everything (faster than querying city_objects)
        $allUsers = DB::table('aggregated_object_levels')
            ->select('user_id')
            ->distinct()
            ->pluck('user_id')
            ->toArray();

        // Also include users who have people (but maybe no buildings yet)
        $peopleUsers = DB::table('people')->select('user_id')->distinct()->pluck('user_id')->toArray();

        // Merge and get unique user IDs
        $userIds = array_unique(array_merge($allUsers, $peopleUsers));

        // Pre-load house aggregates for births calculation (only houses contribute to births)
        $houseAgg = DB::table('aggregated_object_levels')
            ->where('object_type', 'house')
            ->select('user_id', 'object_level_sum', 'tool_sum')
            ->get()
            ->keyBy('user_id')
            ->toArray();

        foreach ($userIds as $userId) {
            try {
                // Set user locale for translations
                $user = User::find($userId);
                if ($user && $user->locale) {
                    App::setLocale($user->locale);
                }

                // Initialize per-user removed counter (deaths) for daily summary
                $removedForUser = 0;

                // --- Mortality: kill people above threshold ---
                try {
                    // Mortality threshold = 5 + hospital_effect
                    // hospital_effect = sum(hospital.level) + sum(tools_in_hospitals) / 10 (rounded half-up)
                    // People table contains only FREE workers (occupied are in occupied_workers table)

                    // Use cached hospital aggregate
                    $hospitalRow = \App\Services\ObjectLevelService::getCachedAggregateRow($userId, 'hospital');
                    $hospitalSum = $hospitalRow ? intval($hospitalRow['object_level_sum']) : 0;
                    $hospitalToolSum = $hospitalRow ? intval($hospitalRow['tool_sum']) : 0;

                    $hospitalEffect = $hospitalSum + ($hospitalToolSum / 10.0);
                    $thresholdLevel = 5 + intval(round($hospitalEffect, 0, PHP_ROUND_HALF_UP));

                    // Delete all people above threshold (they die)
                    DB::beginTransaction();
                    try {
                        $deleted = DB::table('people')
                            ->where('user_id', $userId)
                            ->where('level', '>', $thresholdLevel)
                            ->lockForUpdate()
                            ->get();

                        foreach ($deleted as $row) {
                            $removedForUser += intval($row->count);
                        }

                        // Delete them
                        DB::table('people')
                            ->where('user_id', $userId)
                            ->where('level', '>', $thresholdLevel)
                            ->delete();

                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error('population:births: mortality processing failed for user ' . $userId . ': ' . $e->getMessage());
                    }
                } catch (\Exception $e) {
                    Log::error('population:births: mortality failed for user ' . $userId . ': ' . $e->getMessage());
                }

                // --- Level up: increase level of FREE people by 1 (requires food) ---
                try {
                    // Food consumption: each person needs food = their NEW level to level up
                    // Example: level 1 → 2 needs 2 food, level 10 → 11 needs 11 food
                    // People table contains only FREE workers (occupied are separate)
                    // Process in ascending level order so lower levels level up first if food is limited

                    DB::beginTransaction();
                    try {
                        // Get available food (tool_type_id = 3)
                        $foodInventory = DB::table('inventories')
                            ->where('user_id', $userId)
                            ->where('tool_type_id', 3)
                            ->lockForUpdate()
                            ->first();

                        $availableFood = $foodInventory ? intval($foodInventory->count) : 0;
                        $foodConsumed = 0;

                        // Get all people rows ordered by level (ascending) - lowest levels first
                        $peopleRows = DB::table('people')
                            ->where('user_id', $userId)
                            ->select('level', 'count')
                            ->orderBy('level', 'asc')
                            ->lockForUpdate()
                            ->get();

                        foreach ($peopleRows as $row) {
                            $level = intval($row->level);
                            $count = intval($row->count);
                            $nextLevel = $level + 1;
                            
                            // Calculate food needed for ALL people at this level to level up
                            $foodNeededPerPerson = $nextLevel; // new level
                            $totalFoodNeeded = $foodNeededPerPerson * $count;

                            // Check how many can level up with available food
                            $canLevelUp = 0;
                            if ($availableFood >= $totalFoodNeeded) {
                                // All can level up
                                $canLevelUp = $count;
                                $foodToConsume = $totalFoodNeeded;
                            } else {
                                // Only some can level up
                                $canLevelUp = intdiv($availableFood, $foodNeededPerPerson);
                                $foodToConsume = $canLevelUp * $foodNeededPerPerson;
                            }

                            if ($canLevelUp > 0) {
                                $stayAtCurrentLevel = $count - $canLevelUp;
                                
                                // Update current level count (those who don't level up)
                                if ($stayAtCurrentLevel > 0) {
                                    DB::table('people')
                                        ->where('user_id', $userId)
                                        ->where('level', $level)
                                        ->update(['count' => $stayAtCurrentLevel]);
                                } else {
                                    // All leveled up, delete row
                                    DB::table('people')
                                        ->where('user_id', $userId)
                                        ->where('level', $level)
                                        ->delete();
                                }

                                // Add to next level
                                $existing = DB::table('people')
                                    ->where('user_id', $userId)
                                    ->where('level', $nextLevel)
                                    ->lockForUpdate()
                                    ->first();

                                if ($existing) {
                                    DB::table('people')
                                        ->where('user_id', $userId)
                                        ->where('level', $nextLevel)
                                        ->update(['count' => intval($existing->count) + $canLevelUp]);
                                } else {
                                    DB::table('people')
                                        ->insert([
                                            'user_id' => $userId,
                                            'level' => $nextLevel,
                                            'count' => $canLevelUp
                                        ]);
                                }

                                // Consume food
                                $availableFood -= $foodToConsume;
                                $foodConsumed += $foodToConsume;
                            } else {
                                // No food left, no more level ups possible
                                break;
                            }
                        }

                        // Update food inventory
                        if ($foodConsumed > 0 && $foodInventory) {
                            DB::table('inventories')
                                ->where('user_id', $userId)
                                ->where('tool_type_id', 3)
                                ->update(['count' => max(0, intval($foodInventory->count) - $foodConsumed)]);
                        }

                        DB::commit();
                        
                        if ($foodConsumed > 0) {
                            $this->info("User {$userId}: consumed {$foodConsumed} food for level-ups");
                        }
                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error('population:births: level-up transaction failed for user ' . $userId . ': ' . $e->getMessage());
                    }
                } catch (\Exception $e) {
                    Log::error('population:births: level-up failed for user ' . $userId . ': ' . $e->getMessage());
                }

                // --- Daily income from ALL cached building levels ---
                try {
                    // Sum all cached total_level values for the user and credit that amount as balance.
                    // This intentionally ignores per-type rates and uses the cached total_level directly
                    // as the user requested: credit = SUM(total_level).
                    $incomeTotal = intval(DB::table('aggregated_object_levels')
                        ->where('user_id', $userId)
                        ->sum(DB::raw('COALESCE(total_level, (COALESCE(object_level_sum,0) + COALESCE(tool_sum,0)))')));

                    if ($incomeTotal > 0) {
                        DB::table('users')->where('id', $userId)->increment('balance', $incomeTotal);
                        // create a simple notification summarizing the income
                        try {
                            Notification::create([
                                'user_id' => $userId,
                                'title' => __('notifications.daily_income_title'),
                                'message' => __('notifications.daily_income_message', ['details' => '', 'total' => $incomeTotal]),
                                'type' => 'success',
                                'is_read' => false,
                                'data' => json_encode(['total' => $incomeTotal])
                            ]);
                        } catch (\Exception $e) {
                            Log::error('population:births: failed to create income notification for user ' . $userId . ': ' . $e->getMessage());
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('population:births: failed to compute daily incomes for user ' . $userId . ': ' . $e->getMessage());
                }

                // --- Births: add new level-1 people based on houses ---
                $birthCount = 0;
                $houseRow = $houseAgg[$userId] ?? null;
                if ($houseRow) {
                    $houses = intval($houseRow->object_level_sum ?? 0);
                    $tools = intval($houseRow->tool_sum ?? 0);
                    $toAdd = $houses + $tools;

                    if ($toAdd > 0) {
                        $birthCount = $toAdd;
                        
                        // Add new people at level 1
                        $person = Person::firstOrCreate([
                            'user_id' => $userId,
                            'level' => 1,
                        ], [
                            'count' => 0,
                        ]);

                        $person->increment('count', $toAdd);

                        $this->info("User {$userId}: +{$toAdd} people (houses: {$houses}, tools: {$tools})");
                    }
                }

                // --- Create notifications for deaths and births (separate) ---
                try {
                    // Death notification
                    if ($removedForUser > 0) {
                        Notification::create([
                            'user_id' => $userId,
                            'title' => __('notifications.population_decrease_title'),
                            'message' => __('notifications.population_decrease_message', ['count' => $removedForUser]),
                            'type' => 'warning',
                            'is_read' => false,
                            'data' => json_encode(['count' => $removedForUser])
                        ]);
                    }

                    // Birth notification
                    if ($birthCount > 0) {
                        Notification::create([
                            'user_id' => $userId,
                            'title' => __('notifications.population_increase_title'),
                            'message' => __('notifications.population_increase_message', ['count' => $birthCount]),
                            'type' => 'success',
                            'is_read' => false,
                            'data' => json_encode(['count' => $birthCount])
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('population:births: failed to create population notifications for user ' . $userId . ': ' . $e->getMessage());
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
}
