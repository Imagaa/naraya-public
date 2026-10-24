<?php

namespace Database\Seeders;

use App\Enums\ComplaintStatus;
use App\Enums\ComplaintType;
use App\Enums\OrderStatus;
use App\Models\Complaint;
use App\Models\Order;
use Illuminate\Database\Seeder;

class ComplaintSeeder extends Seeder
{
    public function run(): void
    {
        $photos = UserSeeder::uploadSeedPhotos('proof', 5);
        $completedOrders = Order::where('status', OrderStatus::Completed)->inRandomOrder()->limit(25)->get();

        $types = ComplaintType::cases();
        $statuses = ComplaintStatus::cases();

        foreach ($completedOrders as $index => $order) {
            $status = $statuses[$index % count($statuses)];

            Complaint::create([
                'order_id' => $order->id,
                'tipe' => $types[$index % count($types)],
                'alasan' => fake('id_ID')->sentence(),
                'foto_path' => $photos[$index % count($photos)],
                'status' => $status,
                'resolusi' => $status === ComplaintStatus::Diresolusi ? 'potongan_pesanan_berikutnya' : null,
                'nominal_penyesuaian' => $status === ComplaintStatus::Diresolusi ? fake()->numberBetween(5000, 50000) : null,
            ]);
        }
    }
}
