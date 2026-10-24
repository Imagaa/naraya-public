<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Observers\AttendanceObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(AttendanceObserver::class)]
class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    protected $guarded = [];

    public const FLAG_LABELS = [
        'lompatan' => 'Pindah lokasi terlalu cepat sejak absen terakhir',
        'koordinat_identik' => 'Koordinat persis sama dengan absen sebelumnya',
        'akurasi_janggal' => 'Akurasi GPS tidak wajar (≤ 1 m)',
    ];

    protected function casts(): array
    {
        return [
            'tipe' => AttendanceType::class,
            'status' => AttendanceStatus::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'jarak_m' => 'integer',
            'akurasi_m' => 'integer',
            'selisih_menit' => 'integer',
            'flags' => 'array',
        ];
    }

    public function salesUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dikoreksi_oleh');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(AttendanceLocation::class, 'attendance_location_id');
    }

    /** "Terlambat 12 mnt" etc.; null for rows recorded before schedules existed. */
    public function statusText(): ?string
    {
        return $this->status?->text($this->selisih_menit);
    }
}
