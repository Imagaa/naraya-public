<?php

namespace App\Livewire\Admin\Buyers;

use App\Enums\BusinessLine;
use App\Enums\Role;
use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Livewire\Admin\Concerns\HasStorePeriod;
use App\Models\User;
use App\Support\ActiveLine;
use App\Support\Loyalty\StorePoints;
use App\Support\Reports\StoreReport;
use App\Support\Sales\Consignments;
use Livewire\Component;

/** One store's recap for a period, sized to fit a desktop screenshot. */
class Show extends Component
{
    use HasAdminShell, HasStorePeriod;

    public User $buyer;

    public function mount(User $buyer): void
    {
        abort_unless($buyer->role === Role::User && $buyer->buyerProfile, 404);
        $this->buyer = $buyer;
    }

    public function showStock(): void
    {
        $this->dispatch('store-stock-open');
    }

    public function render(StoreReport $report, StorePoints $points)
    {
        $line = ActiveLine::current();
        [$from, $to] = $this->bounds();
        $this->buyer->load('buyerProfile.salesUser', 'buyerProfile.area');

        return view('livewire.admin.buyers.show', [
            'profile' => $this->buyer->buyerProfile,
            'line' => $line,
            'summary' => $report->summary($line, $from, $to, $this->buyer->id),
            'items' => $report->items($line, $from, $to, $this->buyer->id),
            'lastVisit' => $report->lastVisitAt($this->buyer->id),
            'progress' => $points->progress($this->buyer->id),
            'toysLine' => BusinessLine::Toys,
            'stock' => Consignments::valuation($this->buyer->id),
        ])->layout('layouts.admin')->title($this->buyer->name.' — Toko');
    }
}
