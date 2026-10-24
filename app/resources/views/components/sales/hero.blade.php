@props(['realized', 'target', 'href' => null])
@php($pct = $target > 0 ? min(100, (int) round($realized / $target * 100)) : 0)
@php($tag = $href ? 'a' : 'div')

<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'sales-hero']) }}>
    <small>Pencapaian bulan ini</small>
    <strong>@rupiah($realized)</strong>
    <small>dari target @rupiah($target)</small>
    <progress max="100" value="{{ $pct }}" aria-label="Pencapaian target">{{ $pct }}%</progress>
    <span class="sales-hero-pct">{{ $pct }}%</span>
</{{ $tag }}>
