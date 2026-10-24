<?php

namespace App\Livewire\Admin\Complaints;

use App\Enums\ComplaintStatus;
use App\Livewire\Admin\Concerns\FiltersByWorkQueue;
use App\Livewire\Concerns\HasCrudList;
use App\Models\Complaint;
use Livewire\Component;
use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Livewire\Admin\Concerns\HasFilterPills;

class Index extends Component
{
    use FiltersByWorkQueue, HasAdminShell, HasFilterPills, HasCrudList;

    /** Pill key => statuses (the open ones match the dashboard WorkQueues). */
    private const FILTERS = [
        'waiting' => [ComplaintStatus::Diajukan],
        'done' => [ComplaintStatus::Diresolusi, ComplaintStatus::Ditutup],
    ];

    public function render()
    {
        $complaints = $this->applyQueue(Complaint::query(), 'admin.complaints.index')
            ->with('order.buyer')
            ->when($this->search, fn ($q) => $q->whereHas('order.buyer', fn ($q2) => $q2->where('name', 'like', "%{$this->search}%")))
            ->when(isset(self::FILTERS[$this->filter]), fn ($q) => $q->whereIn('status', self::FILTERS[$this->filter]))
            ->orderByDesc('id')
            ->paginate($this->perPage);

        return view('livewire.admin.complaints.index', ['complaints' => $complaints, 'counts' => $this->counts(), 'queueLabel' => $this->queueLabel('admin.complaints.index')])
            ->layout('layouts.admin');
    }

    /** Pill counts (same scopes as the filters). */
    private function counts(): array
    {
        return [
            'all' => Complaint::count(),
            'waiting' => Complaint::whereIn('status', self::FILTERS['waiting'])->count(),
            'done' => Complaint::whereIn('status', self::FILTERS['done'])->count(),
        ];
    }
}
