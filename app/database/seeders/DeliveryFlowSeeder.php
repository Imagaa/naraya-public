<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Area;
use App\Models\BuyerProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds one order per stage of the Sales delivery pipeline (Gudang handover
 * -> Sales receives -> en route with a real destination pin -> delivered,
 * awaiting buyer confirmation) so the whole /pesanan -> /deliveries/{order}
 * navigation flow — including the route map and "Mulai Perjalanan" handoff —
 * can be clicked through end to end without waiting on real order traffic
 * to reach every stage.
 */
class DeliveryFlowSeeder extends Seeder
{
    public function run(): void
    {
        $sales = User::where('email', 'sales1@naraya.test')->firstOrFail();
        $gudang = User::where('role', Role::Gudang)->firstOrFail();
        $buyer = User::where('email', 'buyer1@naraya.test')->firstOrFail();
        $area = Area::findOrFail($buyer->buyerProfile->area_id);
        $products = Product::inRandomOrder()->limit(2)->get();
        $photos = UserSeeder::uploadSeedPhotos('proof', 2);

        // Small jitter around the area centroid so the destination pin sits
        // somewhere plausible nearby rather than exactly on it.
        $deliveryLat = $area->lat + fake()->randomFloat(6, -0.01, 0.01);
        $deliveryLng = $area->lng + fake()->randomFloat(6, -0.01, 0.01);

        $this->makeOrder($buyer, $sales, $products, [
            'status' => OrderStatus::Processing,
            'sales_assigned_at' => now()->subHours(3),
        ]);

        $this->makeOrder($buyer, $sales, $products, [
            'status' => OrderStatus::Processing,
            'sales_assigned_at' => now()->subHours(3),
            'gudang_handover_at' => now()->subMinutes(45),
            'gudang_handover_by' => $gudang->id,
        ]);

        $this->makeOrder($buyer, $sales, $products, [
            'status' => OrderStatus::Processing,
            'sales_assigned_at' => now()->subHours(3),
            'gudang_handover_at' => now()->subMinutes(45),
            'gudang_handover_by' => $gudang->id,
            'sales_received_at' => now()->subMinutes(20),
            'sales_received_photo_path' => $photos[0],
        ]);

        $this->makeOrder($buyer, $sales, $products, [
            'status' => OrderStatus::Shipped,
            'sales_assigned_at' => now()->subHours(3),
            'gudang_handover_at' => now()->subMinutes(45),
            'gudang_handover_by' => $gudang->id,
            'sales_received_at' => now()->subMinutes(20),
            'sales_received_photo_path' => $photos[0],
            'delivery_lat' => $deliveryLat,
            'delivery_lng' => $deliveryLng,
        ]);

        $this->makeOrder($buyer, $sales, $products, [
            'status' => OrderStatus::Shipped,
            'sales_assigned_at' => now()->subHours(3),
            'gudang_handover_at' => now()->subMinutes(45),
            'gudang_handover_by' => $gudang->id,
            'sales_received_at' => now()->subMinutes(20),
            'sales_received_photo_path' => $photos[0],
            'delivery_lat' => $deliveryLat,
            'delivery_lng' => $deliveryLng,
            'sales_delivered_at' => now()->subMinutes(5),
            'sales_delivered_photo_path' => $photos[1],
        ]);
    }

    private function makeOrder(User $buyer, User $sales, $products, array $attributes): Order
    {
        $order = Order::create(array_merge([
            'buyer_id' => $buyer->id,
            'sales_id' => $sales->id,
            'payment_method' => 'cash',
            'delivery_address' => $buyer->buyerProfile->address,
        ], $attributes));

        $items = OrderItem::factory()
            ->count(2)
            ->create(['order_id' => $order->id, 'product_id' => fn () => $products->random()->id]);

        $subtotal = $items->sum('subtotal');
        $order->update(['subtotal' => $subtotal, 'total' => $subtotal]);

        return $order;
    }
}
