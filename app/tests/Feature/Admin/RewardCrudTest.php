<?php

use App\Enums\Role;
use App\Livewire\Admin\Rewards\Form;
use App\Livewire\Admin\Rewards\Index;
use App\Models\Reward;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
});

it('lists rewards and filters by search', function () {
    Reward::factory()->create(['name' => 'Kaos Naraya']);
    Reward::factory()->create(['name' => 'Payung Lipat']);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->assertSee('Kaos Naraya')
        ->assertSee('Payung Lipat')
        ->set('search', 'Kaos')
        ->assertDontSee('Payung Lipat')
        ->assertSee('Kaos Naraya');
});

it('creates a reward with a photo', function () {
    Storage::fake('s3');

    Livewire::actingAs($this->admin)
        ->test(Form::class)
        ->set('name', 'Topi Naraya')
        ->set('pointCost', 40)
        ->set('stock', 20)
        ->set('foto', UploadedFile::fake()->image('topi.jpg'))
        ->call('save')
        ->assertRedirect(route('admin.rewards.index'));

    $reward = Reward::where('name', 'Topi Naraya')->firstOrFail();
    expect($reward->point_cost)->toBe(40);
    expect($reward->stock)->toBe(20);
    Storage::disk('s3')->assertExists($reward->photo_path);
});

it('blocks non-admin roles from the rewards index', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);

    $this->actingAs($sales)
        ->get('http://admin.'.config('app.domain').'/rewards')
        ->assertStatus(403);
});
