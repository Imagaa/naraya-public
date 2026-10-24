<?php

namespace App\Livewire\Admin\Rewards;

use App\Livewire\Concerns\HasCrudList;
use App\Models\Reward;
use Livewire\Component;
use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Livewire\Admin\Concerns\HasFilterPills;

class Index extends Component
{
    use HasCrudList, HasAdminShell, HasFilterPills;

    public function render()
    {
        $rewards = Reward::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->filter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->filter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate($this->perPage);

        return view('livewire.admin.rewards.index', ['rewards' => $rewards, 'counts' => $this->counts()])
            ->layout('layouts.admin');
    }

    /** Pill counts (same scopes as the filters). */
    private function counts(): array
    {
        return [
            'all' => Reward::count(),
            'active' => Reward::where('is_active', true)->count(),
            'inactive' => Reward::where('is_active', false)->count(),
        ];
    }
}
