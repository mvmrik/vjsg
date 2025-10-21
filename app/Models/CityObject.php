<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CityObject extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'parcel_id',
        'object_type',
        'level',
        'x',
        'y',
        'ready_at',
        'build_seconds'
    ];

    protected $casts = [
        // ready_at is now an integer timestamp, no casting needed
    ];

    // No $dates array needed anymore

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parcel()
    {
        return $this->belongsTo(Parcel::class);
    }

    public function tools()
    {
        return $this->hasMany(Tool::class, 'object_id');
    }

    /**
     * Calculate build seconds for an object given baseSeconds and workers.
     * Uses the 'next level' logic: nextLevel = max(1, currentLevel + 1).
     * reductionMinutes = (workerLevel * workerCount) - 1 (min 0)
     * finalSeconds = max(60, baseSeconds * nextLevel - reductionMinutes*60)
     *
     * @param int $baseSeconds
     * @param int $currentLevel
     * @param int $workerLevel
     * @param int $workerCount
     * @return int
     */
    public static function calculateBuildSeconds(int $baseSeconds, int $currentLevel = 0, int $workerLevel = 0, int $workerCount = 0): int
    {
        $nextLevel = max(1, intval($currentLevel) + 1);
        $levelAdjustedSeconds = intval($baseSeconds) * $nextLevel;
        $reductionMinutes = ($workerLevel * $workerCount) - 1;
        if ($reductionMinutes < 0) {
            $reductionMinutes = 0;
        }
        $reductionSeconds = intval($reductionMinutes) * 60;
        return max(60, $levelAdjustedSeconds - $reductionSeconds);
    }
}
