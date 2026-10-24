{{--
    Read-only reference map: one circle per area that has a lat/lng/radius_km
    set, colored by availability. When wireAreaId is given (the Sales
    assignment form), the currently-selected area is also highlighted —
    the actual "can't pick a taken area" rule is enforced server-side in
    Form::save() and by disabling the option in the <select> above this
    component; the map is purely a visual aid either way.
--}}
@props(['areas', 'currentSalesId' => null, 'wireAreaId' => null, 'label' => 'Peta Area (radius cakupan sales aktif)', 'height' => null])

@php
    $points = $areas->filter(fn ($a) => $a->lat !== null && $a->lng !== null)->values();
    $missingCount = $areas->count() - $points->count();
@endphp

<div class="field">
    @if($label)
        <label>{{ $label }}</label>
    @endif
    <div
        wire:ignore
        x-data="areaCirclesMap(
            @js($points->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'lat' => $a->lat,
                'lng' => $a->lng,
                'radiusKm' => $a->radius_km ?? 3,
                'takenBy' => $a->salesProfiles->first(fn ($p) => $p->user_id !== $currentSalesId)?->user?->name,
            ])->values()),
            @if($wireAreaId) @entangle($wireAreaId) @else null @endif
        )"
    >
        <div x-ref="areaMapEl" class="route-map" @if($height) style="height:{{ $height }}" @endif></div>
    </div>
    @if($missingCount > 0)
        <p class="text-muted" style="font-size:12px;margin:6px 0 0">{{ $missingCount }} area belum punya titik lokasi — atur dulu di halaman Kelola Area.</p>
    @endif
</div>
