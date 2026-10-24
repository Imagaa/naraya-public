<?php

namespace App\Models\Concerns;

use App\Enums\BusinessLine;
use Illuminate\Database\Eloquent\Builder;

/**
 * A record that belongs to one business line (`line` column). Filtering is
 * always explicit — `->forLine(ActiveLine::current())` — never a global
 * scope, so scheduled jobs and the frozen marketplace see every line.
 */
trait HasBusinessLine
{
    public function initializeHasBusinessLine(): void
    {
        $this->mergeCasts(['line' => BusinessLine::class]);
        // Same default as the column, so a new instance knows its line before it is saved/refreshed.
        $this->attributes['line'] ??= BusinessLine::Naraya->value;
    }

    public function scopeForLine(Builder $query, BusinessLine $line): void
    {
        $query->where($this->qualifyColumn('line'), $line->value);
    }
}
