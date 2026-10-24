<?php

use App\Enums\BusinessLine;
use App\Enums\CashDepositStatus;
use App\Enums\NotePaymentMethod;
use App\Enums\Role;
use App\Livewire\Admin\Reconciliation\Index;
use App\Models\BuyerProfile;
use App\Models\CashDeposit;
use App\Models\Product;
use App\Models\User;
use App\Support\Sales\Reconciliation;
use App\Support\Sales\SalesNotes;
use App\Support\Stock\StockTransfers;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-20 10:00'));
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi']);
    $store = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $store->id, 'sales_user_id' => $this->sales->id]);
    $mie = Product::factory()->create(['stock' => 100, 'price' => 1000]);
    $toy = Product::factory()->create(['stock' => 10, 'price' => 50000, 'line' => BusinessLine::Toys]);

    $stock = app(StockTransfers::class);
    $stock->pickup($this->sales, BusinessLine::Naraya, [$mie->id => 30]);          // 30.000 taken
    $stock->returnGoods($this->sales, BusinessLine::Naraya, [$mie->id => 5]);      // 5.000 back
    $stock->pickup($this->sales, BusinessLine::Toys, [$toy->id => 2]);             // other line

    $notes = app(SalesNotes::class);
    $cashA = $notes->create($this->sales, BusinessLine::Naraya, $store->id, [$mie->id => 4], NotePaymentMethod::Cash);     // 4.000
    $notes->create($this->sales, BusinessLine::Naraya, $store->id, [$mie->id => 3], NotePaymentMethod::Cash);              // 3.000 not deposited
    $ok = $notes->create($this->sales, BusinessLine::Naraya, $store->id, [$mie->id => 2], NotePaymentMethod::Transfer, UploadedFile::fake()->image('a.jpg'));
    $notes->create($this->sales, BusinessLine::Naraya, $store->id, [$mie->id => 1], NotePaymentMethod::Transfer, UploadedFile::fake()->image('b.jpg'));       // pending 1.000
    $bad = $notes->create($this->sales, BusinessLine::Naraya, $store->id, [$mie->id => 5], NotePaymentMethod::Transfer, UploadedFile::fake()->image('c.jpg'));
    $notes->verify($ok, $this->admin);                                                     // 2.000
    $notes->reject($bad, $this->admin, 'Nominal kurang');                                  // 5.000 owed

    $deposit = CashDeposit::factory()->create(['sales_id' => $this->sales->id, 'status' => CashDepositStatus::Verified, 'expected_amount' => 4000, 'claimed_amount' => 4000, 'verified_at' => now()]);
    $cashA->update(['cash_deposit_id' => $deposit->id]);

    // carried now: 30 - 5 - 15 sold = 10; the stock check finds 8 → shortfall 2 (2.000)
    $stock->stockCheck($this->sales, BusinessLine::Naraya, [$mie->id => 8], $this->admin);
});

it('reconciles a sales person\'s goods against their money for the line', function () {
    $row = app(Reconciliation::class)->rows(BusinessLine::Naraya, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-20'))->sole();

    expect($row['sales']->is($this->sales))->toBeTrue()
        ->and($row['taken'])->toBe(30000)
        ->and($row['returned'])->toBe(5000)
        ->and($row['sold'])->toBe(15000)
        ->and($row['shortfall'])->toBe(2000)
        ->and($row['carried'])->toBe(8000)
        ->and($row['cash'])->toBe(7000)
        ->and($row['cashDeposited'])->toBe(4000)
        ->and($row['cashOpen'])->toBe(3000)
        ->and($row['nonCashVerified'])->toBe(2000)
        ->and($row['nonCashPending'])->toBe(1000)
        ->and($row['nonCashRejected'])->toBe(5000)
        ->and($row['owed'])->toBe(10000)          // 3.000 + 5.000 + 2.000
        ->and($row['matched'])->toBeFalse();
});

it('leaves out activity outside the period and other lines', function () {
    expect(app(Reconciliation::class)->rows(BusinessLine::Naraya, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'))->sole())
        ->toMatchArray(['taken' => 0, 'sold' => 0, 'cash' => 0, 'carried' => 8000, 'owed' => 0, 'matched' => true]);

    expect(app(Reconciliation::class)->rows(BusinessLine::Toys, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-20'))->sole())
        ->toMatchArray(['taken' => 100000, 'sold' => 0, 'carried' => 100000, 'owed' => 0, 'matched' => true]);
});

it('shows the Pencocokan page with a per-sales detail', function () {
    Livewire::actingAs($this->admin)->test(Index::class)
        ->assertSee('Budi')->assertSee('Perlu tindak lanjut')
        ->call('show', $this->sales->id)
        ->assertSee('Nominal kurang');

    $this->actingAs($this->admin)->get('http://'.config('app.admin_domain').'/pencocokan')->assertOk()->assertSee('Pencocokan');
});
