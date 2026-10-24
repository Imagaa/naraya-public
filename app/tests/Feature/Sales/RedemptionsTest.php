<?php

use App\Enums\RedemptionStatus;
use App\Enums\Role;
use App\Livewire\Sales\Redemptions\Index;
use App\Models\Redemption;
use App\Models\Reward;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('s3');
});

it('lists only approved redemptions assigned to the logged-in sales user', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $buyer = User::factory()->create(['role' => Role::User]);
    $reward = Reward::factory()->create(['name' => 'Payung Naraya']);

    Redemption::factory()->create([
        'user_id' => $buyer->id,
        'reward_id' => $reward->id,
        'sales_id' => $sales->id,
        'status' => RedemptionStatus::Disetujui,
    ]);

    Livewire::actingAs($sales)
        ->test(Index::class)
        ->assertSee('Payung Naraya');
});

it('marks a redemption delivered with a proof photo', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $buyer = User::factory()->create(['role' => Role::User]);
    $reward = Reward::factory()->create();

    $redemption = Redemption::factory()->create([
        'user_id' => $buyer->id,
        'reward_id' => $reward->id,
        'sales_id' => $sales->id,
        'status' => RedemptionStatus::Disetujui,
    ]);

    Livewire::actingAs($sales)
        ->test(Index::class)
        ->set("proofPhotos.{$redemption->id}", UploadedFile::fake()->image('bukti.jpg'))
        ->call('markDelivered', $redemption->id);

    $redemption->refresh();
    expect($redemption->status)->toBe(RedemptionStatus::Diserahkan);
    expect($redemption->diserahkan_oleh)->toBe($sales->id);
    Storage::disk('s3')->assertExists($redemption->bukti_path);
});

it('blocks marking delivered a redemption assigned to a different sales', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $otherSales = User::factory()->create(['role' => Role::Sales]);
    $buyer = User::factory()->create(['role' => Role::User]);
    $reward = Reward::factory()->create();

    $redemption = Redemption::factory()->create([
        'user_id' => $buyer->id,
        'reward_id' => $reward->id,
        'sales_id' => $otherSales->id,
        'status' => RedemptionStatus::Disetujui,
    ]);

    expect(fn () => Livewire::actingAs($sales)
        ->test(Index::class)
        ->set("proofPhotos.{$redemption->id}", UploadedFile::fake()->image('bukti.jpg'))
        ->call('markDelivered', $redemption->id)
    )->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    expect($redemption->fresh()->status)->toBe(RedemptionStatus::Disetujui);
});

it('shows the buyer address and contact buttons on each reward to hand over', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $buyer = User::factory()->create(['role' => Role::User, 'name' => 'Warung Bu Sri']);
    $buyer->buyerProfile()->create(['sales_user_id' => $sales->id, 'phone' => '081234567890', 'address' => 'Jl. Melati 3']);
    Redemption::factory()->create([
        'user_id' => $buyer->id,
        'sales_id' => $sales->id,
        'status' => RedemptionStatus::Disetujui,
        'reward_id' => Reward::factory()->create(['name' => 'Payung Naraya'])->id,
    ]);

    Livewire::actingAs($sales)->test(Index::class)
        ->assertSee('Jl. Melati 3')
        ->assertSee('https://wa.me/6281234567890', false)
        ->assertSee('Ambil Foto');
});
