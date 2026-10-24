<?php

namespace App\Livewire\Admin\Sales;

use App\Models\Area;
use Livewire\Component;
use App\Livewire\Admin\Concerns\HasAdminShell;

class Map extends Component
{
    use HasAdminShell;

    public function render()
    {
        return view('livewire.admin.sales.map', [
            'areas' => Area::orderBy('name')
                ->with(['salesProfiles' => fn ($q) => $q->where('is_active', true)->with('user')])
                ->get(),
        ])->layout('layouts.admin');
    }
}
