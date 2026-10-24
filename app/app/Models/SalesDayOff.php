<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A day off a sales took for themselves (one per week); cancelled ones are kept for the record. */
class SalesDayOff extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date', 'cancelled_at' => 'datetime'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('cancelled_at');
    }

    public function salesUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
