<?php

namespace App\Livewire\Admin\Deposits;

use App\Enums\CashDepositStatus;
use App\Livewire\Admin\Concerns\FiltersByWorkQueue;
use App\Livewire\Concerns\HasCrudList;
use App\Models\CashDeposit;
use App\Support\ActiveLine;
use Livewire\Component;
use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Livewire\Admin\Concerns\HasFilterPills;

class Index extends Component
{
    use FiltersByWorkQueue, HasAdminShell, HasFilterPills, HasCrudList;

    /** Pill key => statuses (the open ones match the dashboard WorkQueues). */
    private const FILTERS = [
        'pending' => [CashDepositStatus::Submitted, CashDepositStatus::Disputed],
        'disputed' => [CashDepositStatus::Disputed],
        'done' => [CashDepositStatus::Verified, CashDepositStatus::Resolved],
    ];

    public function render()
    {
        $deposits = $this->applyQueue(CashDeposit::forLine(ActiveLine::current()), 'admin.deposits.index')
            ->with('salesUser.salesProfile')
            ->when($this->search, fn ($q) => $q->whereHas('salesUser', fn ($q2) => $q2->where('name', 'like', "%{$this->search}%")))
            ->when(isset(self::FILTERS[$this->filter]), fn ($q) => $q->whereIn('status', self::FILTERS[$this->filter]))
            ->orderByDesc('id')
            ->paginate($this->perPage);

        return view('livewire.admin.deposits.index', ['deposits' => $deposits, 'counts' => $this->counts(), 'queueLabel' => $this->queueLabel('admin.deposits.index')])
            ->layout('layouts.admin');
    }

    /** Pill counts (same scopes as the filters). */
    private function counts(): array
    {
        $base = fn () => CashDeposit::forLine(ActiveLine::current());

        return [
            'all' => $base()->count(),
            'pending' => $base()->whereIn('status', self::FILTERS['pending'])->count(),
            'disputed' => $base()->whereIn('status', self::FILTERS['disputed'])->count(),
            'done' => $base()->whereIn('status', self::FILTERS['done'])->count(),
        ];
    }
}
