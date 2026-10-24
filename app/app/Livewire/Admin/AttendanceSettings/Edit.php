<?php

namespace App\Livewire\Admin\AttendanceSettings;

use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Models\AttendanceHoliday;
use App\Models\AttendanceLocation;
use App\Models\AttendanceSchedule;
use App\Models\AttendanceSetting;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Pengaturan Absensi: jadwal, lokasi, hari libur, aturan. */
class Edit extends Component
{
    use HasAdminShell;

    public const DAYS = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    #[Url]
    public string $tab = 'jadwal';

    public string $flash = '';

    // aturan (no set hours since 2026-10-12: only the GPS accuracy limit is left)
    public int $maxAkurasi = 100;

    // jadwal: weekday => ['libur' => bool, 'masuk' => 'HH:MM', 'pulang' => 'HH:MM']
    public array $umum = [];

    public bool $editingOverride = false;

    public ?int $overrideUserId = null;

    public array $khusus = [];

    // hari libur
    public string $holidayDate = '';

    public string $holidayName = '';

    // lokasi
    public bool $editingLocation = false;

    public ?int $locationId = null;

    public string $locNama = '';

    public $lat = null;

    public $lng = null;

    public int $radius = 100;

    public bool $locActive = true;

    public array $locSalesIds = [];

    public int $formKey = 0;

