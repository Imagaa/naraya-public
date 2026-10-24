<?php

namespace App\Models;

use App\Support\Geo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/** A check-in point. No assigned users = for every sales. */
class AttendanceLocation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float', 'radius_m' => 'integer', 'is_active' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /** Active locations this sales may check in at. */
    public static function allowedFor(User $user): Collection
    {
        return static::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereDoesntHave('users')->orWhereHas('users', fn ($u) => $u->whereKey($user->id)))
            ->orderBy('nama')
            ->get();
    }

    /**
     * Each location with its distance in metres from the point, nearest first.
     *
     * @return Collection<int, array{0: self, 1: int}>
     */
    public static function measure(Collection $locations, float $lat, float $lng): Collection
    {
        return $locations
            ->map(fn (self $l) => [$l, (int) round(Geo::distanceKm($lat, $lng, $l->lat, $l->lng) * 1000)])
            ->sortBy(fn ($m) => $m[1])
            ->values();
    }
}
