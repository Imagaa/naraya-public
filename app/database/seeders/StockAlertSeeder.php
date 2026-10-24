<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\StockAlert;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Buyers waiting on sold-out products ("Kabari saya"); the first sold-out product has no waiters. */
class StockAlertSeeder extends Seeder
{
    public function run(): void
    {
        $soldOut = Product::where('is_active', true)->where('stock', 0)->orderBy('id')->get();
        $buyer1 = User::where('email', 'buyer1@naraya.test')->firstOrFail();
        $others = UserSeeder::shoppingBuyers()->where('id', '!=', $buyer1->id)->take(2)->get();

        foreach ($soldOut->slice(1, 2) as $product) {
            StockAlert::create(['user_id' => $buyer1->id, 'product_id' => $product->id]);
        }

        foreach ($others as $i => $buyer) {
            StockAlert::create(['user_id' => $buyer->id, 'product_id' => $soldOut[($i % 2) + 1]->id]);
        }
    }
}
