<?php

namespace App\Models;

use App\Support\Geo;
use Database\Factories\AreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'lat', 'lng', 'radius_km'])]
class Area extends Model
{
    /** @use HasFactory<AreaFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    public function salesProfiles(): HasMany
    {
        return $this->hasMany(SalesProfile::class);
    }

    public function buyerProfiles(): HasMany
    {
        return $this->hasMany(BuyerProfile::class);
    }

    /** The area's currently active sales, if any — enforced 1:1 by the assignment form. */
    public function activeSalesProfile(): ?SalesProfile
    {
        return $this->salesProfiles()->where('is_active', true)->first();
    }

    /** The single "which area is this pin in" rule — registration, staff forms and seeders all use it. */
    public static function nearestTo(float $lat, float $lng): ?self
    {
        return static::query()->whereNotNull('lat')->whereNotNull('lng')->get()
            ->sortBy(fn (self $area) => $area->distanceFromKm($lat, $lng))
            ->first();
    }

    public function distanceFromKm(float $lat, float $lng): ?float
    {
        if ($this->lat === null || $this->lng === null) {
            return null;
        }

        return Geo::distanceKm($lat, $lng, $this->lat, $this->lng);
    }
}
