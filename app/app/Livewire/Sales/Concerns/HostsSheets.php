<?php

namespace App\Livewire\Sales\Concerns;

use Livewire\Attributes\On;

/**
 * The sales app's two pages (Kerja, Lainnya) open every other screen as a sheet over themselves
 *; views/livewire/sales/partials/sheet.blade.php maps a
 * sheet name to its component. A sheet's component closes it with `sheet-close` or `sheet-done`
 * (with a message), or swaps it for another with `sheet-open`.
 */
trait HostsSheets
{
    public string $sheet = '';

    /** The record the sheet is about: a store, a note… */
    public ?int $for = null;

    /** Open the store sheet with its account panel ready (after "Toko baru"). */
    public bool $akun = false;

    public ?string $flash = null;

    /** Sheets this page may open; old URLs land here as ?buka=… */
    abstract protected function sheets(): array;

    public function mountHostsSheets(): void
    {
        $buka = (string) request()->query('buka', '');
        if (in_array($buka, $this->sheets(), true)) {
            $this->sheet = $buka;
            $this->for = request()->integer('id') ?: null;
            $this->akun = request()->boolean('akun');
        }
    }

    #[On('sheet-open')]
    public function open(string $sheet, ?int $for = null, bool $akun = false): void
    {
        if (! in_array($sheet, $this->sheets(), true)) {
            return;
        }
        $this->sheet = $sheet;
        $this->for = $for;
        $this->akun = $akun;
        $this->flash = null;
    }

    #[On('sheet-close')]
    public function close(): void
    {
        $this->reset('sheet', 'for', 'akun');
    }

    #[On('sheet-done')]
    public function done(string $message = ''): void
    {
        $this->close();
        $this->flash = $message ?: null;
    }
}
