<?php

namespace App\Support\Sales;

use App\Models\BuyerProfile;
use App\Models\DeliveryRoute;
use App\Models\DeliveryRouteStop;
use App\Models\User;
use App\Support\BusinessDay;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Sales delivery routes: one active route per sales person. */
final class DeliveryRoutes
{
    public static function active(User $sales): ?DeliveryRoute
    {
        return DeliveryRoute::where('sales_id', $sales->id)->where('status', 'active')->latest('id')->first();
    }

    /**
     * Nearest-neighbour order, from ($lat, $lng) or, without a position, from the first point.
     *
     * @param  array<int, array{0: float, 1: float}>  $points  id => [lat, lng]
     * @return list<int>
     */
    public static function nearestOrder(array $points, ?float $lat, ?float $lng): array
    {
        if ($points === []) {
            return [];
        }
        $order = [];
        if ($lat === null || $lng === null) {
            $first = array_key_first($points);
            [$lat, $lng] = $points[$first];
            $order[] = $first;
            unset($points[$first]);
        }

        // ponytail: flat lat/lng distance — fine at city scale, use haversine if routes span provinces
        while ($points !== []) {
            $next = collect($points)->sortBy(fn ($p) => ($p[0] - $lat) ** 2 + ($p[1] - $lng) ** 2)->keys()->first();
            [$lat, $lng] = $points[$next];
            $order[] = $next;
            unset($points[$next]);
        }

        return $order;
    }

    /** @param list<int> $buyerIds in stop order */
    public function start(User $sales, array $buyerIds): DeliveryRoute
    {
        $buyerIds = array_values(array_unique(array_map('intval', $buyerIds)));
        if ($buyerIds === []) {
            throw ValidationException::withMessages(['route' => 'Pilih minimal satu toko.']);
        }
        if (BuyerProfile::whereIn('user_id', $buyerIds)->where('sales_user_id', $sales->id)->count() !== count($buyerIds)) {
            throw ValidationException::withMessages(['route' => 'Ada toko yang tidak ditugaskan ke Anda.']);
        }

        return DB::transaction(function () use ($sales, $buyerIds) {
            if (DeliveryRoute::where('sales_id', $sales->id)->where('status', 'active')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['route' => 'Selesaikan rute yang sedang berjalan dulu.']);
            }
            $route = DeliveryRoute::create(['sales_id' => $sales->id, 'date' => BusinessDay::today()->toDateString(), 'status' => 'active']);
            foreach ($buyerIds as $i => $buyerId) {
                $route->stops()->create(['buyer_id' => $buyerId, 'position' => $i + 1]);
            }

            return $route->load('stops');
        });
    }

    /** The stop on the sales person's active route for this store, if $stopId is one. */
    public static function stopFor(User $sales, ?int $stopId, int $buyerId): ?DeliveryRouteStop
    {
        return $stopId ? DeliveryRouteStop::whereKey($stopId)->where('buyer_id', $buyerId)
            ->whereHas('route', fn ($q) => $q->where('sales_id', $sales->id)->where('status', 'active'))->first() : null;
    }

    public function close(DeliveryRouteStop $stop, ?UploadedFile $photo, ?string $note): void
    {
        if (! $photo) {
            throw ValidationException::withMessages(['closedPhoto' => 'Foto toko yang tutup wajib diambil.']);
        }
        $stop->update([
            'status' => 'closed',
            'closed_photo_path' => $photo->store('route-stops', 's3'),
            'closed_note' => trim((string) $note) ?: null,
        ]);
    }

    /**
     * Google Maps turn-by-turn through stores ({lat, lng} arrays): the last is the destination, the rest (max 9)
     * waypoints. Null without a located store.
     */
    public static function mapsUrl(array $points): ?string
    {
        $coords = array_map(fn ($p) => $p['lat'].','.$p['lng'], array_values(array_filter($points, fn ($p) => $p['lat'] !== null && $p['lng'] !== null)));
        if ($coords === []) {
            return null;
        }
        $destination = array_pop($coords);

        return 'https://www.google.com/maps/dir/?'.http_build_query(array_filter([
            'api' => 1,
            'destination' => $destination,
            'waypoints' => implode('|', array_slice($coords, 0, 9)) ?: null,
            'travelmode' => 'driving',
            'dir_action' => 'navigate',
        ]));
    }

    public function finish(DeliveryRoute $route): void
    {
        $route->update(['status' => 'finished', 'finished_at' => now()]);
    }
}
