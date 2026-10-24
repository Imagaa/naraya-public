<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Midtrans\MidtransClient;
use Illuminate\Console\Command;

class ReconcilePendingPayments extends Command
{
    protected $signature = 'payments:reconcile';

    protected $description = 'Actively check Midtrans status for pending payments past their expiry window (PRD 3.2.3)';

    public function handle(MidtransClient $client): int
    {
        $orders = Order::where('payment_status', PaymentStatus::Pending)
            ->whereNotNull('payment_expires_at')
            ->where('payment_expires_at', '<', now())
            ->get();

        foreach ($orders as $order) {
            try {
                $result = $client->checkStatus($order->midtrans_order_id);
            } catch (\Throwable $e) {
                $this->warn("Could not check status for order #{$order->id}, will retry next run.");

                continue;
            }

            $newStatus = match ($result['transaction_status'] ?? '') {
                'settlement', 'capture' => PaymentStatus::Paid,
                default => PaymentStatus::Failed,
            };

            $order->update([
                'payment_status' => $newStatus,
                'midtrans_raw_status' => $result['transaction_status'] ?? $order->midtrans_raw_status,
            ]);
        }

        return self::SUCCESS;
    }
}
