<?php

use App\Enums\Role;
use App\Livewire\Admin\Buyers\Index;
use App\Models\BuyerProfile;
use App\Models\User;
use App\Models\Visit;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->budi = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi Penanggung']);
    $this->sari = User::factory()->create(['role' => Role::Sales, 'name' => 'Sari Pengganti']);
    $this->store = User::factory()->create(['role' => Role::User, 'name' => 'Toko Maju']);
    BuyerProfile::factory()->create(['user_id' => $this->store->id, 'sales_user_id' => $this->budi->id]);
});

it('shows each store\'s assigned sales in the store list', function () {
    Livewire::actingAs($this->admin)->test(Index::class)->assertSeeInOrder(['Toko Maju', 'Budi Penanggung']);
});

it('lists the related sales on the store page: the assigned one and others who visited in the last 90 days', function () {
    Visit::create(['sales_id' => $this->sari->id, 'buyer_id' => $this->store->id, 'visited_at' => now()->subDays(3), 'notes' => 'Gantikan Budi']);
    Visit::create(['sales_id' => $this->sari->id, 'buyer_id' => $this->store->id, 'visited_at' => now()->subDays(1)]);
    $old = User::factory()->create(['role' => Role::Sales, 'name' => 'Lama Sekali']);
    Visit::create(['sales_id' => $old->id, 'buyer_id' => $this->store->id, 'visited_at' => now()->subDays(120)]);

    $this->actingAs($this->admin)->get('http://'.config('app.admin_domain').'/buyers/'.$this->store->id.'/edit')
        ->assertSee('Sales terkait')->assertSeeInOrder(['Budi Penanggung', 'Ditugaskan', 'Sari Pengganti', '2 kunjungan']);

    // the sales dropdown on the same page lists every sales, so check the panel's own rows
    Livewire::actingAs($this->admin)->test(\App\Livewire\Admin\Buyers\Form::class, ['buyer' => $this->store])
        ->assertViewHas('relatedSales', fn ($rows) => $rows->pluck('user.name')->all() === ['Budi Penanggung', 'Sari Pengganti']);
});
