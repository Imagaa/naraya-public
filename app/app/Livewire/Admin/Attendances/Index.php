<?php

namespace App\Livewire\Admin\Attendances;

use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Models\Attendance;
use App\Models\SalesDayOff;
use App\Support\AttendanceCalendar;
use App\Support\AttendanceReport;
use App\Support\BusinessDay;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Absensi harian: one row per sales for one WIB day. */
class Index extends Component
{
    use HasAdminShell;

    #[Url(as: 'tanggal')]
    public string $date = '';

    #[Url(as: 'filter')]
    public string $filter = 'all';

    #[Url(as: 'q')]
    public string $search = '';

    public ?int $correctingId = null;

    public string $koreksiAlasan = '';

    public function mount(): void
    {
        $this->date = $this->clamp($this->date);
    }

    public function updatedDate(): void
    {
        $this->date = $this->clamp($this->date);
    }

    public function shiftDay(int $days): void
    {
        $this->date = $this->clamp(Carbon::parse($this->date)->addDays($days)->format('Y-m-d'));
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['all', 'hadir', 'terlambat', 'tidak_absen', 'libur'], true) ? $filter : 'all';
    }

    public function startCorrecting(int $attendanceId): void
    {
        $this->correctingId = $attendanceId;
    }

    public function cancelCorrecting(): void
    {
        $this->reset('correctingId', 'koreksiAlasan');
        $this->resetErrorBag();
    }

    public function correct(): void
    {
        $this->validate(['koreksiAlasan' => 'required|string|max:500']);
        Attendance::findOrFail($this->correctingId)->update(['koreksi_alasan' => $this->koreksiAlasan, 'dikoreksi_oleh' => auth()->id()]);
        $this->correctingId = null;
        $this->koreksiAlasan = '';
    }

    public function render()
    {
        $day = Carbon::parse($this->date, BusinessDay::zone());
        $rows = AttendanceReport::day($day);
        $counts = [
            'all' => $rows->count(),
            'terjadwal' => $rows->where('scheduled', true)->count(),
            'hadir' => $rows->whereIn('state', ['hadir', 'terlambat'])->count(),
            'terlambat' => $rows->where('state', 'terlambat')->count(),
            'tidak_absen' => $rows->where('state', 'tidak_absen')->count(),
            'belum' => $rows->where('state', 'belum')->count(),
            'libur' => $rows->whereNotNull('day_off')->count(),
        ];

        $visible = $rows
            ->when($this->filter === 'hadir', fn ($r) => $r->whereIn('state', ['hadir', 'terlambat']))
            ->when($this->filter === 'terlambat', fn ($r) => $r->where('state', 'terlambat'))
            // no set hours: today's no-shows stay "belum" all day, and admin still wants to follow them up
            ->when($this->filter === 'tidak_absen', fn ($r) => $r->whereIn('state', ['tidak_absen', 'belum']))
            ->when($this->filter === 'libur', fn ($r) => $r->whereNotNull('day_off'))
            ->when($this->search !== '', fn ($r) => $r->filter(fn ($row) => str_contains(mb_strtolower($row['sales']->name), mb_strtolower($this->search))));

        return view('livewire.admin.attendances.index', [
            'day' => $day,
            'isToday' => $day->isSameDay(BusinessDay::today()),
            'holiday' => (new AttendanceCalendar)->holidayOn($day),
            'rows' => $visible->values(),
            'counts' => $counts,
            'offNames' => $rows->whereNotNull('day_off')->pluck('sales.name'),
            // day offs already requested for the coming week, from the day after the one shown
            'upcomingOffs' => SalesDayOff::active()->with('salesUser:id,name')
                ->whereDate('date', '>', $day->toDateString())->whereDate('date', '<=', $day->copy()->addDays(7)->toDateString())
                ->orderBy('date')->get()->groupBy(fn ($d) => $d->date->format('Y-m-d')),
        ])->layout('layouts.admin');
    }

    private function clamp(string $date): string
    {
        $today = BusinessDay::today()->format('Y-m-d');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $today;
        }

        return min($date, $today);
    }
}
