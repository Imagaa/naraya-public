<?php

use Illuminate\Support\Facades\Blade;

it('renders a primary button with the design system class', function () {
    $html = Blade::render('<x-ui.button variant="primary">Simpan</x-ui.button>');

    expect($html)->toContain('btn btn-primary')->toContain('Simpan');
});

it('renders a button with an extra class merged in, not replaced', function () {
    $html = Blade::render('<x-ui.button variant="primary" class="btn-block">Simpan</x-ui.button>');

    expect($html)->toContain('btn btn-primary btn-block');
});

it('renders an input with label and error slot', function () {
    $html = Blade::render('<x-ui.input label="Email" type="email" wire:model="email" />');

    expect($html)->toContain('class="input"')->toContain('type="email"')->toContain('Email');
});

it('renders a tag with the requested variant', function () {
    $html = Blade::render('<x-ui.tag variant="accent">Baru</x-ui.tag>');

    expect($html)->toContain('tag tag-accent')->toContain('Baru');
});

it('renders a table with headers and responsive column classes', function () {
    $html = Blade::render(<<<'BLADE'
        <x-ui.table :headers="['Nama', 'Kategori', 'Stok']">
            <tr><td>Mie Instan</td><td>Sembako</td><td>10</td></tr>
        </x-ui.table>
    BLADE);

    expect($html)->toContain('class="table"')->toContain('Mie Instan')->toContain('Stok');
});
