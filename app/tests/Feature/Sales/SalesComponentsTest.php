<?php

it('renders a page header with an optional sheet close button and actions', function () {
    $this->blade('<x-sales.page title="Absensi" back="1"><x-slot:actions><button>Aksi</button></x-slot:actions><p>isi halaman</p></x-sales.page>')
        ->assertSee('<h1>Absensi</h1>', false)
        ->assertSee('aria-label="Tutup"', false)
        ->assertSee('history.back()', false)
        ->assertSee('Aksi')
        ->assertSee('isi halaman');

    $this->blade('<x-sales.page title="Beranda"><p>x</p></x-sales.page>')
        ->assertDontSee('aria-label="Tutup"', false);
});

it('renders the target hero with a native progress bar capped at 100', function () {
    $this->blade('<x-sales.hero :realized="2500000" :target="10000000" />')
        ->assertSee('Rp 2.500.000')
        ->assertSee('Rp 10.000.000')
        ->assertSee('<progress max="100" value="25"', false);

    $this->blade('<x-sales.hero :realized="5" :target="0" />')->assertSee('value="0"', false);
    $this->blade('<x-sales.hero :realized="300" :target="100" />')->assertSee('value="100"', false);
});

it('renders a row as a link only when given an href', function () {
    $html = (string) $this->blade('<x-sales.row href="/toko/1"><b>Toko A</b><x-slot:meta>kemarin</x-slot:meta></x-sales.row>');
    expect($html)->toContain('<a')->toContain('href="/toko/1"')->toContain('class="sales-row"')->toContain('kemarin');

    $html = (string) $this->blade('<x-sales.row as="button" type="button"><b>Notif</b></x-sales.row>');
    expect($html)->toContain('<button')->not->toContain('<a');
});

it('shows only the contact actions the store data supports', function () {
    $this->blade('<x-sales.contact phone="0812-345" lat="-6.2" lng="106.8" />')
        ->assertSee('href="tel:+62812345"', false)
        ->assertSee('href="https://wa.me/62812345"', false)
        ->assertSee('destination=-6.2%2C106.8', false);

    $this->blade('<x-sales.contact address="Jl. Anggrek 4" />')
        ->assertDontSee('tel:', false)
        ->assertDontSee('wa.me', false)
        ->assertSee('destination=Jl.+Anggrek+4', false);

    expect(trim((string) $this->blade('<x-sales.contact />')))->toBe('');
});

it('marks the current delivery stage and the ones before it', function () {
    $html = (string) $this->blade('<x-sales.stepper stage="ready_to_ship" />');

    expect($html)
        ->toMatch('/class="is-done"[^>]*>Menunggu Gudang</')
        ->toMatch('/class="is-done"[^>]*>Perlu Diambil</')
        ->toMatch('/class="is-current"[^>]*aria-current="step"[^>]*>Siap Kirim</')
        ->toMatch('/<li class=""\s*>Dalam Perjalanan</'); // @class with nothing true still renders class=""
});

it('renders a field error only when the field has one', function () {
    $this->withViewErrors(['photo' => 'Foto wajib.'])
        ->blade('<x-ui.field-error name="photo" />')
        ->assertSee('<span class="field-error">Foto wajib.</span>', false);

    expect(trim((string) $this->withViewErrors([])->blade('<x-ui.field-error name="photo" />')))->toBe('');
});
