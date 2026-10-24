<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** user_id null = general schedule; both times null = off day. */
class AttendanceSchedule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'weekday' => 'integer'];
    }
}
