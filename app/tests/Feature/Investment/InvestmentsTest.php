<?php

use App\Enums\Role;
use App\Models\BuyerProfile;
use App\Models\InvestmentPurchase;
use App\Models\User;
use App\Support\Investment\Investments;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-09 03:00')); // 10:00 WIB
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi']);
    $this->store = User::factory()->create(['role' => Role::User, 'name' => 'Toko Maju']);
    BuyerProfile::factory()->create(['user_id' => $this->store->id, 'sales_user_id' => $this->sales->id]);
    $this->inv = app(Investments::class);
});

it('buys by transfer with a proof: waits for verification, then for being passed on, then pays monthly on the activation date from month 2', function () {
    expect(fn () => $this->inv->buy($this->store, 10, 'transfer', null))->toThrow(ValidationException::class);

    $p = $this->inv->buy($this->store, 10, 'transfer', UploadedFile::fake()->image('bukti.jpg'));
    expect($p)->status->toBe('menunggu_verifikasi')->amount->toBe(7000000)->number()->toBe('INV-'.str_pad($p->id, 4, '0', STR_PAD_LEFT));

    $this->inv->verify($p, $this->admin);
    expect($p->fresh()->status)->toBe('dibayar');

    $this->inv->activate($p->fresh(), $this->admin, 'MITRA-7781');
    $p = $p->fresh();
    expect($p)->status->toBe('aktif')->partner_ref->toBe('MITRA-7781')
        ->and($p->next_payout_on->format('Y-m-d'))->toBe('2026-12-09'); // the store's first purchase skips month 1

    $this->travelTo(Carbon::parse('2026-11-10 03:00'));
    expect($this->inv->payDue())->toBe(0); // not yet

    $this->travelTo(Carbon::parse('2026-12-09 01:00')); // 08:00 WIB on the 9th
    expect($this->inv->payDue())->toBe(1)
        ->and($this->inv->payDue())->toBe(0) // idempotent
        ->and($this->inv->balance($this->store))->toBe(280000)
        ->and($p->fresh()->months_paid)->toBe(1)
        ->and($p->fresh()->next_payout_on->format('Y-m-d'))->toBe('2027-01-09');
});

it('catches up missed months: a first purchase pays 11 times from month 2, later ones 12 times from month 1, principal back in month 12', function () {
    $first = $this->inv->buyViaSales($this->sales, $this->store->id, 1);
    $second = $this->inv->buyViaSales($this->sales, $this->store->id, 1);
    expect($first)->months->toBe(11)->skipped_months->toBe(1)
        ->and($second)->months->toBe(12)->skipped_months->toBe(0);
    $this->inv->activate($first, $this->admin);
    $this->inv->activate($second, $this->admin);
    expect($second->fresh()->next_payout_on->format('Y-m-d'))->toBe('2026-11-09');

    $this->travelTo(Carbon::parse('2027-10-08 03:00')); // the day before month 12
    Artisan::call('investments:pay');
    expect($first->fresh()->months_paid)->toBe(10)->and($second->fresh()->months_paid)->toBe(11);

    $this->travelTo(Carbon::parse('2027-10-10 03:00'));
    Artisan::call('investments:pay');
    expect($first->fresh())->status->toBe('selesai')->months_paid->toBe(11)->next_payout_on->toBeNull()
        ->and($second->fresh())->status->toBe('selesai')->months_paid->toBe(12)
        ->and($this->inv->balance($this->store))->toBe(11 * 28000 + 12 * 28000 + 2 * 700000);
});

