<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Timestamps are stored in UTC; business days (and what admin reads as
 * "today", "this month", a report date) are WIB, config app.business_timezone.
 */
final class BusinessDay
{
    public static function zone(): string
    {
        return config('app.business_timezone');
    }

    public static function now(): Carbon
    {
        return now(self::zone());
    }

    /** Today's WIB date (midnight WIB). */
    public static function today(): Carbon
    {
        return self::now()->startOfDay();
    }

    /**
     * A WIB calendar day as UTC bounds, for querying UTC timestamps.
     * A 06:30 WIB check-in is 23:30 UTC "yesterday".
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function boundsFor(CarbonInterface|string $date): array
    {
        $day = $date instanceof CarbonInterface ? $date->format('Y-m-d') : Carbon::parse($date)->format('Y-m-d');
        $start = Carbon::parse($day, self::zone())->startOfDay();

        return [$start->copy()->utc(), $start->copy()->endOfDay()->utc()];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public static function bounds(): array
    {
        return self::boundsFor(self::today());
    }
}
