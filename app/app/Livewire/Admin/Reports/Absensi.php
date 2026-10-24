<?php

namespace App\Livewire\Admin\Reports;

use App\Livewire\Admin\Reports\Concerns\ExportsTable;
use App\Livewire\Admin\Reports\Concerns\HasDateRangeFilter;
use App\Models\Attendance;
use Illuminate\Support\Collection;
use Livewire\Component;
use App\Livewire\Admin\Concerns\HasAdminShell;

class Absensi extends Component
{
    use HasDateRangeFilter, ExportsTable, HasAdminShell;

    public function reportTitle(): string
    {
        return 'Laporan Absensi';
    }

    public function headings(): array
    {
        return ['Tanggal', 'Sales', 'Tipe', 'Waktu (WIB)', 'Status', 'Selisih (mnt)', 'Lokasi', 'Jarak (m)', 'Flags', 'Dikoreksi'];
    }

    public function rows(): Collection
    {
        return Attendance::whereBetween('created_at', [$this->rangeStart(), $this->rangeEnd()])
            ->with(['salesUser', 'location'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($attendance) => [
                $attendance->created_at->wib()->format('d/m/Y'),
                $attendance->salesUser->name,
                $attendance->tipe->label(),
                $attendance->created_at->wib()->format('H:i'),
                $attendance->status?->text($attendance->selisih_menit) ?? '-',
                $attendance->selisih_menit ?? '-',
                $attendance->location?->nama ?? ($attendance->latitude && $attendance->longitude ? $attendance->latitude.','.$attendance->longitude : '-'),
                $attendance->jarak_m ?? '-',
                $attendance->flags ? implode(', ', $attendance->flags) : '-',
                $attendance->dikoreksi_oleh ? 'Ya' : 'Tidak',
            ]);
    }

    public function render()
    {
        return view('livewire.admin.reports.absensi')->layout('layouts.admin');
    }
}
