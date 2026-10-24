<?php

namespace App\Support\Orders;

use App\Support\BusinessDay;
use Illuminate\Support\Carbon;

/**
 * An order placed before 21.00 WIB goes out the next day.
 * The rule is never shown; later orders get no date and follow the sales person's round.
 */
final class DeliveryEstimate
{
    public const CUTOFF_HOUR = 21;

    /** @return Carbon|null UTC: the next WIB day at 12.00, or null from 21.00 WIB on */
    public static function for(Carbon $placedAt): ?Carbon
    {
        $wib = $placedAt->copy()->timezone(BusinessDay::zone());

        return $wib->hour < self::CUTOFF_HOUR ? $wib->addDay()->setTime(12, 0)->utc() : null;
    }
}
