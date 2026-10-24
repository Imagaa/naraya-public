<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\BuyerNotice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Enough notifications on buyer1 to exercise the bell ("9+"), read vs
 * unread rows, the "Sebelumnya" group and /notifikasi paging. Built with
 * the real BuyerNotice so the stored shape matches what observers send.
 */
class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $buyer = User::where('email', 'buyer1@naraya.test')->firstOrFail();
        $orders = Order::where('buyer_id', $buyer->id)->latest('id')->take(12)->get();
        $product = Product::where('is_active', true)->where('stock', '>', 0)->first();

        $notices = [];
        foreach ($orders as $order) {
            $url = '/pesanan/'.$order->id;
            $notices[] = new BuyerNotice('Pesanan dikonfirmasi', "Pesanan #{$order->id} dikonfirmasi dan sedang disiapkan", $url, 'package', 'info');
            $notices[] = new BuyerNotice('Pesanan dikirim', "Pesanan #{$order->id} sedang diantar oleh Budi Santoso", $url, 'truck', 'info');
        }
        $notices[] = new BuyerNotice('Pembayaran berhasil', 'Pembayaran pesanan #'.($orders->first()?->id ?? 1).' berhasil', '/pesanan', 'wallet', 'success');
        $notices[] = new BuyerNotice('Penukaran hadiah', 'Penukaran Kaos Naraya: Disetujui', '/hadiah', 'gift', 'success');
        $notices[] = new BuyerNotice('Komplain diselesaikan', 'Komplain pesanan #'.($orders->last()?->id ?? 1).' diselesaikan', '/pesanan', 'message-circle', 'success');
        if ($product) {
            $notices[] = new BuyerNotice('Stok tersedia lagi', "{$product->name} tersedia lagi", '/produk/'.$product->slug, 'package-check', 'success');
        }

        // Oldest first, so the newest 12 are the unread ones; the oldest 8 sit 2–10 days back.
        $total = count($notices);
        foreach (array_values($notices) as $i => $notice) {
            $age = $i < 8 ? now()->subDays(10 - $i) : now()->subMinutes(($total - $i) * 37);
            $buyer->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => BuyerNotice::class,
                'data' => $notice->toArray($buyer),
                'read_at' => $i < $total - 12 ? $age : null,
                'created_at' => $age,
                'updated_at' => $age,
            ]);
        }

        // Another buyer with exactly one unread notice.
        $other = UserSeeder::shoppingBuyers()->where('id', '!=', $buyer->id)->first();
        $other?->notify(new BuyerNotice('Pesanan dikirim', 'Pesanan Anda sedang diantar', '/pesanan', 'truck', 'info'));
    }
}
