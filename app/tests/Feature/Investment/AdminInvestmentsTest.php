<?php

use App\Enums\Role;
use App\Livewire\Admin\Investments\Index;
use App\Models\BuyerProfile;
use App\Models\InvestmentPurchase;
use App\Models\User;
use App\Support\Admin\AdminNav;
use App\Support\Investment\Investments;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-09 03:00'));
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi']);
    $this->store = User::factory()->create(['role' => Role::User, 'name' => 'Toko Maju']);
    BuyerProfile::factory()->create(['user_id' => $this->store->id, 'sales_user_id' => $this->sales->id]);
    $inv = app(Investments::class);
    $this->active = $inv->buyViaSales($this->sales, $this->store->id, 10);
    $inv->activate($this->active, $this->admin);
    $this->waiting = $inv->buy($this->store, 2, 'transfer', UploadedFile::fake()->image('b.jpg'));
    $this->paid = $inv->buyViaSales($this->sales, $this->store->id, 3);
    $this->travelTo(Carbon::parse('2026-12-10 03:00')); // a first purchase pays from month 2
    $inv->payDue();
    $this->withdrawal = $inv->requestWithdrawal($this->store, 100000, 'BCA', '1234567', 'Budi Santoso');
});

it('summarises the program on the Ringkasan tab', function () {
    Livewire::actingAs($this->admin)->test(Index::class)
        ->assertViewHas('stats', fn ($s) => $s['active_packages'] === 10 && $s['forward_packages'] === 3
            && $s['forward_amount'] === 2100000 && $s['wallets'] === 180000 && $s['withdrawals_waiting'] === 1)
        ->assertSee('Belum disalurkan ke mitra')->assertSee($this->paid->number())->assertSee('Toko Maju');
});

it('verifies a proof, passes a paid purchase on to the partner, and handles a withdrawal', function () {
    Livewire::actingAs($this->admin)->test(Index::class, ['tab' => 'pembelian'])
        ->call('verify', $this->waiting->id)
        ->call('startAct', 'activate', $this->paid->id)->set('partnerRef', 'MITRA-1')->call('activate');
    expect($this->waiting->fresh()->status)->toBe('dibayar')
        ->and($this->paid->fresh())->status->toBe('aktif')->partner_ref->toBe('MITRA-1');

    $this->travelBack(); // Livewire's temporary uploads expire against the real clock
    Livewire::actingAs($this->admin)->test(Index::class, ['tab' => 'penarikan'])
        ->call('startAct', 'transfer', $this->withdrawal->id)->call('transfer')->assertHasErrors('transferProof')
        ->set('transferProof', UploadedFile::fake()->image('tf.jpg'))->call('transfer')->assertHasNoErrors();
    expect($this->withdrawal->fresh()->status)->toBe('ditransfer');
});

it('rejects a proof with a reason', function () {
    Livewire::actingAs($this->admin)->test(Index::class, ['tab' => 'pembelian'])
        ->call('startAct', 'reject', $this->waiting->id)->call('reject')->assertHasErrors('reason')
        ->set('reason', 'Nominal kurang')->call('reject')->assertHasNoErrors();
    expect($this->waiting->fresh()->status)->toBe('ditolak');
});

it('badges the sidebar with what needs admin', function () {
    \Illuminate\Support\Once::flush();
    expect(AdminNav::badges()['Investasi'])->toBe(3); // 1 proof, 1 paid not passed on, 1 withdrawal
    $this->actingAs($this->admin)->get('http://'.config('app.admin_domain').'/investasi?tab=investor')->assertOk()->assertSee('Toko Maju');
});
