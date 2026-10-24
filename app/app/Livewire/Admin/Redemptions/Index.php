<?php

namespace App\Livewire\Admin\Redemptions;

use App\Enums\RedemptionStatus;
use App\Livewire\Admin\Concerns\FiltersByWorkQueue;
use App\Livewire\Concerns\HasCrudList;
use App\Models\Redemption;
use Livewire\Component;
use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Livewire\Admin\Concerns\HasFilterPills;

class Index extends Component
{
    use FiltersByWorkQueue, HasAdminShell, HasFilterPills, HasCrudList;

    /** Pill key => statuses (the open ones match the dashboard WorkQueues). */
    private const FILTERS = [
        'decision' => [RedemptionStatus::Diajukan],
        'handover' => [RedemptionStatus::Disetujui],
        'done' => [RedemptionStatus::Diserahkan, RedemptionStatus::Ditolak, RedemptionStatus::Dibatalkan],
    ];

    public function render()
    {
        $redemptions = $this->applyQueue(Redemption::query(), 'admin.redemptions.index')
            ->with('user', 'reward')
            ->when($this->search, fn ($q) => $q->whereHas('user', fn ($q2) => $q2->where('name', 'like', "%{$this->search}%")))
            ->when(isset(self::FILTERS[$this->filter]), fn ($q) => $q->whereIn('status', self::FILTERS[$this->filter]))
            ->orderByDesc('id')
            ->paginate($this->perPage);

        return view('livewire.admin.redemptions.index', ['redemptions' => $redemptions, 'counts' => $this->counts(), 'queueLabel' => $this->queueLabel('admin.redemptions.index')])
            ->layout('layouts.admin');
    }

    /** Pill counts (same scopes as the filters). */
    private function counts(): array
    {
        return [
            'all' => Redemption::count(),
            'decision' => Redemption::whereIn('status', self::FILTERS['decision'])->count(),
            'handover' => Redemption::whereIn('status', self::FILTERS['handover'])->count(),
            'done' => Redemption::whereIn('status', self::FILTERS['done'])->count(),
        ];
    }
}
