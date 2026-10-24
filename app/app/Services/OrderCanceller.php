<?php

namespace App\Services;

use App\Enums\CancelReason;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Exceptions\OrderNotCancellableException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Admin cancel. Only before the goods leave the
 * warehouse: after the gudang handover they're physically out, and the
 * reject-on-delivery flow (restock or write-off) is the honest path.
 */
class OrderCanceller
{
    public static function canCancel(Order $order): bool
    {
        return $order->status === OrderStatus::New
            || ($order->status === OrderStatus::Processing && $order->gudang_handover_at === null);
    }

    /** $by null = the system (an unpaid transfer that ran out of time). */
    public function cancel(Order $order, CancelReason $reason, ?string $note, ?User $by): void
    {
        $note = trim((string) $note) ?: null;

        if ($reason === CancelReason::Lainnya && $note === null) {
            throw new OrderNotCancellableException('Tulis catatan untuk alasan "Lainnya".');
        }

        DB::transaction(function () use ($order, $reason, $note, $by) {
            // Re-read under lock so two admins on stale pages can't both cancel (and restock twice).
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! self::canCancel($order)) {
                throw new OrderNotCancellableException(
                    in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Completed, OrderStatus::RejectedByUser], true)
                        ? 'Pesanan ini sudah dibatalkan atau ditutup.'
                        : 'Barang sudah keluar gudang — gunakan alur penolakan saat diterima.'
                );
            }

            // Stock is deducted at confirmation, so only a Processing order has any to give back.
            if ($order->status === OrderStatus::Processing) {
                foreach ($order->items as $item) {
                    $item->product?->adjustStock($item->quantity * $item->product->ppu(), StockMovementType::Correction, $by, "Batal Order #{$order->id} — {$reason->label()}");
                }
            }

            if ($order->credit_applied > 0) {
                $order->buyer->buyerProfile?->increment('credit_balance', $order->credit_applied);
            }

            $order->update([
                'status' => OrderStatus::Cancelled,
                'cancel_reason' => $reason,
                'cancel_note' => $note,
                'cancelled_at' => now(),
                'cancelled_by' => $by?->id,
                // Money came in online; it goes back by hand, tracked by the "refunds" queue.
                'refund_status' => $order->payment_method !== 'cash' && $order->payment_status === PaymentStatus::Paid ? 'pending' : null,
            ]);
        });
    }
}
