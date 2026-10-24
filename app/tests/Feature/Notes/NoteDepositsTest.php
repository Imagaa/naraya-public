<?php

use App\Enums\BusinessLine;
use App\Enums\CashCollectionStatus;
use App\Enums\NotePaymentMethod;
use App\Enums\Role;
use App\Livewire\Admin\Deposits\Index as AdminDeposits;
use App\Livewire\Admin\Deposits\Show as AdminDeposit;
use App\Livewire\Sales\Deposits\Index;
use App\Models\BuyerProfile;
use App\Models\CashCollection;
use App\Models\CashDeposit;
use App\Models\Product;
use App\Models\User;
use App\Support\ActiveLine;
use App\Support\Admin\WorkQueues;
use App\Support\Sales\SalesNotes;
use App\Support\Stock\StockTransfers;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    $this->sales = User::factory()->create(['role' => Role::Sales]);
    $this->store = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $this->store->id, 'sales_user_id' => $this->sales->id]);
    $mie = Product::factory()->create(['stock' => 50, 'price' => 3000]);
    $toy = Product::factory()->create(['stock' => 5, 'price' => 50000, 'line' => BusinessLine::Toys]);
    app(StockTransfers::class)->pickup($this->sales, BusinessLine::Naraya, [$mie->id => 20]);
    app(StockTransfers::class)->pickup($this->sales, BusinessLine::Toys, [$toy->id => 3]);
    $notes = app(SalesNotes::class);
    $this->cashA = $notes->create($this->sales, BusinessLine::Naraya, $this->store->id, [$mie->id => 2], NotePaymentMethod::Cash);   // 6.000
    $this->cashB = $notes->create($this->sales, BusinessLine::Naraya, $this->store->id, [$mie->id => 3], NotePaymentMethod::Cash);   // 9.000
    $this->transfer = $notes->create($this->sales, BusinessLine::Naraya, $this->store->id, [$mie->id => 1], NotePaymentMethod::Transfer, UploadedFile::fake()->image('q.jpg'));
    $this->toyCash = $notes->create($this->sales, BusinessLine::Toys, $this->store->id, [$toy->id => 1], NotePaymentMethod::Cash);    // 50.000
    $this->legacy = CashCollection::factory()->create(['sales_id' => $this->sales->id, 'amount' => 20000, 'status' => CashCollectionStatus::Held]);
});

it('deposits the active line\'s undeposited cash notes, plus legacy held cash for Naraya', function () {
    Livewire::actingAs($this->sales)->test(Index::class)
        ->assertSet('claimedAmount', 35000)
        ->assertSee($this->cashA->number())->assertDontSee($this->transfer->number())->assertDontSee($this->toyCash->number())
        ->call('submitDeposit')->assertHasNoErrors();

    $deposit = CashDeposit::sole();
    expect($deposit->line)->toBe(BusinessLine::Naraya)
        ->and($deposit->expected_amount)->toBe(35000)
        ->and($this->cashA->fresh()->cash_deposit_id)->toBe($deposit->id)
        ->and($this->cashB->fresh()->cash_deposit_id)->toBe($deposit->id)
        ->and($this->transfer->fresh()->cash_deposit_id)->toBeNull()
        ->and($this->toyCash->fresh()->cash_deposit_id)->toBeNull()
        ->and($this->legacy->fresh()->status)->toBe(CashCollectionStatus::Deposited);
});

it('keeps Toys cash in a Toys deposit without legacy order cash', function () {
    ActiveLine::set(BusinessLine::Toys);
    Livewire::actingAs($this->sales)->test(Index::class)
        ->assertSet('claimedAmount', 50000)->assertDontSee('Pesanan #')
        ->call('submitDeposit')->assertHasNoErrors();

    $deposit = CashDeposit::sole();
    expect($deposit->line)->toBe(BusinessLine::Toys)
        ->and($deposit->expected_amount)->toBe(50000)
        ->and($this->toyCash->fresh()->cash_deposit_id)->toBe($deposit->id)
        ->and($this->legacy->fresh()->status)->toBe(CashCollectionStatus::Held);
});

it('shows admin only the active line\'s deposits, with the notes each covers', function () {
    Livewire::actingAs($this->sales)->test(Index::class)->call('submitDeposit');
    ActiveLine::set(BusinessLine::Toys);
    Livewire::actingAs($this->sales)->test(Index::class)->call('submitDeposit');
    [$naraya, $toys] = [CashDeposit::forLine(BusinessLine::Naraya)->sole(), CashDeposit::forLine(BusinessLine::Toys)->sole()];

    $admin = User::factory()->create(['role' => Role::Admin]);
    ActiveLine::set(BusinessLine::Naraya);
    Livewire::actingAs($admin)->test(AdminDeposits::class)
        ->assertViewHas('deposits', fn ($d) => $d->pluck('id')->all() === [$naraya->id])
        ->assertViewHas('counts', fn ($c) => $c['all'] === 1);
    \Illuminate\Support\Once::flush();
    expect(WorkQueues::count('deposits'))->toBe(1);

    Livewire::actingAs($admin)->test(AdminDeposit::class, ['deposit' => $naraya])
        ->assertSee($this->cashA->number())->assertSee($this->cashB->number())->assertSee('#'.$this->legacy->order_id);
    Livewire::actingAs($admin)->test(AdminDeposit::class, ['deposit' => $toys])->assertSee($this->toyCash->number());
});
