<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\SalesNote;
use Carbon\Carbon;

class SalesTarget
{
    /**
     * A sales person's sales within the given month (defaults to the current month):
     * their notes of every line, plus completed legacy marketplace orders.
     */
    public static function realized(int $salesId, ?Carbon $month = null): int
    {
        $month ??= now();
        $range = [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];

        return (int) Order::where('sales_id', $salesId)
            ->where('status', OrderStatus::Completed)
            ->whereBetween('completed_at', $range)
            ->sum('total')
            + (int) SalesNote::where('sales_id', $salesId)->whereBetween('created_at', $range)->sum('total');
    }
}
