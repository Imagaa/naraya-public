<?php

use Illuminate\Support\Facades\Blade;

it('renders a save button that stays disabled until its fields are dirty', function () {
    $html = Blade::render('<x-ui.save-button target="name,phone">Simpan</x-ui.save-button>');

    expect($html)->toContain('type="submit"')
        ->toContain('disabled')
        ->toContain('wire:dirty.remove.attr="disabled"')
        ->toContain('wire:target="name,phone"');
});

it('is clickable straight away when the server already holds an unsaved change (an uploaded photo)', function () {
    $html = Blade::render('<x-ui.save-button target="photo" :changed="true">Simpan foto</x-ui.save-button>');

    expect($html)->not->toContain('disabled')->not->toContain('wire:dirty');
});

it('renders checkbox, chip and switch controls from one native-input component', function () {
    $html = Blade::render('<x-ui.checkbox label="Libur" wire:model="x" /><x-ui.checkbox chip label="Budi" value="3" wire:model="ids" /><x-ui.checkbox switch label="Aktif" wire:model="on" />');

    expect($html)->toContain('class="check"')->toContain('class="check check-chip"')->toContain('class="check check-switch"')
        ->toContain('role="switch"')
        ->toContain('type="checkbox"')
        ->toContain('value="3"');
});
