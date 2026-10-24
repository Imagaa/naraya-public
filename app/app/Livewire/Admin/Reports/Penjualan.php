<?php

namespace App\Livewire\Admin\Reports;

use App\Enums\OrderStatus;
use App\Livewire\Admin\Reports\Concerns\ExportsTable;
use App\Livewire\Admin\Reports\Concerns\HasDateRangeFilter;
use App\Models\Order;
use Illuminate\Support\Collection;
use Livewire\Component;
use App\Livewire\Admin\Concerns\HasAdminShell;

class Penjualan extends Component
{
    use HasDateRangeFilter, ExportsTable, HasAdminShell;

    public function reportTitle(): string
    {
        return 'Laporan Penjualan';
    }

    public function headings(): array
    {
        return ['Tanggal', 'Order #', 'Buyer', 'Sales', 'Area', 'Total'];
    }

    public function rows(): Collection
    {
        return Order::where('status', OrderStatus::Completed)
            ->whereBetween('completed_at', [$this->rangeStart(), $this->rangeEnd()])
            ->with(['buyer.buyerProfile.area', 'salesUser'])
            ->orderByDesc('completed_at')
            ->get()
            ->map(fn ($order) => [
                $order->completed_at->format('d/m/Y'),
                '#'.$order->id,
                $order->buyer->name,
                $order->salesUser?->name ?? '-',
                $order->buyer->buyerProfile?->area?->name ?? '-',
                'Rp '.number_format($order->total, 0, ',', '.'),
            ]);
    }

    public function render()
    {
        return view('livewire.admin.reports.penjualan')->layout('layouts.admin');
    }
}
