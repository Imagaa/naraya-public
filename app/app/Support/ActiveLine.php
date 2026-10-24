<?php

namespace App\Support;

use App\Enums\BusinessLine;

/**
 * The business line a portal user is working in.
 * Kept in the session; every staff portal has its own host-only session, so
 * admin and sales each remember their own choice.
 */
final class ActiveLine
{
    public const KEY = 'line';

    public static function current(): BusinessLine
    {
        $line = BusinessLine::tryFrom((string) session(self::KEY)) ?? BusinessLine::Naraya;

        return self::isOpen($line) ? $line : BusinessLine::Toys;
    }

    /** While grosir is closed (naraya.grosir_open) the marketplace and the sales app only have Toys; admin keeps both. */
    public static function isOpen(BusinessLine $line): bool
    {
        return $line !== BusinessLine::Naraya || config('naraya.grosir_open')
            || ! in_array(request()->getHost(), [config('app.domain'), config('app.sales_domain')], true);
    }

    /** The line whose Setoran carries investment cash taken by sales: Naraya, or Toys while grosir is closed. */
    public static function investmentCashLine(): BusinessLine
    {
        return config('naraya.grosir_open') ? BusinessLine::Naraya : BusinessLine::Toys;
    }

    public static function set(BusinessLine $line): void
    {
        session([self::KEY => $line->value]);
    }
}
