<?php

use App\Enums\Role;
use App\Livewire\Admin\Buyers\Form;
use App\Livewire\Admin\Buyers\Index;
use App\Models\Area;
use App\Models\BuyerProfile;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
});

it('renders numbered page links, not just previous/next, when there are multiple pages', function () {
    User::factory(20)->state(['role' => Role::User])->create();

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->assertSee('gotoPage(2', false);
});

it('lists buyers with their phone number', function () {
    $buyer = User::factory()->create(['role' => Role::User, 'name' => 'Toko Makmur']);
    // BuyerProfile has two belongsTo(User::class) relations (user, salesUser) —
    // ->for() needs the relation name spelled out to avoid ambiguity.
    BuyerProfile::factory()->for($buyer, 'user')->create(['phone' => '081211112222']);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->assertSee('Toko Makmur')
        ->assertSee('081211112222');
});

it('creates a buyer with a profile in one step', function () {
    $area = Area::factory()->create();

    \Illuminate\Support\Facades\Storage::fake('s3');

    // No login fields: the store is saved without an account and opens on its own page with the account panel.
    $form = Livewire::actingAs($this->admin)
        ->test(Form::class)
        ->set('name', 'Toko Sejahtera')
        ->set('ownerName', 'Pak Joko')
        ->set('phone', '081233334444')
        ->set('address', 'Jl. Mawar No. 5')
        ->set('areaId', $area->id)
        ->set('lat', -6.2)->set('lng', 106.8)
        ->set('storePhoto', \Illuminate\Http\UploadedFile::fake()->image('depan.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $user = User::where('name', 'Toko Sejahtera')->firstOrFail();
    $form->assertRedirect(route('admin.buyers.edit', ['buyer' => $user, 'akun' => 1]));
    expect($user->role)->toBe(Role::User)->and($user->hasAccount())->toBeFalse();
    expect($user->buyerProfile->phone)->toBe('6281233334444');
    expect($user->buyerProfile->area_id)->toBe($area->id);
});

it('blocks non-admin roles from the buyers index', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);

    $this->actingAs($sales)
        ->get('http://admin.'.config('app.domain').'/buyers')
        ->assertStatus(403);
});
