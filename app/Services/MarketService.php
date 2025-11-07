<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class MarketService
{
    // Base fee settings (bps)
    // Base fee settings (bps) - base 100% (10000 bps). Bank units reduce fee by BANK_REDUCTION_BPS each.
    const BASE_FEE_BPS = 10000; // 100%
    const BANK_REDUCTION_BPS = 100; // 1% per bank unit
    const MIN_FEE_BPS = 100; // 1% minimum

    /**
     * Recompute and persist user's fee_bps based on number of bank objects and tools in them.
     */
    public static function recomputeUserFee(int $userId)
    {
        // Use cached aggregate when available, otherwise recompute and store it.
        // Strictly use cached aggregate. If cache is missing, treat as zero.
        // Cache should be maintained by object/tool updates; missing cache indicates a data issue.
        $cachedRow = \App\Services\ObjectLevelService::getCachedAggregateRow($userId, 'bank');
        $totalBankUnits = $cachedRow ? intval($cachedRow['total_level']) : 0;

        // Reduction: each unit reduces fee by BANK_REDUCTION_BPS (bps)
        $reduction = $totalBankUnits * self::BANK_REDUCTION_BPS;
        $fee = max(self::MIN_FEE_BPS, self::BASE_FEE_BPS - $reduction);

        DB::table('users')->where('id', $userId)->update(['fee_bps' => intval($fee)]);
        return intval($fee);
    }
}
