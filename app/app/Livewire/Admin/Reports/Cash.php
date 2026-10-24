<?php

namespace App\Livewire\Admin\Reports;

use App\Enums\CashCollectionStatus;
use App\Livewire\Admin\Reports\Concerns\ExportsTable;
use App\Livewire\Admin\Reports\Concerns\HasDateRangeFilter;
use App\Models\CashCollection;
use App\Models\CashDeposit;
use Illuminate\Support\Collection;
use Livewire\Component;
use App\Livewire\Admin\Concerns\HasAdminShell;

class Cash extends Component
{
    use HasDateRangeFilter, ExportsTable, HasAdminShell;

    public function reportTitle(): string
    {
        return 'Laporan Cash dan Setoran';
    }

    public function headings(): array
    {
        return ['Tanggal', 'Sales', 'Diharapkan', 'Diklaim', 'Status', 'Catatan Resolusi'];
    }

    public function rows(): Collection
    {
        return CashDeposit::forLine(\App\Support\ActiveLine::current())->whereBetween('created_at', [$this->rangeStart(), $this->rangeEnd()])
            ->with('salesUser')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($deposit) => [
                $deposit->created_at->format('d/m/Y'),
                $deposit->salesUser->name,
                'Rp '.number_format($deposit->expected_amount, 0, ',', '.'),
                'Rp '.number_format($deposit->claimed_amount, 0, ',', '.'),
                $deposit->status->label(),
                $deposit->resolution_note ?? '-',
            ]);
    }

    public function render()
    {
        return view('livewire.admin.reports.cash', [
            'cashUnremitted' => CashCollection::where('status', CashCollectionStatus::Held)->sum('amount'),
        ])->layout('layouts.admin');
    }
}
