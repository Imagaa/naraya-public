<?php

namespace App\Jobs;

use App\Enums\PaymentStatus;
use App\Models\MidtransNotification;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessMidtransNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $notificationId)
    {
    }

    public function handle(): void
    {
        $notification = MidtransNotification::find($this->notificationId);

        if (! $notification || ! $notification->order_id) {
            $notification?->update(['processed_at' => now()]);

            return;
        }

        $order = Order::find($notification->order_id);

        if (! $order) {
            $notification->update(['processed_at' => now()]);

            return;
        }

        $payload = json_decode($notification->raw_payload, true);
        $newStatus = $this->mapStatus($payload['transaction_status'] ?? '');

        if ($order->payment_status === PaymentStatus::Pending) {
            $order->update([
                'payment_status' => $newStatus,
                'midtrans_raw_status' => $payload['transaction_status'] ?? $order->midtrans_raw_status,
                'midtrans_transaction_id' => $payload['transaction_id'] ?? $order->midtrans_transaction_id,
            ]);
        } elseif ($newStatus === PaymentStatus::Paid && $order->payment_status === PaymentStatus::Failed) {
            // Settlement arrived after we'd already closed this out as Failed/Cancelled —
            // money moved, don't silently reopen it (PRD 3.2.4). Flag for Admin.
            $order->update(['needs_payment_review' => true]);
        }
        // else: order already Paid, or notification doesn't represent forward progress — ignore.

        $notification->update(['processed_at' => now()]);
    }

    private function mapStatus(string $transactionStatus): PaymentStatus
    {
        return match ($transactionStatus) {
            'settlement', 'capture' => PaymentStatus::Paid,
            'deny', 'cancel', 'expire' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };
    }
}
