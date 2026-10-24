<?php

namespace App\Livewire\Sales\Target;

use App\Support\SalesTarget;
use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        $target = auth()->user()->salesProfile?->monthly_target ?? 0;
        $realized = SalesTarget::realized(auth()->id());

        $history = collect(range(1, 3))->map(fn ($monthsAgo) => [
            'label' => now()->subMonths($monthsAgo)->translatedFormat('F Y'),
            'realized' => SalesTarget::realized(auth()->id(), now()->subMonths($monthsAgo)),
        ]);

        return view('livewire.sales.target.index', [
            'target' => $target,
            'realized' => $realized,
            'history' => $history,
        ])->layout('layouts.sales');
    }
}
