<?php

use Illuminate\Support\Facades\Blade;
use Symfony\Component\Finder\Finder;

it('renders a styled box around a hidden native file input', function () {
    $html = Blade::render('<x-ui.file-picker wire:model="proof" label="Pilih foto bukti" hint="Maks. 5 MB" capture="environment" />');

    expect($html)
        ->toContain('class="file-pick"')
        ->toContain('type="file"')
        ->toContain('accept="image/*"')
        ->toContain('capture="environment"')
        ->toContain('wire:model="proof"')
        ->toContain('visually-hidden')
        ->toContain('Pilih foto bukti')
        ->toContain('Maks. 5 MB')
        ->toContain('wire:target="proof"');
});

it('shows the saved photo with a replace hint', function () {
    $html = Blade::render('<x-ui.file-picker wire:model="photo" current="https://cdn.test/a.jpg" hint="Maks. 5 MB" />');

    expect($html)->toContain('src="https://cdn.test/a.jpg"')->toContain('Ketuk untuk mengganti')->not->toContain('Maks. 5 MB');
});

it('leaves no browser-default file input in the views', function () {
    $raw = collect(Finder::create()->files()->in(resource_path('views'))->name('*.blade.php')->notPath('components/ui'))
        ->filter(fn ($f) => str_contains($f->getContents(), 'type="file"'))
        ->map(fn ($f) => $f->getRelativePathname())
        ->values();

    expect($raw->all())->toBe([]);
});
