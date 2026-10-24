<?php

use App\Enums\Role;
use App\Livewire\Sales\Buyers\Form;
use App\Models\Area;
use App\Models\SalesProfile;
use App\Models\User;
use Livewire\Livewire;

it('creates a new store auto-assigned to the logged-in sales user', function () {
    $area = Area::factory()->create();
    $sales = User::factory()->create(['role' => Role::Sales]);
    SalesProfile::factory()->for($sales)->for($area)->create();

    \Illuminate\Support\Facades\Storage::fake('s3');

    Livewire::actingAs($sales)
        ->test(Form::class)
        ->set('name', 'Toko Baru Sekali')
        ->set('ownerName', 'Bu Wati')
        ->set('phone', '081234567890')
        ->set('address', 'Jl. Anggrek 4')
        ->set('lat', -6.2)->set('lng', 106.8)
        ->set('storePhoto', \Illuminate\Http\UploadedFile::fake()->image('depan.jpg'))
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Toko tersimpan')->assertSee('Buatkan akun sekarang');

    $buyer = User::where('name', 'Toko Baru Sekali')->firstOrFail();
    expect($buyer->role)->toBe(Role::User)->and($buyer->hasAccount())->toBeFalse()->and($buyer->email)->toBeNull();
    expect($buyer->buyerProfile->sales_user_id)->toBe($sales->id);
    expect($buyer->buyerProfile->area_id)->toBe($area->id);
});
