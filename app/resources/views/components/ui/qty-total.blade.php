{{--
    The total of several goods with its unit: "52 lembar" (and "3.000 pcs" under it) when they share a unit,
    otherwise in pcs. `items`: [Product|null, pcs] pairs.
--}}
@props(['items'])
@php
    $items = collect($items);
    $pcs = (int) $items->sum(fn ($x) => $x[1]);
    $units = $items->map(fn ($x) => $x[0]?->unit ?: 'pcs')->unique();
    $byUnit = $units->count() === 1 && $units->first() !== 'pcs' && $items->every(fn ($x) => $x[0] && $x[0]->ppu() > 1);
    $whole = $byUnit ? (int) $items->sum(fn ($x) => intdiv($x[1], $x[0]->ppu())) : 0;
    $loose = $byUnit ? (int) $items->sum(fn ($x) => $x[1] % $x[0]->ppu()) : 0;
    $n = fn ($v) => number_format($v, 0, ',', '.');
@endphp
<span {{ $attributes->class('qty-total') }}>
    @if($byUnit)
        <b>{{ $n($whole) }} {{ $units->first() }}@if($loose) {{ $n($loose) }} pcs @endif</b>
        <small>{{ $n($pcs) }} pcs</small>
    @else
        <b>{{ $n($pcs) }} pcs</b>
    @endif
</span>
