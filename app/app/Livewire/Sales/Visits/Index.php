<?php

namespace App\Livewire\Sales\Visits;

use App\Models\Visit;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    /** Store preselected from a link such as the store detail page. */
    #[Url(as: 'toko')]
    public ?int $toko = null;

    #[On('visit-logged')]
    public function refreshList(): void {}

    public function render()
    {
        return view('livewire.sales.visits.index', [
            'visits' => Visit::where('sales_id', auth()->id())
                ->with('buyer')
                ->latest('visited_at')
                ->get(),
        ])->layout('layouts.sales');
    }
}
