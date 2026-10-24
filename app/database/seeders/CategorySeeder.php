<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Colors/icons match the ones the create_categories_table migration
     * assigns when backfilling production data, so fresh installs and
     * migrated production data end up looking the same.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Mie Instan', 'color' => '#7A8A5E', 'icon' => 'soup'],
            ['name' => 'Minuman', 'color' => '#9C8C67', 'icon' => 'cup-soda'],
            ['name' => 'Sembako', 'color' => '#A8863F', 'icon' => 'shopping-bag'],
            ['name' => 'Snack', 'color' => '#7A8A5E', 'icon' => 'cookie'],
            ['name' => 'Sabun & Toiletries', 'color' => '#9C8C67', 'icon' => 'soap-dispenser-droplet'],
            ['name' => 'Bumbu Dapur', 'color' => '#C1603A', 'icon' => 'chef-hat'],
            ['name' => 'Kebutuhan Rumah', 'color' => '#F0B90B', 'icon' => 'home'],
            ['name' => 'Lainnya', 'color' => '#9C8C67', 'icon' => 'more-horizontal'],
            ['name' => 'Kopi & Teh', 'color' => '#7A4E2D', 'icon' => 'coffee'],
            ['name' => 'Susu & Olahan', 'color' => '#5B7DB1', 'icon' => 'milk'],
            ['name' => 'Makanan Kaleng & Instan', 'color' => '#8C5A3C', 'icon' => 'package'],
            ['name' => 'Perawatan Bayi', 'color' => '#C27BA0', 'icon' => 'baby'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(['name' => $category['name']], $category);
        }
    }
}
