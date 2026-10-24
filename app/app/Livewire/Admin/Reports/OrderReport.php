<?php

namespace App\Livewire\Admin\Reports;

use App\Livewire\Admin\Reports\Concerns\ExportsTable;
use App\Livewire\Admin\Reports\Concerns\HasDateRangeFilter;
use App\Models\Order;
use Illuminate\Support\Collection;
use Livewire\Component;
use App\Livewire\Admin\Concerns\HasAdminShell;

class OrderReport extends Component
{
    use HasDateRangeFilter, ExportsTable, HasAdminShell;

    public function reportTitle(): string
    {
        return 'Laporan Order';
    }

    public function headings(): array
    {
        return ['Tanggal', 'Order #', 'Buyer', 'Status', 'Total'];
    }

    public function rows(): Collection
    {
        return Order::whereBetween('created_at', [$this->rangeStart(), $this->rangeEnd()])
            ->with('buyer')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($order) => [
                $order->created_at->format('d/m/Y'),
                '#'.$order->id,
                $order->buyer->name,
                $order->status->label(),
                'Rp '.number_format($order->total, 0, ',', '.'),
            ]);
    }

    public function render()
    {
        return view('livewire.admin.reports.order')->layout('layouts.admin');
    }
}
