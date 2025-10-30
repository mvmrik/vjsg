<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class MarketService
{
    // Base fee settings (bps)
    const BASE_FEE_BPS = 1000; // 10%
    const BANK_REDUCTION_BPS = 100; // 1% per bank tool
    const MIN_FEE_BPS = 100; // 1% minimum

    /**
     * Recompute and persist user's fee_bps based on number of bank objects and tools in them.
     */
    public static function recomputeUserFee(int $userId)
    {
        // Count bank levels
        $bankLevelSum = intval(DB::table('city_objects')
            ->where('user_id', $userId)
            ->where('object_type', 'bank')
            ->sum('level'));

        // Count tools attached to banks (their levels)
        $bankToolSum = intval(DB::table('tools')
            ->join('city_objects', 'tools.object_id', '=', 'city_objects.id')
            ->where('city_objects.user_id', $userId)
            ->where('city_objects.object_type', 'bank')
            ->sum('tools.level'));

        $totalBankUnits = $bankLevelSum + $bankToolSum;

        $reduction = $totalBankUnits * self::BANK_REDUCTION_BPS;
        $fee = max(self::MIN_FEE_BPS, self::BASE_FEE_BPS - $reduction);

        DB::table('users')->where('id', $userId)->update(['fee_bps' => intval($fee)]);
        return intval($fee);
    }
}
