<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $product = Product::factory();
        $quantity = fake()->numberBetween(1, 10);

        return [
            'order_id' => Order::factory(),
            'product_id' => $product,
            'product_name' => fn (array $attributes) => Product::find($attributes['product_id'])?->name ?? fake()->words(2, true),
            'price' => fn (array $attributes) => Product::find($attributes['product_id'])?->price ?? fake()->numberBetween(2000, 50000),
            'quantity' => $quantity,
            'subtotal' => fn (array $attributes) => $attributes['price'] * $attributes['quantity'],
        ];
    }
}
