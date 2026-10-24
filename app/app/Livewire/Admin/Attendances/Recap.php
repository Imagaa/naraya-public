<?php

namespace App\Livewire\Admin\Attendances;

use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Support\AttendanceReport;
use App\Support\BusinessDay;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

class Recap extends Component
{
    use HasAdminShell;

    #[Url(as: 'bulan')]
    public string $month = '';

    public function mount(): void
    {
        $this->month = preg_match('/^\d{4}-\d{2}$/', $this->month) ? $this->month : BusinessDay::now()->format('Y-m');
    }

    public function render()
    {
        $start = Carbon::parse($this->month.'-01', BusinessDay::zone());

        return view('livewire.admin.attendances.recap', [
            'start' => $start,
            'rows' => AttendanceReport::ranking($start),
        ])->layout('layouts.admin');
    }
}
