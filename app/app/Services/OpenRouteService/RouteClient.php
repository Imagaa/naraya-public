<?php

namespace App\Services\OpenRouteService;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RouteClient
{
    /**
     * Driving route from origin to destination as a list of [lat, lng]
     * pairs. Falls back to a straight two-point line (no API key
     * configured, or the request failed) — the map still shows something
     * useful rather than nothing.
     */
    public function route(float $originLat, float $originLng, float $destLat, float $destLng): array
    {
        $straightLine = [[$originLat, $originLng], [$destLat, $destLng]];

        $key = config('services.openrouteservice.key');

        if (! $key) {
            return $straightLine;
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders(['Authorization' => $key])
                ->get('https://api.openrouteservice.org/v2/directions/driving-car/geojson', [
                    'start' => "{$originLng},{$originLat}",
                    'end' => "{$destLng},{$destLat}",
                ]);

            if (! $response->successful()) {
                return $straightLine;
            }

            $coordinates = $response->json('features.0.geometry.coordinates');

            if (! is_array($coordinates) || empty($coordinates)) {
                return $straightLine;
            }

            // GeoJSON order is [lng, lat] — flip to [lat, lng] for Leaflet.
            return array_map(fn ($point) => [$point[1], $point[0]], $coordinates);
        } catch (\Throwable $e) {
            Log::warning('OpenRouteService route lookup failed', ['error' => $e->getMessage()]);

            return $straightLine;
        }
    }
}
