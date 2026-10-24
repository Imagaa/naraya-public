<?php

namespace App\Livewire\Admin\Reports;

use App\Livewire\Admin\Reports\Concerns\ExportsTable;
use App\Livewire\Admin\Reports\Concerns\HasDateRangeFilter;
use App\Models\BuyerProfile;
use App\Models\Redemption;
use Illuminate\Support\Collection;
use Livewire\Component;
use App\Livewire\Admin\Concerns\HasAdminShell;

class Loyalty extends Component
{
    use HasDateRangeFilter, ExportsTable, HasAdminShell;

    public function reportTitle(): string
    {
        return 'Laporan Loyalty';
    }

    public function headings(): array
    {
        return ['Tanggal', 'User', 'Hadiah', 'Point', 'Status'];
    }

    public function rows(): Collection
    {
        return Redemption::whereBetween('created_at', [$this->rangeStart(), $this->rangeEnd()])
            ->with(['user', 'reward'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($redemption) => [
                $redemption->created_at->format('d/m/Y'),
                $redemption->user->name,
                $redemption->reward->name,
                $redemption->point_cost,
                $redemption->status->label(),
            ]);
    }

    public function render()
    {
        return view('livewire.admin.reports.loyalty', [
            'topBalances' => BuyerProfile::with('user')->orderByDesc('loyalty_points')->limit(20)->get(),
        ])->layout('layouts.admin');
    }
}
