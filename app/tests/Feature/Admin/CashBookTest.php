<?php

use App\Enums\CashBookEntryKind;
use App\Enums\CashBookEntryType;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Livewire\Admin\CashBook\Index;
use App\Models\CashBookClosing;
use App\Models\CashBookEntry;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\User;
use App\Services\CashBook\DailyPoster;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-20 10:00'));
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->cat = ExpenseCategory::factory()->create(['name' => 'Transport']);
});

function cashPage(?string $month = null)
{
    return Livewire::actingAs(test()->admin)->withQueryParams($month ? ['bulan' => $month] : [])->test(Index::class);
}

it('shows the month KPIs and the running saldo', function () {
    CashBookEntry::factory()->create(['date' => '2026-09-05', 'amount' => 1000, 'type' => CashBookEntryType::Pemasukan]);
    CashBookEntry::factory()->create(['date' => '2026-10-02', 'amount' => 300, 'type' => CashBookEntryType::Pemasukan, 'catatan' => 'Jual kardus']);
    CashBookEntry::factory()->create(['date' => '2026-10-03', 'amount' => 100, 'type' => CashBookEntryType::Pengeluaran, 'expense_category_id' => $this->cat->id, 'catatan' => 'Bensin']);

    cashPage()
        ->assertViewHas('summary', fn ($s) => $s['opening'] === 1000 && $s['in'] === 300 && $s['out'] === 100 && $s['closing'] === 1200)
        ->assertViewHas('rows', fn ($rows) => $rows->first()['saldo'] === 1200 && $rows->last()['saldo'] === 1300)
        ->assertSee('Bensin')->assertSee('Transport');
});

it('navigates by month', function () {
    CashBookEntry::factory()->create(['date' => '2026-09-05', 'catatan' => 'Lama september']);

    cashPage('2026-09')->assertSee('September 2026')->assertSee('Lama september');
    cashPage()->assertDontSee('Lama september');
});

it('shows today\'s unposted sums on the current month only', function () {
    Order::factory()->create(['payment_method' => 'qris', 'payment_status' => PaymentStatus::Paid, 'paid_at' => now(), 'total' => 4000]);

    cashPage()->assertSee('Hari ini (belum dibukukan)')->assertSee('Penjualan online');
    cashPage('2026-09')->assertDontSee('Hari ini (belum dibukukan)');
});

it('records an entry only after the review step is confirmed', function () {
    $page = cashPage()->call('openCreate')
        ->set('formType', 'pengeluaran')->set('formAmount', '75000')->set('formDate', '2026-10-20')
        ->set('formCategoryId', $this->cat->id)->set('formNote', 'Bensin motor')
        ->call('review')->assertSet('step', 'confirm')->assertSee('Rp 75.000');

    $page->call('save')->assertHasErrors('confirmed');
    expect(CashBookEntry::count())->toBe(0);

    $page->set('confirmed', true)->call('save')->assertHasNoErrors()->assertDispatched('cash-close', name: 'entry');
    expect(CashBookEntry::first())->amount->toBe(75000)->user_id->toBe($this->admin->id);
});

it('warns loudly about a big amount on the review step', function () {
    cashPage()->call('openCreate')->set('formType', 'pemasukan')->set('formAmount', '5000000')
        ->set('formDate', '2026-10-20')->set('formNote', 'Modal')->call('review')->assertSee('Nominal besar');
});

it('validates the form before the review step', function () {
    cashPage()->call('openCreate')->set('formType', 'pengeluaran')->set('formAmount', '')->call('review')
        ->assertHasErrors(['formAmount', 'formNote', 'formCategoryId'])->assertSet('step', 'form');
});

it('lets the author edit and delete inside the window, then offers Koreksi', function () {
    $e = CashBookEntry::factory()->create(['date' => '2026-10-20', 'amount' => 1000, 'user_id' => $this->admin->id, 'catatan' => 'Salah']);

    cashPage()->assertSee('bisa diubah')->call('openEdit', $e->id)->set('formAmount', '900')->call('review')->set('confirmed', true)->call('save');
    expect($e->fresh()->amount)->toBe(900);

    $this->travel(16)->minutes();
    cashPage()->assertDontSee('bisa diubah')->assertSeeHtml('wire:click="openCorrection('.$e->id.')"')
        ->call('delete', $e->id)->assertHasErrors('cash');
});

it('posts a correction from the dialog', function () {
    $e = CashBookEntry::factory()->create(['date' => '2026-10-02', 'amount' => 500, 'type' => CashBookEntryType::Pemasukan]);

    cashPage()->call('openCorrection', $e->id)->set('correctionMode', 'amount')->set('correctionAmount', '450')
        ->set('correctionReason', 'Salah hitung')->call('saveCorrection')->assertHasNoErrors();

    expect(CashBookEntry::where('kind', CashBookEntryKind::Koreksi)->first())->type->toBe(CashBookEntryType::Pengeluaran)->amount->toBe(50);
});

it('expands a summary row to its orders', function () {
    $order = Order::factory()->create(['payment_method' => 'qris', 'payment_status' => PaymentStatus::Paid, 'paid_at' => '2026-10-05 08:00', 'total' => 1000]);
    app(DailyPoster::class)->postDay(Carbon::parse('2026-10-05'));

    cashPage()->assertSee('Penjualan online · 1 transaksi')->assertSeeHtml('href="'.route('admin.orders.show', $order).'"');
});

it('closes a past month from the page and shows it read-only', function () {
    CashBookEntry::factory()->create(['date' => '2026-09-05', 'amount' => 1000]);

    cashPage('2026-09')->assertSee('Tutup buku September')->call('closeMonth')->assertHasNoErrors();
    expect(CashBookClosing::count())->toBe(1);
    cashPage('2026-09')->assertSee('Ditutup')->assertDontSee('Tutup buku September')->assertDontSee('Catat transaksi');
});

it('keeps every dialog open across re-renders', function () {
    cashPage()->assertSeeHtml('<dialog id="cash-entry"')->assertSeeHtml('wire:ignore.self');
});

it('blocks non-admin roles from the cash book', function () {
    $this->actingAs(User::factory()->create(['role' => Role::Sales]))
        ->get('http://'.config('app.admin_domain').'/cash-book')->assertStatus(403);
});

it('links an old per-transaction row straight to its order or deposit', function () {
    $order = Order::factory()->create();
    CashBookEntry::factory()->create(['date' => '2026-10-02', 'kind' => CashBookEntryKind::Rekap, 'reference' => 'Order #'.$order->id, 'catatan' => null]);

    cashPage()->assertSeeHtml('href="'.route('admin.orders.show', $order).'"')->assertSee('Order #'.$order->id);
});
