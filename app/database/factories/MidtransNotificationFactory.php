<?php

namespace Database\Factories;

use App\Models\MidtransNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

class MidtransNotificationFactory extends Factory
{
    protected $model = MidtransNotification::class;

    public function definition(): array
    {
        return [
            'raw_payload' => '{}',
            'signature_valid' => true,
        ];
    }
}
