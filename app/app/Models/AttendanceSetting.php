<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['toleransi_menit' => 'integer', 'pengingat_menit' => 'integer', 'max_akurasi_m' => 'integer'];
    }

    /** The single settings row (seeded by the migration; recreated with defaults if missing). */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['toleransi_menit' => 10, 'pengingat_menit' => 15, 'max_akurasi_m' => 100]);
    }
}
