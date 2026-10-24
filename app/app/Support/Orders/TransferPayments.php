<?php

namespace App\Support\Orders;

use App\Enums\CancelReason;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\AdminNotice;
use App\Services\OrderCanceller;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BNI transfer on a marketplace order: the store uploads a
 * proof within 30 minutes of checkout, admin verifies or rejects it (a rejection restarts the 30 minutes),
 * and an order that never got a proof cancels itself.
 */
final class TransferPayments
{
    public const WINDOW_MINUTES = 30;

    public function upload(Order $order, UploadedFile $photo): void
    {
        $path = $photo->store('order-payments', 's3');

        $this->locked($order, function (Order $o) use ($path) {
            if (! $this->awaitingProof($o)) {
                throw ValidationException::withMessages(['proof' => 'Pesanan ini tidak sedang menunggu bukti transfer.']);
            }
            $o->update(['payment_proof_path' => $path, 'payment_proof_uploaded_at' => now(), 'payment_expires_at' => null]);
        });

        AdminNotice::toAdmins('Bukti transfer pesanan #'.$order->id, 'Rp '.number_format($order->total, 0, ',', '.').' dari '.$order->buyer?->name.' — periksa lalu verifikasi.',
            route('admin.orders.show', $order, absolute: false), 'landmark', 'warning', urgent: true);
    }

    public function verify(Order $order, User $admin): void
    {
        $this->locked($order, function (Order $o) use ($admin) {
            if (! $this->awaitingReview($o)) {
                throw ValidationException::withMessages(['payment' => 'Tidak ada bukti transfer yang menunggu verifikasi.']);
            }
            $o->update(['payment_status' => PaymentStatus::Paid, 'payment_reviewed_at' => now(), 'payment_reviewed_by' => $admin->id, 'payment_reject_reason' => null]);
        });
    }

    public function reject(Order $order, User $admin, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['rejectReason' => 'Tuliskan alasan penolakan.']);
        }

        $this->locked($order, function (Order $o) use ($admin, $reason) {
            if (! $this->awaitingReview($o)) {
                throw ValidationException::withMessages(['payment' => 'Tidak ada bukti transfer yang menunggu verifikasi.']);
            }
            // back to "Menunggu bukti bayar", with a fresh 30 minutes
            $o->update(['payment_proof_path' => null, 'payment_proof_uploaded_at' => null, 'payment_reviewed_at' => now(),
                'payment_reviewed_by' => $admin->id, 'payment_reject_reason' => $reason, 'payment_expires_at' => now()->addMinutes(self::WINDOW_MINUTES)]);
        });
    }

    /** Cancels every transfer order whose 30 minutes ran out without a proof. @return int how many */
    public function expireDue(): int
    {
        $due = Order::where('payment_method', 'transfer')->where('status', OrderStatus::New)->where('payment_status', PaymentStatus::Pending)
            ->whereNull('payment_proof_path')->where('payment_expires_at', '<', now())->pluck('id');

        $cancelled = 0;
        foreach ($due as $id) {
            $cancelled += (int) DB::transaction(function () use ($id) {
                $o = Order::lockForUpdate()->find($id);
                if (! $o || ! $this->awaitingProof($o) || ! $o->payment_expires_at?->isPast()) {
                    return false; // a proof arrived meanwhile
                }
                app(OrderCanceller::class)->cancel($o, CancelReason::PembayaranBermasalah, 'Bukti transfer tidak diunggah dalam 30 menit', null);
                AdminNotice::toAdmins('Pesanan batal otomatis', "#{$o->id} {$o->buyer?->name} — bukti transfer tidak diunggah dalam 30 menit", route('admin.orders.show', $o, absolute: false), 'circle-x', 'warning');

                return true;
            });
        }

        return $cancelled;
    }

    private function awaitingProof(Order $o): bool
    {
        return $o->payment_method === 'transfer' && $o->status === OrderStatus::New
            && $o->payment_status === PaymentStatus::Pending && $o->payment_proof_path === null;
    }

    private function awaitingReview(Order $o): bool
    {
        return $o->payment_method === 'transfer' && $o->status === OrderStatus::New
            && $o->payment_status === PaymentStatus::Pending && $o->payment_proof_path !== null;
    }

    private function locked(Order $order, callable $change): void
    {
        DB::transaction(fn () => $change(Order::lockForUpdate()->findOrFail($order->id)));
        $order->refresh();
    }
}
