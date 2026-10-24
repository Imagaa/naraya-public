<?php

use App\Enums\RedemptionStatus;
use App\Enums\Role;
use App\Livewire\Admin\Redemptions\Index;
use App\Livewire\Admin\Redemptions\Show;
use App\Models\BuyerProfile;
use App\Models\Redemption;
use App\Models\Reward;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
});

it('lists redemptions', function () {
    $redemption = Redemption::factory()->create();

    Livewire::actingAs($this->admin)->test(Index::class)->assertSee('#'.$redemption->id);
});

it('approves a redemption, deducting points and reward stock', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    $buyerProfile = BuyerProfile::factory()->create(['user_id' => $buyer->id, 'loyalty_points' => 50]);
    $reward = Reward::factory()->create(['point_cost' => 30, 'stock' => 5]);
    $redemption = Redemption::factory()->create([
        'user_id' => $buyer->id, 'reward_id' => $reward->id, 'point_cost' => 30, 'status' => RedemptionStatus::Diajukan,
    ]);

    Livewire::actingAs($this->admin)->test(Show::class, ['redemption' => $redemption])->call('approve');

    expect($redemption->fresh()->status)->toBe(RedemptionStatus::Disetujui);
    expect($buyerProfile->fresh()->loyalty_points)->toBe(20);
    expect($reward->fresh()->stock)->toBe(4);
});

it('blocks approval when the buyer no longer has enough points', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $buyer->id, 'loyalty_points' => 10]);
    $reward = Reward::factory()->create(['point_cost' => 30, 'stock' => 5]);
    $redemption = Redemption::factory()->create([
        'user_id' => $buyer->id, 'reward_id' => $reward->id, 'point_cost' => 30, 'status' => RedemptionStatus::Diajukan,
    ]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['redemption' => $redemption])
        ->call('approve')
        ->assertHasErrors('approve');

    expect($redemption->fresh()->status)->toBe(RedemptionStatus::Diajukan);
});

it('rejects a redemption with a required reason and no side effects', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    $buyerProfile = BuyerProfile::factory()->create(['user_id' => $buyer->id, 'loyalty_points' => 50]);
    $reward = Reward::factory()->create(['point_cost' => 30, 'stock' => 5]);
    $redemption = Redemption::factory()->create([
        'user_id' => $buyer->id, 'reward_id' => $reward->id, 'point_cost' => 30, 'status' => RedemptionStatus::Diajukan,
    ]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['redemption' => $redemption])
        ->call('reject')
        ->assertHasErrors('alasanPenolakan');

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['redemption' => $redemption])
        ->set('alasanPenolakan', 'Stok fisik gudang kosong')
        ->call('reject');

    expect($redemption->fresh()->status)->toBe(RedemptionStatus::Ditolak);
    expect($buyerProfile->fresh()->loyalty_points)->toBe(50);
    expect($reward->fresh()->stock)->toBe(5);
});

it('cancels an approved redemption and refunds points and stock', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    $buyerProfile = BuyerProfile::factory()->create(['user_id' => $buyer->id, 'loyalty_points' => 20]);
    $reward = Reward::factory()->create(['point_cost' => 30, 'stock' => 4]);
    $redemption = Redemption::factory()->create([
        'user_id' => $buyer->id, 'reward_id' => $reward->id, 'point_cost' => 30, 'status' => RedemptionStatus::Disetujui,
    ]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['redemption' => $redemption])
        ->set('alasanPenolakan', 'Hadiah rusak sebelum diserahkan')
        ->call('cancel');

    expect($redemption->fresh()->status)->toBe(RedemptionStatus::Dibatalkan);
    expect($buyerProfile->fresh()->loyalty_points)->toBe(50);
    expect($reward->fresh()->stock)->toBe(5);
});

it('blocks non-admin roles from the redemptions index', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);

    $this->actingAs($sales)
        ->get('http://admin.'.config('app.domain').'/redemptions')
        ->assertStatus(403);
});

it('marks an approved redemption as delivered with a required photo', function () {
    Storage::fake('s3');
    $redemption = Redemption::factory()->create(['status' => RedemptionStatus::Disetujui]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['redemption' => $redemption])
        ->set('buktiFoto', UploadedFile::fake()->image('serah-terima.jpg'))
        ->call('markDelivered');

    $redemption->refresh();
    expect($redemption->status)->toBe(RedemptionStatus::Diserahkan);
    expect($redemption->diserahkan_oleh)->toBe($this->admin->id);
    Storage::disk('s3')->assertExists($redemption->bukti_path);
});

it('requires a photo to mark a redemption as delivered', function () {
    $redemption = Redemption::factory()->create(['status' => RedemptionStatus::Disetujui]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['redemption' => $redemption])
        ->call('markDelivered')
        ->assertHasErrors('buktiFoto');
});
