<?php

use Illuminate\Support\Facades\Blade;

it('renders a page header with a subtitle and actions', function () {
    $html = Blade::render('<x-admin.page-header title="Pesanan">12 aktif<x-slot:actions><a href="#">Tambah</a></x-slot:actions></x-admin.page-header>');

    expect($html)->toContain('class="adm-page-head"')->toContain('<h1>Pesanan</h1>')->toContain('12 aktif')->toContain('Tambah');
});

it('renders a queue row as one link carrying its severity', function () {
    $html = Blade::render('<x-admin.queue-row href="/orders?antrean=new-orders" label="Order baru" meta="Tertua 5 jam" :count="7" severity="late" />');

    expect($html)
        ->toContain('href="/orders?antrean=new-orders"')
        ->toContain('adm-queue-row is-late')
        ->toContain('Order baru')->toContain('Tertua 5 jam')
        ->toContain('<span class="adm-queue-count">7</span>')
        ->toContain('Lewat batas'); // screen-reader text, colour is not the only signal
});

it('renders a kpi with a native progress bar only when progress is given', function () {
    expect(Blade::render('<x-admin.kpi label="Omzet" value="Rp 1 jt" :progress="62" />'))->toContain('<progress class="adm-kpi-bar" max="100" value="62"');
    expect(Blade::render('<x-admin.kpi label="Omzet" value="Rp 1 jt" />'))->not->toContain('<progress');
});

it('renders the queue chip only when a label is given', function () {
    expect(Blade::render('<x-admin.queue-chip :label="null" />'))->not->toContain('adm-chip');
    expect(Blade::render('<x-admin.queue-chip label="Review pembayaran" />'))
        ->toContain('Antrean: Review pembayaran')->toContain('wire:click="clearQueue"');
});

it('renders a titled panel with actions', function () {
    $html = Blade::render('<x-admin.panel title="Toko"><x-slot:actions><a href="#">Ubah</a></x-slot:actions> Isi</x-admin.panel>');

    expect($html)->toContain('class="adm-panel"')->toContain('<h2>Toko</h2>')->toContain('Ubah')->toContain('Isi');
});

it('colours an order status pill from one map', function () {
    expect(Blade::render('<x-admin.status-pill :status="$s" />', ['s' => \App\Enums\OrderStatus::New]))->toContain('adm-pill tone-new')->toContain('Baru');
    expect(Blade::render('<x-admin.status-pill :status="$s" />', ['s' => \App\Enums\OrderStatus::Cancelled]))->toContain('tone-bad')->toContain('Dibatalkan');
});

it('renders timeline steps with done state, time, actor and photo', function () {
    $html = Blade::render('<x-admin.timeline :steps="$steps" />', ['steps' => [
        ['label' => 'Dipesan', 'done' => true, 'time' => now()->setDate(2026, 10, 4)->setTime(1, 1), 'by' => 'Toko A'],
        ['label' => 'Diantar', 'done' => false, 'photo' => 'https://cdn.example/foto.jpg'],
    ]]);

    expect($html)->toContain('class="is-done"')->toContain('Dipesan')->toContain('4 Okt 2026 08.01') // stored UTC, shown WIB->toContain('Toko A')
        ->toContain('href="https://cdn.example/foto.jpg"');
});

it('renders a list with a desktop header and link rows', function () {
    $html = Blade::render('<x-admin.list cols="1fr 2fr" :headers="[\'No.\', \'Toko\']"><x-admin.list-row href="/orders/1"><span>#1</span><span>Toko A</span></x-admin.list-row></x-admin.list>');

    expect($html)->toContain('style="--cols: 1fr 2fr; --l1: &#039;No.&#039;; --l2: &#039;Toko&#039;"')->toContain('<span>No.</span>')->toContain('href="/orders/1"')->toContain('adm-list-row');
});

it('renders filter pills with counts and the active one pressed', function () {
    $html = Blade::render('<x-admin.filter-pills :options="$o" active="new" action="setStatusFilter" />', ['o' => ['all' => ['Semua', 3], 'new' => ['Baru', 1], 'x' => ['Tanpa angka', null]]]);

    expect($html)
        ->toContain('wire:click="setStatusFilter(\'new\')"')
        ->toContain('class="adm-pill-btn is-active" aria-pressed="true">Baru <span>1</span>')
        ->toContain('aria-pressed="false">Semua <span>3</span>')
        ->toContain('>Tanpa angka</button>');
});

it('renders a static row with an actions cell when there is no href', function () {
    $html = Blade::render('<x-admin.list cols="1fr auto"><x-admin.list-row><span class="adm-cell-main">A</span><span class="adm-cell-actions"><button>Setujui</button></span></x-admin.list-row></x-admin.list>');

    expect($html)->toContain('<div class="adm-list-row is-static">')->toContain('adm-cell-actions')->not->toContain('<a ');
});

it('keeps the list header when there are no rows', function () {
    $html = Blade::render('<x-admin.list cols="1fr 1fr" :headers="[\'Nama\', \'Status\']" :empty="true" empty-message="Belum ada data." />');

    expect($html)->toContain('<span>Nama</span>')->toContain('class="adm-list-empty"')->toContain('Belum ada data.');
});

it('renders a generic pill and the order status pill through it', function () {
    expect(Blade::render('<x-admin.pill tone="ok">Aktif</x-admin.pill>'))->toContain('class="adm-pill tone-ok"')->toContain('Aktif');
    expect(Blade::render('<x-admin.status-pill :status="$s" />', ['s' => \App\Enums\OrderStatus::Completed]))->toContain('adm-pill tone-ok');
});
