<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Console\Command;

class AutoCompleteUnconfirmedDeliveries extends Command
{
    protected $signature = 'orders:auto-complete-deliveries';

    protected $description = 'Auto-complete Shipped orders the buyer never confirmed within 3 days of Sales delivering them';

    public function handle(): int
    {
        $orders = Order::where('status', OrderStatus::Shipped)
            ->whereNotNull('sales_delivered_at')
            ->where('sales_delivered_at', '<=', now()->subDays(3))
            ->whereNull('buyer_confirmed_at')
            ->get();

        foreach ($orders as $order) {
            $order->update([
                'buyer_confirmed_at' => now(),
                'status' => OrderStatus::Completed,
                'completed_at' => now(),
            ]);
        }

        return self::SUCCESS;
    }
}
