{{--
    Goods as a short list, one per line — name left, quantity right — so a row reads at a glance instead of a comma
    sentence. `items`: [Product|null, pcs] pairs. Past `limit` the rest fold under "+N barang lainnya".
--}}
@props(['items', 'limit' => 4])
@php($items = collect($items)->values())
@php($line = fn ($p, $q) => '<li><span>'.e($p?->name ?? '?').'</span><b>'.e($p?->qtyLabel($q) ?? number_format($q, 0, ',', '.').' pcs').'</b></li>')

@if($items->isEmpty())
    <span class="adm-muted">—</span>
@else
    <ul {{ $attributes->class('item-lines') }}>
        @foreach($items->take($limit) as [$p, $q]){!! $line($p, $q) !!}@endforeach
    </ul>
    @if($items->count() > $limit)
        <details class="item-lines-more">
            <summary>+{{ $items->count() - $limit }} barang lainnya</summary>
            <ul class="item-lines">@foreach($items->slice($limit) as [$p, $q]){!! $line($p, $q) !!}@endforeach</ul>
        </details>
    @endif
@endif
