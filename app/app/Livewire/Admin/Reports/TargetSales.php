<?php

namespace App\Livewire\Admin\Reports;

use App\Enums\OrderStatus;
use App\Livewire\Admin\Reports\Concerns\ExportsTable;
use App\Livewire\Admin\Reports\Concerns\HasDateRangeFilter;
use App\Models\Order;
use App\Models\SalesProfile;
use Illuminate\Support\Collection;
use Livewire\Component;
use App\Livewire\Admin\Concerns\HasAdminShell;

class TargetSales extends Component
{
    use HasDateRangeFilter, ExportsTable, HasAdminShell;

    public function reportTitle(): string
    {
        return 'Laporan Target Sales';
    }

    public function headings(): array
    {
        return ['Sales', 'Area', 'Target Bulanan', 'Realisasi', '% Capaian'];
    }

    public function rows(): Collection
    {
        return SalesProfile::where('is_active', true)
            ->with(['user', 'area'])
            ->get()
            ->map(function ($profile) {
                $realisasi = Order::where('status', OrderStatus::Completed)
                    ->where('sales_id', $profile->user_id)
                    ->whereBetween('completed_at', [$this->rangeStart(), $this->rangeEnd()])
                    ->sum('total');

                $percent = $profile->monthly_target > 0
                    ? round(($realisasi / $profile->monthly_target) * 100, 1)
                    : 0;

                return [
                    $profile->user->name,
                    $profile->area?->name ?? '-',
                    'Rp '.number_format($profile->monthly_target, 0, ',', '.'),
                    'Rp '.number_format($realisasi, 0, ',', '.'),
                    $percent.'%',
                ];
            });
    }

    public function render()
    {
        return view('livewire.admin.reports.target-sales')->layout('layouts.admin');
    }
}
