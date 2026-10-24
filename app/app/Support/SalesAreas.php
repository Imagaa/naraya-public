<?php

namespace App\Support;

use App\Models\Area;
use App\Models\BuyerProfile;
use App\Models\SalesProfile;

/**
 * Areas are made by the sales people, not by the admin (user, 2026-10-12): one area per sales person, named after
 * them, its circle the smallest one around their stores' pins (centre = the pins' average, radius = the farthest
 * pin + a small margin). Recomputed whenever a store's pin or sales person changes (BuyerProfile::booted) and when
 * a sales person is saved; the admin only looks at them.
 */
final class SalesAreas
{
    private const MARGIN_KM = 0.5;

    public static function refresh(?int $salesId): void
    {
        $profile = $salesId ? SalesProfile::with('user:id,name,account_deleted_at')->where('user_id', $salesId)->first() : null;
        if (! $profile?->user || $profile->user->account_deleted_at) { // a deleted sales account has no area
            return;
        }

        $pins = BuyerProfile::where('sales_user_id', $salesId)->whereNotNull('latitude')->whereNotNull('longitude')->get(['latitude', 'longitude']);
        $geo = ['lat' => null, 'lng' => null, 'radius_km' => null];
        if ($pins->isNotEmpty()) {
            $lat = $pins->avg(fn ($p) => (float) $p->latitude);
            $lng = $pins->avg(fn ($p) => (float) $p->longitude);
            $far = $pins->max(fn ($p) => Geo::distanceKm($lat, $lng, (float) $p->latitude, (float) $p->longitude));
            $geo = ['lat' => $lat, 'lng' => $lng, 'radius_km' => max(1, (int) ceil($far + self::MARGIN_KM))];
        }

        $area = $profile->area_id ? Area::find($profile->area_id) : null;
        $area ??= Area::create(['name' => 'Area '.$profile->user->name]);
        $area->update(['name' => 'Area '.$profile->user->name] + $geo);
        if ($profile->area_id !== $area->id) {
            $profile->forceFill(['area_id' => $area->id])->saveQuietly();
        }
        // plain query: no model events, so this never loops back into BuyerProfile::booted
        BuyerProfile::where('sales_user_id', $salesId)->where(fn ($q) => $q->whereNull('area_id')->orWhere('area_id', '!=', $area->id))
            ->update(['area_id' => $area->id]);
    }

    /** Every sales person's area, then drop the areas no sales person owns (the old admin-made ones). */
    public static function refreshAll(): void
    {
        SalesProfile::pluck('user_id')->each(fn ($id) => self::refresh($id));
        Area::whereNotIn('id', SalesProfile::whereNotNull('area_id')->pluck('area_id'))->delete();
    }
}
