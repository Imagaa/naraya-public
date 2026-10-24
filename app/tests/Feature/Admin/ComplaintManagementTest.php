<?php

use App\Enums\ComplaintStatus;
use App\Enums\ComplaintType;
use App\Enums\Role;
use App\Livewire\Admin\Complaints\Index;
use App\Livewire\Admin\Complaints\Show;
use App\Models\BuyerProfile;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
});

it('lists complaints', function () {
    $order = Order::factory()->create();
    Complaint::factory()->create(['order_id' => $order->id]);

    Livewire::actingAs($this->admin)->test(Index::class)->assertSee('#'.$order->id);
});

it('approves a complaint with potongan_order_berikutnya and credits the buyer balance', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    $buyerProfile = BuyerProfile::factory()->create(['user_id' => $buyer->id, 'credit_balance' => 0]);
    $order = Order::factory()->create(['buyer_id' => $buyer->id]);
    $complaint = Complaint::factory()->create([
        'order_id' => $order->id, 'tipe' => ComplaintType::KomplainPascaSelesai, 'status' => ComplaintStatus::Diajukan,
    ]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['complaint' => $complaint])
        ->set('resolusi', 'potongan_order_berikutnya')
        ->set('nominalPenyesuaian', 20000)
        ->call('approve');

    expect($complaint->fresh()->status)->toBe(ComplaintStatus::Diresolusi);
    expect($complaint->fresh()->resolusi)->toBe('potongan_order_berikutnya');
    expect($buyerProfile->fresh()->credit_balance)->toBe(20000);
});

it('approves a complaint with refund without touching the buyer balance', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    $buyerProfile = BuyerProfile::factory()->create(['user_id' => $buyer->id, 'credit_balance' => 0]);
    $order = Order::factory()->create(['buyer_id' => $buyer->id]);
    $complaint = Complaint::factory()->create([
        'order_id' => $order->id, 'tipe' => ComplaintType::KomplainPascaSelesai, 'status' => ComplaintStatus::Diajukan,
    ]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['complaint' => $complaint])
        ->set('resolusi', 'refund')
        ->call('approve');

    expect($complaint->fresh()->status)->toBe(ComplaintStatus::Diresolusi);
    expect($complaint->fresh()->resolusi)->toBe('refund');
    expect($buyerProfile->fresh()->credit_balance)->toBe(0);
});

it('rejects a complaint with a required note', function () {
    $order = Order::factory()->create();
    $complaint = Complaint::factory()->create(['order_id' => $order->id, 'status' => ComplaintStatus::Diajukan]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['complaint' => $complaint])
        ->call('reject')
        ->assertHasErrors('catatanAdmin');

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['complaint' => $complaint])
        ->set('catatanAdmin', 'Foto tidak menunjukkan kerusakan')
        ->call('reject');

    expect($complaint->fresh()->status)->toBe(ComplaintStatus::Ditutup);
    expect($complaint->fresh()->catatan_admin)->toBe('Foto tidak menunjukkan kerusakan');
});

it('blocks non-admin roles from the complaints index', function () {
    $buyer = User::factory()->create(['role' => Role::User]);

    $this->actingAs($buyer)
        ->get('http://admin.'.config('app.domain').'/complaints')
        ->assertStatus(403);
});
