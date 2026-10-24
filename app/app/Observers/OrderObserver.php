<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Notifications\AdminNotice;
use App\Notifications\BuyerNotice;
use App\Notifications\SalesNotice;

class OrderObserver
{
    /** Cash book "penjualan online" counts an order on the day its payment succeeded, whichever path set it. */
    public function saving(Order $order): void
    {
        if ($order->payment_status === PaymentStatus::Paid && $order->paid_at === null && ($order->isDirty('payment_status') || ! $order->exists)) {
            $order->paid_at = now();
        }
    }

    public function created(Order $order): void
    {
        AdminNotice::toAdmins('Order baru', "#{$order->id} dari {$order->buyer?->name}", route('admin.orders.show', $order, absolute: false), 'shopping-cart');
    }

    public function updated(Order $order): void
    {
        $this->notifyAdmins($order);
        $this->notifySales($order);
        $this->notifyBuyer($order);
    }

    private function notifyAdmins(Order $order): void
    {
        $url = route('admin.orders.show', $order, absolute: false);
        if ($order->wasChanged('status') && $order->status === OrderStatus::RejectedByUser) {
            AdminNotice::toAdmins('Toko menolak barang', "#{$order->id} {$order->buyer?->name}", $url, 'circle-x', 'warning', urgent: true);
        }
        if ($order->wasChanged('status') && $order->status === OrderStatus::Completed) {
            AdminNotice::toAdmins('Pesanan selesai', "#{$order->id} {$order->buyer?->name} · Rp ".number_format($order->total, 0, ',', '.'), $url, 'circle-check', 'success');
        }
        if ($order->wasChanged('needs_payment_review') && $order->needs_payment_review) {
            AdminNotice::toAdmins('Pembayaran perlu dicek', "Pesanan #{$order->id} perlu review pembayaran", route('admin.orders.show', $order, absolute: false), 'wallet', 'warning', urgent: true);
        }
    }

    private function notifySales(Order $order): void
    {
        $sales = $order->salesUser;

        if (SalesNotice::skipFor($sales)) {
            return;
        }

        $url = route('sales.deliveries.show', $order, absolute: false);

        if ($order->wasChanged('sales_id')) {
            $sales->notify(new SalesNotice('Tugas kirim baru', "Pesanan #{$order->id} untuk {$order->buyer->name}", $url, 'package', 'info'));
        }

        if ($order->wasChanged('gudang_handover_at') && $order->gudang_handover_at !== null) {
            $sales->notify(new SalesNotice('Barang siap diambil', "Admin menyerahkan barang pesanan #{$order->id}, terima sekarang", $url, 'package-check', 'warning'));
        }

        // Only an admin cancel carries a reason; a resolved delivery rejection also ends Cancelled but the sales already knows.
        if ($order->wasChanged('status') && $order->status === OrderStatus::Cancelled && $order->cancel_reason) {
            $sales->notify(new SalesNotice('Pesanan dibatalkan', "Pesanan #{$order->id} dibatalkan: {$order->cancel_reason->label()}", route('sales.orders.index', absolute: false), 'circle-x', 'warning'));
        }
    }

    private function notifyBuyer(Order $order): void
    {
        if (BuyerNotice::skipFor($order->buyer)) {
            return;
        }

        $url = route('orders.show', $order, absolute: false);
        $id = $order->id;

        $notices = [];

        if ($order->wasChanged('status')) {
            $notices[] = match ($order->status) {
                OrderStatus::Processing => ['Pesanan dikonfirmasi', "Pesanan #{$id} dikonfirmasi dan sedang disiapkan", 'package', 'info'],
                OrderStatus::Shipped => ['Pesanan dikirim', $order->salesUser
                    ? "Pesanan #{$id} sedang diantar oleh {$order->salesUser->name}"
                    : "Pesanan #{$id} sedang diantar", 'truck', 'info'],
                OrderStatus::Completed => ['Pesanan selesai', "Pesanan #{$id} selesai.", 'circle-check', 'success'],
                OrderStatus::Cancelled => ['Pesanan dibatalkan', "Pesanan #{$id} dibatalkan".($order->cancel_reason ? ': '.$order->cancel_reason->label() : ''), 'circle-x', 'warning'],
                OrderStatus::RejectedByUser => ['Penolakan dicatat', "Penolakan pesanan #{$id} dicatat dan diteruskan ke Admin", 'circle-x', 'warning'],
                default => null,
            };
        }

        if ($order->wasChanged('sales_delivered_at') && $order->sales_delivered_at !== null) {
            $notices[] = ['Pesanan sudah diantar', "Pesanan #{$id} sudah diantar. Konfirmasi penerimaan dalam 3 hari.", 'circle-alert', 'warning'];
        }

        if ($order->wasChanged('payment_reject_reason') && $order->payment_reject_reason !== null) {
            $notices[] = ['Bukti transfer ditolak', "Pesanan #{$id}: {$order->payment_reject_reason}. Unggah ulang bukti dalam 30 menit.", 'wallet', 'warning'];
        }

        if ($order->wasChanged('payment_status')) {
            $notices[] = match ($order->payment_status) {
                PaymentStatus::Paid => ['Pembayaran berhasil', "Pembayaran pesanan #{$id} berhasil", 'wallet', 'success'],
                PaymentStatus::Failed => ['Pembayaran gagal', "Pembayaran pesanan #{$id} gagal, silakan coba lagi", 'wallet', 'warning'],
                default => null,
            };
        }

        foreach (array_filter($notices) as [$title, $body, $icon, $tone]) {
            $order->buyer->notify(new BuyerNotice($title, $body, $url, $icon, $tone));
        }
    }
}
