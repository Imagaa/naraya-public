<?php

use App\Enums\ComplaintStatus;
use App\Enums\ComplaintType;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Livewire\Marketplace\Complaints\Create;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('lets a buyer submit a complaint with a photo on a completed order', function () {
    Storage::fake('s3');
    $buyer = User::factory()->create(['role' => Role::User]);
    $order = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => OrderStatus::Completed]);

    Livewire::actingAs($buyer)
        ->test(Create::class, ['order' => $order])
        ->set('alasan', 'Barang rusak saat dibuka')
        ->set('foto', UploadedFile::fake()->image('bukti.jpg'))
        ->call('submit')
        ->assertRedirect(route('orders.index'));

    $complaint = Complaint::where('order_id', $order->id)->firstOrFail();
    expect($complaint->tipe)->toBe(ComplaintType::KomplainPascaSelesai);
    expect($complaint->status)->toBe(ComplaintStatus::Diajukan);
    expect($complaint->alasan)->toBe('Barang rusak saat dibuka');
    Storage::disk('s3')->assertExists($complaint->foto_path);
});

it('requires a photo to submit a complaint', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    $order = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => OrderStatus::Completed]);

    Livewire::actingAs($buyer)
        ->test(Create::class, ['order' => $order])
        ->set('alasan', 'Barang rusak')
        ->call('submit')
        ->assertHasErrors('foto');
});

it('blocks submitting a complaint on an order that is not yet Completed', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    $order = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => OrderStatus::Processing]);

    $this->actingAs($buyer)
        ->get('http://'.config('app.domain')."/pesanan/{$order->id}/komplain")
        ->assertStatus(403);
});

it('blocks a buyer from complaining about another buyer\'s order', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    $otherBuyer = User::factory()->create(['role' => Role::User]);
    $order = Order::factory()->create(['buyer_id' => $otherBuyer->id, 'status' => OrderStatus::Completed]);

    $this->actingAs($buyer)
        ->get('http://'.config('app.domain')."/pesanan/{$order->id}/komplain")
        ->assertStatus(403);
});

it('renders the complaint form in the marketplace layout with a photo drop zone', function () {
    $buyer = \App\Models\User::factory()->create(['role' => \App\Enums\Role::User]);
    $order = \App\Models\Order::factory()->create(['buyer_id' => $buyer->id, 'status' => 'completed']);

    $this->actingAs($buyer)->get(route('complaints.create', $order))
        ->assertOk()
        ->assertSee('<body class="mp" data-line="', false)
        ->assertSee('class="taskbar"', false)
        ->assertSee('Apa masalahnya?')
        ->assertSee('class="file-pick"', false)
        ->assertSee('saldo kredit');
});
