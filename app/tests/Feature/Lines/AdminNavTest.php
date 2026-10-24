<?php

use App\Enums\BusinessLine;
use App\Support\ActiveLine;
use App\Support\Admin\AdminNav;

function navLabels(): array
{
    return collect(AdminNav::groups())->mapWithKeys(fn ($g) => [$g[0] ?: '—'.count($g[1]) => collect($g[1])->pluck(2)->all()])->all();
}

it('moves the marketplace pages into an on-hold group while the marketplace is closed (Naraya only)', function () {
    config(['naraya.marketplace_purchasing_open' => false]);
    $nav = navLabels();

    expect($nav['Marketplace (ditahan)'])->toBe(['Pesanan', 'Retur & Komplain', 'Penukaran Hadiah', 'Katalog Hadiah'])
        ->and($nav['Penjualan'])->not->toContain('Pesanan')
        ->and($nav)->not->toHaveKey('Loyalty');

    ActiveLine::set(BusinessLine::Toys);
    expect(navLabels())->not->toHaveKey('Marketplace (ditahan)');
});

it('keeps the marketplace pages in their own groups while the marketplace is open', function () {
    config(['naraya.marketplace_purchasing_open' => true]);
    $nav = navLabels();

    expect($nav['Penjualan'])->toContain('Pesanan')->toContain('Retur & Komplain')
        ->and($nav['Loyalty'])->toBe(['Penukaran Hadiah', 'Katalog Hadiah'])
        ->and($nav)->not->toHaveKey('Marketplace (ditahan)');
});

it('only lists pages whose routes exist', function () {
    foreach (AdminNav::groups() as [, $links]) {
        foreach ($links as $link) {
            expect(\Illuminate\Support\Facades\Route::has($link[0]))->toBeTrue();
        }
    }
});
