<?php

namespace App\Livewire\Admin\Reports;

use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Livewire\Admin\Concerns\HasStorePeriod;
use App\Support\ActiveLine;
use App\Support\Reports\StoreReport;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Laporan Toko: every store's figures summed, plus a store ranking. */
class Toko extends Component
{
    use HasAdminShell, WithPagination;
    use HasStorePeriod { setPeriod as private choosePeriod; }

    public const SORTS = ['uang' => 'Uang terbanyak', 'terjual' => 'Terjual terbanyak', 'lama' => 'Paling lama tidak dikunjungi'];

    /** uang | terjual | lama */
    #[Url]
    public string $sort = 'uang';

    public function updated(string $property): void
    {
        if (in_array($property, ['sort', 'fromMonth', 'toMonth'], true)) {
            $this->resetPage();
        }
    }

    public function setPeriod(string $period): void
    {
        $this->choosePeriod($period);
        $this->resetPage();
    }

    public function paginationView(): string
    {
        return 'components.admin.pagination';
    }

    public function render(StoreReport $report)
    {
        $line = ActiveLine::current();
        [$from, $to] = $this->bounds();
        $sort = array_key_exists($this->sort, self::SORTS) ? $this->sort : 'uang';

        return view('livewire.admin.reports.toko', [
            'line' => $line,
            'summary' => $report->summary($line, $from, $to),
            'counts' => $report->storeCounts($line, $from, $to),
            'items' => $report->items($line, $from, $to),
            'ranking' => $report->ranking($line, $from, $to, $sort),
            'sorts' => self::SORTS,
        ])->layout('layouts.admin')->title('Laporan Toko');
    }
}
