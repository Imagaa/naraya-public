<?php

namespace App\Livewire\Admin\Attendances;

use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Models\User;
use App\Support\AttendanceCalendar;
use App\Support\AttendanceReport;
use App\Support\BusinessDay;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

class RecapSales extends Component
{
    use HasAdminShell;

    public User $user;

    #[Url(as: 'bulan')]
    public string $month = '';

    public function mount(): void
    {
        $this->month = preg_match('/^\d{4}-\d{2}$/', $this->month) ? $this->month : BusinessDay::now()->format('Y-m');
    }

    public function render()
    {
        $start = Carbon::parse($this->month.'-01', BusinessDay::zone());
        $recap = AttendanceReport::month($this->user, $start);
        $previous = AttendanceReport::month($this->user, $start->copy()->subMonthNoOverflow())['totals'];
        $tol = (new AttendanceCalendar)->setting()->toleransi_menit;
        $min = fn (Carbon $t) => $t->hour * 60 + $t->minute;

        $points = collect($recap['days'])
            ->filter(fn ($d) => $d['schedule'] || $d['masuk'])
            ->map(fn ($d) => [
                'label' => $d['date']->format('j'),
                'jadwal' => $d['schedule'] ? $min($d['schedule']['masuk']) : null,
                'batas' => $d['schedule'] ? $min($d['schedule']['masuk']) + $tol : null,
                'masuk' => $d['masuk'] ? $min($d['masuk']->created_at->wib()) : null,
                'state' => $d['state'],
                // rows from before schedules existed carry no judgement; the chart draws them neutral
                'judged' => $d['masuk']?->status !== null,
                'text' => $d['masuk'] ? ($d['masuk']->statusText() ?? 'Data lama, belum dinilai') : ($d['state'] === 'tidak_absen' ? 'Tidak absen' : 'Belum absen'),
            ])->values();

        return view('livewire.admin.attendances.recap-sales', [
            'start' => $start,
            'recap' => $recap,
            'note' => AttendanceReport::note($recap['totals'], $previous['hari_kerja'] ? $previous : null),
            'points' => $points,
            'trend' => AttendanceReport::trend($this->user, $start),
        ])->layout('layouts.admin');
    }
}
