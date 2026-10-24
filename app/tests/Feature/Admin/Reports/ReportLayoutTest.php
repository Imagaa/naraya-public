<?php

use App\Enums\Role;
use App\Livewire\Admin\Reports;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->travelTo(Carbon::parse('2026-10-20 05:00')); // 12:00 WIB
});

function reportHead(string $html): string
{
    preg_match('/<div class="adm-list-head".*?<\/div>/s', $html, $m);

    return $m[0] ?? '';
}

it('renders every report on the shared admin layout with fixed columns', function (string $component) {
    $page = Livewire::actingAs($this->admin)->test($component);
    $html = $page->html();

    expect($html)->toContain('class="adm-page-head"')->toContain('class="adm-list"')->toContain('wire:click="exportExcel"')
        ->not->toContain('<table')->not->toContain('<button wire:click');
    $head = reportHead($html);
    expect($head)->not->toBe('');

    expect(reportHead($page->set('startDate', '2025-01-01')->set('endDate', '2025-01-31')->html()))->toBe($head);
})->with([
    Reports\Penjualan::class, Reports\OrderReport::class, Reports\Stok::class, Reports\Cash::class, Reports\Absensi::class,
    Reports\TargetSales::class, Reports\BukuKas::class, Reports\Loyalty::class, Reports\Komplain::class,
]);

it('fills the dates from a quick period pill and marks it active', function () {
    $page = Livewire::actingAs($this->admin)->test(Reports\Penjualan::class)
        ->assertSeeHtml('aria-pressed="true">Bulan ini')
        ->call('setPeriod', 'last_month')
        ->assertSet('startDate', '2026-09-01')->assertSet('endDate', '2026-09-30')
        ->assertSeeHtml('aria-pressed="true">Bulan lalu');

    $page->call('setPeriod', 'last_30')->assertSet('startDate', '2026-09-21')->assertSet('endDate', '2026-10-20');
});

it('lists every report on the Laporan page as a link row with a description', function () {
    Livewire::actingAs($this->admin)->test(Reports\Index::class)
        ->assertSeeHtml('href="'.route('admin.reports.penjualan').'"')
        ->assertSee('Order selesai dan omzet')
        ->assertSeeHtml('aria-pressed="true">30 hari')
        ->assertDontSeeHtml('<ul>');
});
