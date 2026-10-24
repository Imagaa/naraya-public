<?php

namespace App\Models;

use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One store on a route; status pending | visited | closed. */
class DeliveryRouteStop extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['visited_at' => 'datetime'];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(DeliveryRoute::class, 'delivery_route_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(SalesNote::class, 'route_stop_id');
    }

    public function closedPhotoUrl(): ?string
    {
        return $this->closed_photo_path ? Storage::disk('s3')->url($this->closed_photo_path) : null;
    }
}