it('gives the first-purchase terms only to a store that has not bought yet (a rejected purchase does not count)', function () {
    expect($this->inv->termsFor(null))->first->toBeTrue()->months->toBe(11)->term->toBe(12)
        ->and($this->inv->termsFor($this->store))->first->toBeTrue();

    $rejected = $this->inv->buy($this->store, 1, 'qris', UploadedFile::fake()->image('q.jpg'));
    $this->inv->reject($rejected, $this->admin, 'Bukti buram');
    expect($this->inv->termsFor($this->store))->first->toBeTrue();

    $this->inv->buy($this->store, 1, 'qris', UploadedFile::fake()->image('q.jpg'));
    expect($this->inv->termsFor($this->store))->first->toBeFalse()->months->toBe(12)->skip->toBe(0);
});

it('pays for a package from the wallet, and refuses more than the balance', function () {
    $seed = $this->inv->buyViaSales($this->sales, $this->store->id, 5);
    $this->inv->activate($seed, $this->admin);
    $this->travelTo(Carbon::parse('2027-10-10 03:00'));
    $this->inv->payDue(); // first purchase: 5 × (11 × 28.000 + 700.000) = 5.040.000

    expect(fn () => $this->inv->buy($this->store, 8, 'wallet'))->toThrow(ValidationException::class); // 5.6M > 5.04M
    $p = $this->inv->buy($this->store, 7, 'wallet');
    expect($p)->status->toBe('dibayar')->months->toBe(12)->and($this->inv->balance($this->store))->toBe(5040000 - 4900000);
});

it('records cash bought through the assigned sales person only', function () {
    $p = $this->inv->buyViaSales($this->sales, $this->store->id, 3);
    expect($p)->status->toBe('dibayar')->payment_method->toBe('cash_sales')->sales_id->toBe($this->sales->id)->amount->toBe(2100000);

    $other = User::factory()->create(['role' => Role::User]);
    expect(fn () => $this->inv->buyViaSales($this->sales, $other->id, 1))->toThrow(ValidationException::class);
});

it('withdraws from the wallet any time: debited at once, refunded if admin rejects, done when admin transferred', function () {
    $p = $this->inv->buyViaSales($this->sales, $this->store->id, 1);
    $this->inv->activate($p, $this->admin);
    $this->travelTo(Carbon::parse('2027-02-10 03:00'));
    $this->inv->payDue(); // 3 × 28.000 (Dec, Jan, Feb: a first purchase starts in month 2)

    expect(fn () => $this->inv->requestWithdrawal($this->store, 100000, 'BCA', '1234', 'Budi'))->toThrow(ValidationException::class);
    $w = $this->inv->requestWithdrawal($this->store, 50000, 'BCA', '1234567', 'Budi Santoso');
    expect($this->inv->balance($this->store))->toBe(34000);

    expect(fn () => $this->inv->rejectWithdrawal($w, $this->admin, ''))->toThrow(ValidationException::class);
    $this->inv->rejectWithdrawal($w, $this->admin, 'Nama rekening tidak sesuai');
    expect($w->fresh()->status)->toBe('ditolak')->and($this->inv->balance($this->store))->toBe(84000);

    $w2 = $this->inv->requestWithdrawal($this->store, 84000, 'BCA', '1234567', 'Budi Santoso');
    $this->inv->markTransferred($w2, $this->admin, UploadedFile::fake()->image('tf.jpg'));
    expect($w2->fresh())->status->toBe('ditransfer')->proof_path->not->toBeNull()
        ->and($this->inv->balance($this->store))->toBe(0);
});

it('rejects a transfer proof with a reason and never activates it', function () {
    $p = $this->inv->buy($this->store, 1, 'qris', UploadedFile::fake()->image('q.jpg'));
    expect(fn () => $this->inv->reject($p, $this->admin, ''))->toThrow(ValidationException::class);
    $this->inv->reject($p, $this->admin, 'Bukti buram');
    expect($p->fresh())->status->toBe('ditolak')->reject_reason->toBe('Bukti buram');
    expect(fn () => $this->inv->activate($p->fresh(), $this->admin))->toThrow(ValidationException::class);
    expect(InvestmentPurchase::where('status', 'aktif')->count())->toBe(0);
});
