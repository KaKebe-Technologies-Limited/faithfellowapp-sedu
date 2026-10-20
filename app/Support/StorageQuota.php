<?php

namespace App\Support;

use App\Models\Church;
use Illuminate\Support\Facades\DB;

/**
 * Per-church media storage accounting (media.church_id).
 */
class StorageQuota
{
    public static function usedBytes(Church $church): int
    {
        // The audio on the church's own radio channel counts too (the table exists once the radio channels update has been applied).
        $radio = rescue(fn () => (int) DB::table('radio_tracks')->join('radio_stations', 'radio_stations.id', '=', 'radio_tracks.station_id')
            ->where('radio_stations.church_id', $church->id)->sum('radio_tracks.size'), 0, false);

        return (int) DB::table('media')->where('church_id', $church->id)->sum('size') + $radio;
    }

    public static function usedMb(Church $church): float
    {
        return round(self::usedBytes($church) / 1048576, 1);
    }

    public static function limitMb(Church $church): int
    {
        $planLimit = method_exists($church, 'planLimit') ? $church->planLimit('storage_mb') : null;

        return (int) ($planLimit ?? config('faithfellows.limits.storage_mb', 5120));
    }

    public static function canStore(Church $church, int $bytes): bool
    {
        return self::usedBytes($church) + $bytes <= self::limitMb($church) * 1048576;
    }

    public static function percentUsed(Church $church): float
    {
        return round(self::usedMb($church) / max(1, self::limitMb($church)) * 100, 1);
    }
}
