<?php

namespace Database\Seeders;

use App\Enums\CashBookEntryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\CashBookEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        // Only buyers who can actually shop get orders — pending, rejected,
        // must-change-password and incomplete accounts never placed any.
        $buyers = UserSeeder::shoppingBuyers()->orderBy('id')->get();
        $salesUsers = User::where('role', Role::Sales)->get();
        $products = Product::where('is_active', true)->where('stock', '>', 10)->get();

        // Every buyer except the very last one (which stays order-less — the
        // deliberate empty-state edge case seeded in UserSeeder) gets 4-6 orders.
        $buyersWithOrders = $buyers->slice(0, $buyers->count() - 1);

        $totalCreated = 0;
        foreach ($buyersWithOrders as $buyer) {
            $orderCount = fake()->numberBetween(4, 6);

            for ($i = 0; $i < $orderCount; $i++) {
                $this->createOrder($buyer, $salesUsers, $products);
                $totalCreated++;
            }
        }

        // Top up to at least 300 with extra orders on random existing buyers.
        while ($totalCreated < 300) {
            $this->createOrder($buyersWithOrders->random(), $salesUsers, $products);
            $totalCreated++;
        }

        // Explicit edge cases guaranteed to exist regardless of the random pass above.
        // A random status per order makes every OrderStatus *likely* to appear across
        // 300 orders, but the seeder test asserts every status deterministically
        // exists — relying on chance would make that assertion flaky. Force one order
        // per status explicitly instead.
        foreach (OrderStatus::cases() as $status) {
            $this->createOrder($buyersWithOrders->random(), $salesUsers, $products, $status);
        }

        // buyer1 gets enough history to page /pesanan.
        $buyer1 = $buyers->firstWhere('email', 'buyer1@naraya.test');
        while (Order::where('buyer_id', $buyer1->id)->count() < 25) {
            $this->createOrder($buyer1, $salesUsers, $products);
        }

        $this->createDigitalPayment($buyersWithOrders->random(), $products, PaymentStatus::Pending, now()->addMinutes(20));
        $this->createDigitalPayment($buyersWithOrders->random(), $products, PaymentStatus::Failed, now()->subHour());

        $this->createPartialDeliveryPair($buyersWithOrders->random(), $salesUsers, $products);
        $this->createNeedsPaymentReviewOrder($buyersWithOrders->random(), $products);
        $this->createCreditAppliedOrder($buyersWithOrders->random(), $salesUsers, $products);
    }

    private function createOrder($buyer, $salesUsers, $products, ?OrderStatus $forceStatus = null): Order
    {
        $status = $forceStatus ?? fake()->randomElement(OrderStatus::cases());
        $paymentMethod = fake()->randomElement(['cash', 'cash', 'cash', 'gopay', 'qris']);
        $isCompleted = $status === OrderStatus::Completed;
        $completedAt = $isCompleted ? fake()->dateTimeBetween('-6 months', 'now') : null;

        $order = Order::create([
            'buyer_id' => $buyer->id,
            'sales_id' => in_array($status, [OrderStatus::New], true) ? null : $salesUsers->random()->id,
            'status' => $status,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentMethod === 'cash' ? PaymentStatus::Paid : PaymentStatus::Paid,
            'delivery_address' => fake('id_ID')->address(),
            'completed_at' => $completedAt,
        ]);

        $items = OrderItem::factory()
            ->count(fake()->numberBetween(1, 4))
            ->create(['order_id' => $order->id, 'product_id' => fn () => $products->random()->id]);

        $subtotal = $items->sum('subtotal');
        $order->update(['subtotal' => $subtotal, 'total' => $subtotal]);

        if ($isCompleted && $paymentMethod === 'cash') {
            CashBookEntry::create([
                'type' => CashBookEntryType::Pemasukan,
                'amount' => $order->total,
                'date' => $order->completed_at->toDateString(),
                'reference' => 'Order #'.$order->id,
                'kind' => 'rekap',
                'source' => 'penjualan_online',
            ]);
        }

        return $order;
    }

    private function createPartialDeliveryPair($buyer, $salesUsers, $products): void
    {
        $parent = $this->createOrder($buyer, $salesUsers, $products);
        $parent->update(['status' => OrderStatus::Completed, 'completed_at' => now()->subDays(10)]);

        $child = $this->createOrder($buyer, $salesUsers, $products);
        $child->update(['parent_order_id' => $parent->id, 'status' => OrderStatus::New]);
    }

    private function createNeedsPaymentReviewOrder($buyer, $products): void
    {
        $order = Order::create([
            'buyer_id' => $buyer->id,
            'status' => OrderStatus::New,
            'payment_method' => 'gopay',
            'payment_status' => PaymentStatus::Pending,
            'delivery_address' => fake('id_ID')->address(),
            'payment_expires_at' => now()->subHours(2),
            'needs_payment_review' => true,
        ]);

        $items = OrderItem::factory()->count(2)->create(['order_id' => $order->id, 'product_id' => fn () => $products->random()->id]);
        $subtotal = $items->sum('subtotal');
        $order->update(['subtotal' => $subtotal, 'total' => $subtotal]);
    }

    /** A QRIS/GoPay order still waiting for payment, or one whose payment failed. */
    private function createDigitalPayment($buyer, $products, PaymentStatus $status, $expiresAt): void
    {
        $order = Order::create([
            'buyer_id' => $buyer->id,
            'status' => OrderStatus::New,
            'payment_method' => 'qris',
            'payment_status' => $status,
            'delivery_address' => fake('id_ID')->address(),
            'payment_expires_at' => $expiresAt,
            'midtrans_order_id' => 'NR-SEED-'.$status->value.'-'.$buyer->id,
        ]);

        $items = OrderItem::factory()->count(2)->create(['order_id' => $order->id, 'product_id' => fn () => $products->random()->id]);
        $subtotal = $items->sum('subtotal');
        $order->update(['subtotal' => $subtotal, 'total' => $subtotal]);
    }

    private function createCreditAppliedOrder($buyer, $salesUsers, $products): void
    {
        $order = $this->createOrder($buyer, $salesUsers, $products);
        $credit = (int) round($order->total * 0.1);
        $order->update(['credit_applied' => $credit, 'total' => max(0, $order->total - $credit)]);
    }
}
