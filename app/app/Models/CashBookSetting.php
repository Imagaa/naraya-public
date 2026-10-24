<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashBookSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['closing_day' => 'integer', 'posted_through' => 'date'];
    }

    /** The single settings row (seeded by the migration; recreated if missing). */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['closing_day' => 5, 'posted_through' => now()->subDay()->toDateString()]);
    }
}
