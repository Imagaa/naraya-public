<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A sales person's planned run of store stops; status active | finished. */
class DeliveryRoute extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date', 'finished_at' => 'datetime'];
    }

    public function salesUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(DeliveryRouteStop::class)->orderBy('position');
    }
}
