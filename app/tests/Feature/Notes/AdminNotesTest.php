<?php

use App\Enums\BusinessLine;
use App\Enums\NotePaymentMethod;
use App\Enums\ProofStatus;
use App\Enums\Role;
use App\Livewire\Admin\Notes\Index;
use App\Livewire\Admin\Notes\Show;
use App\Models\BuyerProfile;
use App\Models\Product;
use App\Models\User;
use App\Support\ActiveLine;
use App\Support\Admin\WorkQueues;
use App\Support\Sales\SalesNotes;
use App\Support\Stock\StockTransfers;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi']);
    $this->store = User::factory()->create(['role' => Role::User, 'name' => 'Toko Maju']);
    BuyerProfile::factory()->create(['user_id' => $this->store->id, 'sales_user_id' => $this->sales->id]);
    $mie = Product::factory()->create(['name' => 'Mie', 'stock' => 20, 'price' => 3000]);
    $toy = Product::factory()->create(['name' => 'Mobil', 'stock' => 5, 'price' => 50000, 'line' => BusinessLine::Toys]);
    app(StockTransfers::class)->pickup($this->sales, BusinessLine::Naraya, [$mie->id => 10]);
    app(StockTransfers::class)->pickup($this->sales, BusinessLine::Toys, [$toy->id => 2]);
    $notes = app(SalesNotes::class);
    $this->cash = $notes->create($this->sales, BusinessLine::Naraya, $this->store->id, [$mie->id => 2], NotePaymentMethod::Cash);
    $this->transfer = $notes->create($this->sales, BusinessLine::Naraya, $this->store->id, [$mie->id => 1], NotePaymentMethod::Transfer, UploadedFile::fake()->image('b.jpg'));
    $this->toyNote = $notes->create($this->sales, BusinessLine::Toys, $this->store->id, [$toy->id => 1], NotePaymentMethod::Cash);
});

it('lists the active line\'s notes with filters', function () {
    Livewire::actingAs($this->admin)->test(Index::class)
        ->assertSee($this->cash->number())->assertSee($this->transfer->number())->assertDontSee($this->toyNote->number())
        ->assertViewHas('counts', fn ($c) => $c['all'] === 2 && $c['tunai'] === 1 && $c['menunggu'] === 1 && $c['ditolak'] === 0)
        ->call('setFilter', 'menunggu')
        ->assertSee($this->transfer->number())->assertDontSee($this->cash->number());

    ActiveLine::set(BusinessLine::Toys);
    Livewire::actingAs($this->admin)->test(Index::class)->assertSee($this->toyNote->number())->assertDontSee($this->cash->number());
});

it('verifies or rejects a pending proof from the note page', function () {
    Livewire::actingAs($this->admin)->test(Show::class, ['note' => $this->transfer])
        ->assertSee('Toko Maju')->assertSee('Menunggu verifikasi')
        ->call('verify')
        ->assertSee('Terverifikasi');
    expect($this->transfer->fresh()->proof_status)->toBe(ProofStatus::Verified);
});

it('needs a reason to reject a proof', function () {
    Livewire::actingAs($this->admin)->test(Show::class, ['note' => $this->transfer])
        ->call('reject')->assertHasErrors('reason')
        ->set('reason', 'Nominal tidak sesuai')->call('reject')->assertHasNoErrors();
    expect($this->transfer->fresh()->proof_status)->toBe(ProofStatus::Rejected);
});

it('queues pending proofs for the active line on the dashboard and the sidebar', function () {
    expect(WorkQueues::count('note-proofs'))->toBe(1);
});

it('shows the store\'s note history on the admin store page and the sales store page', function () {
    $this->actingAs($this->admin)->get('http://'.config('app.admin_domain').'/buyers/'.$this->store->id.'/edit')
        ->assertSee('Riwayat nota')->assertSee($this->cash->number())->assertSee($this->toyNote->number());
    Livewire::actingAs($this->sales)->test(\App\Livewire\Sales\Buyers\Show::class, ['buyer' => $this->store->id])
        ->assertSee($this->cash->number())->assertSee('Kunjungan titip jual');
});
