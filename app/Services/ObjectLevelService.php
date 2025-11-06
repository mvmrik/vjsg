<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ObjectLevelService
{
    /**
     * Compute aggregated "level" for all objects of a given type owned by a user.
     * Aggregation = SUM(city_objects.level) + SUM(tools.level|tools.durability if present)
     *
     * This helper is generic so other systems can ask for the combined level of any object type.
     *
     * @param int $userId
     * @param string $objectType
     * @return int
     */
    public static function aggregateLevel(int $userId, string $objectType): int
    {
        // Sum object levels (city_objects.level)
        $objectLevelSum = intval(DB::table('city_objects')
            ->where('user_id', $userId)
            ->where('object_type', $objectType)
            ->sum('level'));

        // Sum tool contributions separately (prefer tools.level if exists else tools.durability)
        $schema = DB::getSchemaBuilder();
        $toolLevelCol = $schema->hasColumn('tools', 'level') ? 'tools.level' : null;
        $toolDurabilityCol = $schema->hasColumn('tools', 'durability') ? 'tools.durability' : null;

        $toolSum = 0;
        if ($toolLevelCol) {
            $toolSum = intval(DB::table('tools')
                ->join('city_objects', 'tools.object_id', '=', 'city_objects.id')
                ->where('city_objects.user_id', $userId)
                ->where('city_objects.object_type', $objectType)
                ->sum(DB::raw($toolLevelCol)));
        } elseif ($toolDurabilityCol) {
            // Durability is 0..100; treat it as contribution directly (same units as level)
            $toolSum = intval(DB::table('tools')
                ->join('city_objects', 'tools.object_id', '=', 'city_objects.id')
                ->where('city_objects.user_id', $userId)
                ->where('city_objects.object_type', $objectType)
                ->sum(DB::raw($toolDurabilityCol)));
        }

        return $objectLevelSum + $toolSum;
    }

    /**
     * Read cached aggregate row (object_level_sum, tool_sum, total_level) or null if not present
     * @return array|null ['object_level_sum'=>int, 'tool_sum'=>int, 'total_level'=>int]
     */
    public static function getCachedAggregateRow(int $userId, string $objectType): ?array
    {
        $row = DB::table('aggregated_object_levels')
            ->where('user_id', $userId)
            ->where('object_type', $objectType)
            ->first();
        if (!$row) return null;
        return [
            'object_level_sum' => intval($row->object_level_sum ?? 0),
            'tool_sum' => intval($row->tool_sum ?? 0),
            'total_level' => intval($row->total_level ?? (intval($row->object_level_sum ?? 0) + intval($row->tool_sum ?? 0)))
        ];
    }

    /**
     * Read cached aggregate from DB. Returns null if not present.
     */
    public static function getCachedAggregate(int $userId, string $objectType): ?int
    {
        $row = DB::table('aggregated_object_levels')
            ->where('user_id', $userId)
            ->where('object_type', $objectType)
            ->first();
        return $row ? intval($row->total_level) : null;
    }

    /**
     * Store aggregate level in cache table (insert or update)
     */
    public static function storeAggregate(int $userId, string $objectType, int $totalLevel): void
    {
        // preserve backward compatible total_level, but try to compute parts as well
        DB::table('aggregated_object_levels')->updateOrInsert(
            ['user_id' => $userId, 'object_type' => $objectType],
            ['total_level' => intval($totalLevel), 'updated_at' => now()]
        );
    }

    /**
     * Recompute aggregate from current DB state and store it. Returns the computed total.
     */
    public static function recomputeAndStore(int $userId, string $objectType): int
    {
        // Compute object level sum and tool sum separately
        $objectLevelSum = intval(DB::table('city_objects')
            ->where('user_id', $userId)
            ->where('object_type', $objectType)
            ->sum('level'));

        $schema = DB::getSchemaBuilder();
        $toolLevelCol = $schema->hasColumn('tools', 'level') ? 'tools.level' : null;
        $toolDurabilityCol = $schema->hasColumn('tools', 'durability') ? 'tools.durability' : null;

        $toolSum = 0;
        if ($toolLevelCol) {
            $toolSum = intval(DB::table('tools')
                ->join('city_objects', 'tools.object_id', '=', 'city_objects.id')
                ->where('city_objects.user_id', $userId)
                ->where('city_objects.object_type', $objectType)
                ->sum(DB::raw($toolLevelCol)));
        } elseif ($toolDurabilityCol) {
            $toolSum = intval(DB::table('tools')
                ->join('city_objects', 'tools.object_id', '=', 'city_objects.id')
                ->where('city_objects.user_id', $userId)
                ->where('city_objects.object_type', $objectType)
                ->sum(DB::raw($toolDurabilityCol)));
        }

        $total = $objectLevelSum + $toolSum;
        DB::table('aggregated_object_levels')->updateOrInsert(
            ['user_id' => $userId, 'object_type' => $objectType],
            ['object_level_sum' => $objectLevelSum, 'tool_sum' => $toolSum, 'total_level' => $total, 'updated_at' => now()]
        );

        // If this is a bank aggregate, update user's market fee immediately so fee does not wait for cron.
        if ($objectType === 'bank') {
            try {
                \App\Services\MarketService::recomputeUserFee($userId);
            } catch (\Exception $e) {
                // Log and continue — don't break aggregate update
                try { \Illuminate\Support\Facades\Log::error('ObjectLevelService::recomputeAndStore: failed to recompute user fee for user ' . $userId . ': ' . $e->getMessage()); } catch (\Exception $_) {}
            }
        }

        return $total;
    }
}