    public function mount(): void
    {
        $s = AttendanceSetting::current();
        $this->maxAkurasi = $s->max_akurasi_m;
        $this->umum = $this->loadWeek(null);
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['jadwal', 'lokasi', 'libur', 'aturan'], true) ? $tab : 'jadwal';
        $this->flash = '';
    }

    public function saveRules(): void
    {
        $this->validate(['maxAkurasi' => 'required|integer|between:10,1000']);
        AttendanceSetting::current()->update(['max_akurasi_m' => $this->maxAkurasi]);
        $this->flash = 'Aturan tersimpan.';
    }

    public function saveGeneral(): void
    {
        $this->validateWeek($this->umum, 'umum');
        $this->writeWeek(null, $this->umum);
        $this->flash = 'Jadwal umum tersimpan.';
    }

    public function editOverride(?int $userId = null): void
    {
        $this->overrideUserId = $userId;
        $this->khusus = $this->loadWeek($userId);
        $this->editingOverride = true;
    }

    public function saveOverride(): void
    {
        $this->validate(['overrideUserId' => 'required|exists:users,id'], ['overrideUserId.required' => 'Pilih sales.']);
        $this->validateWeek($this->khusus, 'khusus');
        $this->writeWeek($this->overrideUserId, $this->khusus);
        $this->editingOverride = false;
        $this->flash = 'Jadwal khusus tersimpan.';
    }

    public function deleteOverride(int $userId): void
    {
        AttendanceSchedule::where('user_id', $userId)->delete();
        $this->flash = 'Jadwal khusus dihapus; sales ini kembali ikut jadwal umum.';
    }

    public function addHoliday(): void
    {
        $this->validate([
            'holidayDate' => 'required|date|unique:attendance_holidays,tanggal',
            'holidayName' => 'required|string|max:100',
        ], ['holidayDate.unique' => 'Tanggal ini sudah ada di daftar libur.']);
        AttendanceHoliday::create(['tanggal' => $this->holidayDate, 'nama' => $this->holidayName]);
        $this->reset('holidayDate', 'holidayName');
    }

    public function deleteHoliday(int $id): void
    {
        AttendanceHoliday::whereKey($id)->delete();
    }

    public function newLocation(): void
    {
        $this->reset('locationId', 'locNama', 'lat', 'lng', 'locSalesIds');
        $this->radius = 100;
        $this->locActive = true;
        $this->editingLocation = true;
        $this->formKey++;
    }

    public function editLocation(int $id): void
    {
        $l = AttendanceLocation::with('users')->findOrFail($id);
        [$this->locationId, $this->locNama, $this->lat, $this->lng, $this->radius, $this->locActive] = [$l->id, $l->nama, $l->lat, $l->lng, $l->radius_m, $l->is_active];
        // strings, like the checkbox values the browser sends back, so an untouched form isn't "dirty"
        $this->locSalesIds = $l->users->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->editingLocation = true;
        $this->formKey++;
    }

    public function saveLocation(): void
    {
        $this->validate([
            'locNama' => 'required|string|max:100',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'radius' => 'required|integer|between:1,1000',
            'locSalesIds' => 'array',
            'locSalesIds.*' => 'integer|exists:users,id',
        ], ['lat.required' => 'Tandai titik lokasi di peta.', 'radius.between' => 'Radius 1–1000 meter.']);

        $location = AttendanceLocation::updateOrCreate(['id' => $this->locationId], [
            'nama' => $this->locNama, 'lat' => $this->lat, 'lng' => $this->lng, 'radius_m' => $this->radius, 'is_active' => $this->locActive,
        ]);
        $location->users()->sync($this->locSalesIds);
        $this->editingLocation = false;
        $this->flash = 'Lokasi tersimpan.';
    }

    public function toggleLocation(int $id): void
    {
        $l = AttendanceLocation::findOrFail($id);
        $l->update(['is_active' => ! $l->is_active]);
    }

    public function deleteLocation(int $id): void
    {
        AttendanceLocation::whereKey($id)->delete();
    }

    public function render()
    {
        $globalExists = AttendanceLocation::where('is_active', true)->whereDoesntHave('users')->exists();

        return view('livewire.admin.attendance-settings.edit', [
            'days' => self::DAYS,
            'sales' => User::activeSales()->orderBy('name')->get(['id', 'name']),
            'overrides' => User::whereIn('id', AttendanceSchedule::whereNotNull('user_id')->distinct()->pluck('user_id'))->orderBy('name')->get(['id', 'name']),
            'holidays' => AttendanceHoliday::orderByDesc('tanggal')->get(),
            'locations' => AttendanceLocation::with('users:id,name')->orderBy('nama')->get(),
            'withoutLocation' => $globalExists ? collect() : User::activeSales()
                ->whereDoesntHave('attendanceLocations', fn ($q) => $q->where('is_active', true))
                ->orderBy('name')->pluck('name'),
        ])->layout('layouts.admin');
    }

    private function loadWeek(?int $userId): array
    {
        $rows = AttendanceSchedule::where('user_id', $userId)->get()->keyBy('weekday');
        if ($userId !== null && $rows->isEmpty()) {
            $rows = AttendanceSchedule::whereNull('user_id')->get()->keyBy('weekday'); // prefill from the general schedule
        }

        return collect(self::DAYS)->map(fn ($_, $d) => [
            'libur' => ! $rows->get($d)?->jam_masuk,
            'masuk' => substr((string) ($rows->get($d)?->jam_masuk ?? '08:00'), 0, 5),
            'pulang' => substr((string) ($rows->get($d)?->jam_pulang ?? '17:00'), 0, 5),
        ])->all();
    }

    private function validateWeek(array $week, string $prop): void
    {
        // A manual ValidationException doesn't clear the bag the way validate() does; a fixed day would keep its old error.
        $this->resetErrorBag();
        $errors = [];
        foreach ($week as $d => $row) {
            if (! empty($row['libur'])) {
                continue;
            }
            $masuk = substr((string) ($row['masuk'] ?? ''), 0, 5);
            $pulang = substr((string) ($row['pulang'] ?? ''), 0, 5);
            if (! preg_match('/^\d{2}:\d{2}$/', $masuk) || ! preg_match('/^\d{2}:\d{2}$/', $pulang)) {
                $errors["{$prop}.{$d}"] = self::DAYS[$d].': isi jam masuk dan pulang, atau centang Libur.';
            } elseif ($pulang <= $masuk) {
                $errors["{$prop}.{$d}"] = self::DAYS[$d].': jam pulang harus setelah jam masuk.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function writeWeek(?int $userId, array $week): void
    {
        foreach ($week as $d => $row) {
            $off = ! empty($row['libur']);
            AttendanceSchedule::updateOrCreate(['user_id' => $userId, 'weekday' => $d], [
                'jam_masuk' => $off ? null : substr($row['masuk'], 0, 5),
                'jam_pulang' => $off ? null : substr($row['pulang'], 0, 5),
            ]);
        }
    }
}
