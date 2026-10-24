<?php

use App\Enums\BusinessLine;
use App\Enums\CashBookEntryKind;
use App\Enums\CashBookEntryType;
use App\Enums\CashBookSource;
use App\Enums\CashDepositStatus;
use App\Enums\NotePaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Livewire\Admin\CashBook\Index;
use App\Models\BuyerProfile;
use App\Models\CashBookClosing;
use App\Models\CashBookEntry;
use App\Models\CashDeposit;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CashBook\DailyPoster;
use App\Services\CashBook\Ledger;
use App\Support\ActiveLine;
use App\Support\Sales\SalesNotes;
use App\Support\Stock\StockTransfers;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-20 10:00'));
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->ledger = app(Ledger::class);
});

it('posts each line\'s day separately, with verified transfer/QRIS notes as Penjualan non-tunai', function () {
    $day = Carbon::parse('2026-10-19 10:00');
    Order::factory()->create(['payment_method' => 'qris', 'payment_status' => PaymentStatus::Paid, 'paid_at' => $day, 'total' => 120000]);
    CashDeposit::factory()->create(['status' => CashDepositStatus::Verified, 'verified_at' => $day, 'expected_amount' => 50000, 'claimed_amount' => 50000]);
    CashDeposit::factory()->create(['line' => BusinessLine::Toys, 'status' => CashDepositStatus::Verified, 'verified_at' => $day, 'expected_amount' => 70000, 'claimed_amount' => 70000]);

    $sales = User::factory()->create(['role' => Role::Sales]);
    $store = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $store->id, 'sales_user_id' => $sales->id]);
    $toy = Product::factory()->create(['stock' => 5, 'price' => 40000, 'line' => BusinessLine::Toys]);
    app(StockTransfers::class)->pickup($sales, BusinessLine::Toys, [$toy->id => 3]);
    $notes = app(SalesNotes::class);
    $verified = $notes->create($sales, BusinessLine::Toys, $store->id, [$toy->id => 2], NotePaymentMethod::Transfer, UploadedFile::fake()->image('t.jpg'));
    $pending = $notes->create($sales, BusinessLine::Toys, $store->id, [$toy->id => 1], NotePaymentMethod::Transfer, UploadedFile::fake()->image('q.jpg'));
    $this->travelTo($day);
    $notes->verify($verified, $this->admin);
    $this->travelTo(Carbon::parse('2026-10-20 10:00'));

    expect(app(DailyPoster::class)->postDay($day))->toBe(4);
    $rekap = fn (BusinessLine $line, CashBookSource $source) => CashBookEntry::forLine($line)->where('kind', CashBookEntryKind::Rekap)->where('source', $source)->first();

    expect($rekap(BusinessLine::Naraya, CashBookSource::PenjualanOnline)->amount)->toBe(120000)
        ->and($rekap(BusinessLine::Naraya, CashBookSource::SetoranSales)->amount)->toBe(50000)
        ->and($rekap(BusinessLine::Toys, CashBookSource::SetoranSales)->amount)->toBe(70000)
        ->and($rekap(BusinessLine::Toys, CashBookSource::PenjualanNonTunai))
        ->amount->toBe(80000)->catatan->toBe('Penjualan non-tunai · 1 nota')
        ->and($rekap(BusinessLine::Toys, CashBookSource::PenjualanOnline))->toBeNull()
        ->and($rekap(BusinessLine::Naraya, CashBookSource::PenjualanNonTunai))->toBeNull();
    expect(app(DailyPoster::class)->postDay($day))->toBe(0);
});

it('keeps balances, manual entries and closings per line', function () {
    CashBookEntry::factory()->create(['date' => '2026-09-10', 'amount' => 1000, 'type' => CashBookEntryType::Pemasukan]);
    CashBookEntry::factory()->create(['date' => '2026-09-10', 'amount' => 400, 'type' => CashBookEntryType::Pemasukan, 'line' => BusinessLine::Toys]);

    expect($this->ledger->summary(Carbon::parse('2026-10-01'))['opening'])->toBe(1000);
    ActiveLine::set(BusinessLine::Toys);
    expect($this->ledger->summary(Carbon::parse('2026-10-01'))['opening'])->toBe(400);

    $entry = $this->ledger->record(['type' => CashBookEntryType::Pemasukan, 'amount' => 50, 'date' => '2026-10-20', 'catatan' => 'Jual mainan'], $this->admin);
    expect($entry->line)->toBe(BusinessLine::Toys);

    $this->ledger->close(Carbon::parse('2026-09-01'), $this->admin);
    expect(CashBookClosing::forLine(BusinessLine::Toys)->sole()->closing_balance)->toBe(400)
        ->and(Ledger::isClosed('2026-09-15'))->toBeTrue()
        ->and(Ledger::isClosed('2026-09-15', BusinessLine::Naraya))->toBeFalse();

    ActiveLine::set(BusinessLine::Naraya);
    $this->ledger->close(Carbon::parse('2026-09-01'), $this->admin);
    expect(CashBookClosing::forLine(BusinessLine::Naraya)->sole()->closing_balance)->toBe(1000);
});

it('auto closes each line', function () {
    CashBookEntry::factory()->create(['date' => '2026-09-10', 'amount' => 100, 'type' => CashBookEntryType::Pemasukan]);
    CashBookEntry::factory()->create(['date' => '2026-09-10', 'amount' => 300, 'type' => CashBookEntryType::Pemasukan, 'line' => BusinessLine::Toys]);

    expect($this->ledger->closeDue())->toBe(2);
    expect(CashBookClosing::orderBy('line')->pluck('closing_balance', 'line')->all())->toBe(['naraya' => 100, 'toys' => 300]);
});

it('shows only the active line on the Buku Kas page', function () {
    CashBookEntry::factory()->create(['date' => '2026-10-10', 'amount' => 1000, 'type' => CashBookEntryType::Pemasukan, 'catatan' => 'Kas Naraya']);
    CashBookEntry::factory()->create(['date' => '2026-10-10', 'amount' => 400, 'type' => CashBookEntryType::Pemasukan, 'catatan' => 'Kas Toys', 'line' => BusinessLine::Toys]);

    Livewire::actingAs($this->admin)->test(Index::class)->assertSee('Kas Naraya')->assertDontSee('Kas Toys');
    ActiveLine::set(BusinessLine::Toys);
    Livewire::actingAs($this->admin)->test(Index::class)->assertSee('Kas Toys')->assertDontSee('Kas Naraya');
});
