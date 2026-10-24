<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Order;
use App\Models\User;

class SalesAssigner
{
    /**
     * At most this many orders assigned to one sales within the rolling
     * window before further orders in their area overflow to the next
     * nearest area — keeps one sales from getting buried by a local spike
     * while goods are still fresh off the shelf.
     */
    private const MAX_ORDERS_PER_WINDOW = 2;

    private const WINDOW_HOURS = 3;

    /**
     * Nearest area with an active sales under the load limit, walking
     * outward until one is found. If every nearby area is over capacity,
     * falls back to the nearest one anyway — an order should never go
     * unassigned just because everyone happens to be busy right now.
     */
    public function assign(Order $order): ?User
    {
        if ($order->delivery_lat === null || $order->delivery_lng === null) {
            return null;
        }

        $areasByDistance = Area::query()
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->get()
            ->sortBy(fn (Area $area) => $area->distanceFromKm($order->delivery_lat, $order->delivery_lng))
            ->values();

        $nearestAvailableSales = null;

        foreach ($areasByDistance as $area) {
            $salesProfile = $area->activeSalesProfile();

            if (! $salesProfile) {
                continue;
            }

            $nearestAvailableSales ??= $salesProfile->user;

            $recentlyAssignedCount = Order::where('sales_id', $salesProfile->user_id)
                ->where('sales_assigned_at', '>=', now()->subHours(self::WINDOW_HOURS))
                ->count();

            if ($recentlyAssignedCount < self::MAX_ORDERS_PER_WINDOW) {
                return $salesProfile->user;
            }
        }

        return $nearestAvailableSales;
    }
}
