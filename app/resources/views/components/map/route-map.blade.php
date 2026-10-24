{{--
    Preview of the driving route from Sales's current location (browser
    geolocation, falling back server-side to the warehouse point if denied)
    to the order's delivery pinpoint. "Mulai Perjalanan" hands off to the
    Google Maps app for actual turn-by-turn navigation — a plain deep link,
    not an API call, so it costs nothing and needs no key.
--}}
@props(['destLat', 'destLng'])

<div class="field">
    <label>Rute Pengantaran</label>
    <div
        wire:ignore
        x-data="routeMap({{ $destLat }}, {{ $destLng }})"
    >
        <div x-ref="routeMapEl" class="route-map"></div>
        <p class="text-muted" style="font-size:12px;margin:6px 0" x-show="loading">Mencari lokasi Anda...</p>
    </div>
    <x-ui.button
        tag="a" variant="primary" class="btn-block"
        href="https://www.google.com/maps/dir/?api=1&destination={{ $destLat }},{{ $destLng }}&travelmode=driving"
        target="_blank" rel="noopener"
        style="margin-top:8px;text-align:center"
    >Mulai Perjalanan</x-ui.button>
</div>
