<?php

use App\Enums\ComplaintStatus;
use App\Enums\ComplaintType;
use App\Enums\Role;
use App\Models\BuyerProfile;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\User;

it('casts complaint type and status to enums and stores resolution fields', function () {
    $order = Order::factory()->create();
    $admin = User::factory()->create(['role' => Role::Admin]);

    $complaint = Complaint::factory()->create([
        'order_id' => $order->id,
        'tipe' => ComplaintType::KomplainPascaSelesai,
        'status' => ComplaintStatus::Diresolusi,
        'resolusi' => 'potongan_order_berikutnya',
        'nominal_penyesuaian' => 20000,
        'diproses_oleh' => $admin->id,
    ]);

    expect($complaint->tipe)->toBe(ComplaintType::KomplainPascaSelesai);
    expect($complaint->status)->toBe(ComplaintStatus::Diresolusi);
    expect($complaint->order->id)->toBe($order->id);
    expect($complaint->processedBy->id)->toBe($admin->id);
});

it('defaults buyer credit_balance to zero', function () {
    $user = User::factory()->create(['role' => Role::User]);
    $buyer = BuyerProfile::factory()->create(['user_id' => $user->id]);

    expect($buyer->credit_balance)->toBe(0);
});
