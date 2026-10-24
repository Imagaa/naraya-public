<?php

namespace App\Livewire\Admin\Reports;

use App\Livewire\Admin\Reports\Concerns\ExportsTable;
use App\Livewire\Admin\Reports\Concerns\HasDateRangeFilter;
use App\Models\Complaint;
use Illuminate\Support\Collection;
use Livewire\Component;
use App\Livewire\Admin\Concerns\HasAdminShell;

class Komplain extends Component
{
    use HasDateRangeFilter, ExportsTable, HasAdminShell;

    public function reportTitle(): string
    {
        return 'Laporan Retur/Komplain';
    }

    public function headings(): array
    {
        return ['Tanggal', 'Order #', 'Tipe', 'Status', 'Resolusi'];
    }

    public function rows(): Collection
    {
        return Complaint::whereBetween('created_at', [$this->rangeStart(), $this->rangeEnd()])
            ->with('order')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($complaint) => [
                $complaint->created_at->format('d/m/Y'),
                '#'.$complaint->order_id,
                $complaint->tipe->label(),
                $complaint->status->label(),
                $complaint->resolusi ?? '-',
            ]);
    }

    public function render()
    {
        return view('livewire.admin.reports.komplain')->layout('layouts.admin');
    }
}
