{{--
    Leaflet + OpenFreeMap (Fiord) pinpoint picker — click or drag the marker
    to set a location. Writes to the host Livewire component's $wireLat/
    $wireLng properties via $wire.set(..., false) (deferred, no network
    round trip until the form is actually submitted).

    Requires an ancestor Alpine scope exposing `lat`/`lng` (the initial
    value, e.g. via `lat: @entangle('newLat')` on a wrapping x-data) — this
    component only ever reads those once at init and then owns its own
    reactive copy, so it doesn't need Blade to resolve them as PHP
    variables (this partial is nested inside other anonymous components
    that don't inherit the host Livewire component's property scope).

    wire:ignore is required: Livewire must never touch this subtree, or a
    re-render can morph out from under the live Leaflet/MapLibre instance.
--}}
@props(['wireLat', 'wireLng'])

<div class="field">
    <label>Titik Lokasi (klik atau geser pin ke posisi tepat)</label>
    <div
        wire:ignore
        x-data="pinpointPicker(lat, lng, '{{ $wireLat }}', '{{ $wireLng }}')"
        x-on:address-village-resolved.window="recenter($event.detail.lat, $event.detail.lng)"
        x-on:pinpoint-locate.window="placeAt($event.detail.lat, $event.detail.lng)"
        x-on:pinpoint-radius.window="setRadius($event.detail)"
    >
        <div x-ref="pinpointMapEl" class="pinpoint-map"></div>
        <p class="text-muted" style="font-size:12px;margin:6px 0 0" x-show="lat && lng">
            <span x-text="lat?.toFixed(6)"></span>, <span x-text="lng?.toFixed(6)"></span>
        </p>
        <p class="text-danger" style="font-size:12px;margin:6px 0 0" x-show="!lat || !lng">Klik peta untuk menandai lokasi.</p>
    </div>
</div>
