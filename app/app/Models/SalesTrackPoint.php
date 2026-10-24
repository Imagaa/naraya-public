<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One GPS fix of a sales person during a shift (check-in → check-out). */
class SalesTrackPoint extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'accuracy' => 'integer', 'recorded_at' => 'datetime'];
    }
}
