<?php

namespace App\Livewire\Admin\Attendances;

use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Models\SalesDayOff;
use App\Models\User;
use App\Support\BusinessDay;
use App\Support\DayOffs as DayOffRules;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Libur sales: every weekly day off the sales took, newest first; admin may cancel one with a reason. */
class DayOffs extends Component
{
    use HasAdminShell, WithPagination;

    #[Url(as: 'sales')]
    public ?int $salesId = null;

    #[Url(as: 'minggu')]
    public string $week = '';

    public ?int $cancellingId = null;

    public string $cancelReason = '';

    public function updated(string $field): void
    {
        if (in_array($field, ['salesId', 'week'], true)) {
            $this->resetPage();
        }
    }

    public function startCancel(int $id): void
    {
        $this->resetErrorBag();
        $this->reset('cancelReason');
        $this->cancellingId = $id;
    }

    public function stopCancel(): void
    {
        $this->reset('cancellingId', 'cancelReason');
    }

    public function cancel(DayOffRules $rules): void
    {
        $this->resetErrorBag();
        $rules->cancel(SalesDayOff::active()->findOrFail($this->cancellingId), auth()->user(), $this->cancelReason);
        $this->reset('cancellingId', 'cancelReason');
    }

    public function render()
    {
        $weekStart = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->week) ? Carbon::parse($this->week)->startOfWeek(Carbon::MONDAY) : null;

        return view('livewire.admin.attendances.day-offs', [
            'salesPeople' => User::activeSales()->orderBy('name')->get(['id', 'name']),
            'today' => BusinessDay::today()->toDateString(),
            'dayOffs' => SalesDayOff::with(['salesUser.salesProfile', 'canceller:id,name'])
                ->when($this->salesId, fn ($q) => $q->where('sales_id', $this->salesId))
                ->when($weekStart, fn ($q) => $q->whereDate('date', '>=', $weekStart->toDateString())->whereDate('date', '<=', $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->toDateString()))
                ->orderByDesc('date')->orderByDesc('id')->paginate(25),
        ])->layout('layouts.admin');
    }
}
