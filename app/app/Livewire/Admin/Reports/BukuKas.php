<?php

namespace App\Livewire\Admin\Reports;

use App\Livewire\Admin\Reports\Concerns\ExportsTable;
use App\Livewire\Admin\Reports\Concerns\HasDateRangeFilter;
use App\Models\CashBookEntry;
use Illuminate\Support\Collection;
use Livewire\Component;
use App\Livewire\Admin\Concerns\HasAdminShell;

class BukuKas extends Component
{
    use HasDateRangeFilter, ExportsTable, HasAdminShell;

    public function reportTitle(): string
    {
        return 'Laporan Buku Kas';
    }

    public function headings(): array
    {
        return ['Tanggal', 'Tipe', 'Jumlah', 'Kategori', 'Keterangan'];
    }

    public function rows(): Collection
    {
        return CashBookEntry::forLine(\App\Support\ActiveLine::current())->whereBetween('date', [$this->startDate, $this->endDate])
            ->with('expenseCategory')
            ->orderByDesc('date')
            ->get()
            ->map(fn ($entry) => [
                $entry->date->format('d/m/Y'),
                $entry->type->label(),
                'Rp '.number_format($entry->amount, 0, ',', '.'),
                $entry->expenseCategory?->name ?? '-',
                $entry->catatan ?? $entry->reference ?? '-',
            ]);
    }

    public function render()
    {
        return view('livewire.admin.reports.buku-kas')->layout('layouts.admin');
    }
}
