{{-- A band with the marketplace header pattern: title left, optional "Lihat semua ›" right. --}}
@props(['title', 'href' => null, 'tone' => 'plain'])

<section {{ $attributes->merge(['class' => 'sales-section tone-'.$tone]) }}>
    <div class="sec-head">
        <h2>{{ $title }}</h2>
        @if($href)
            <a class="more" href="{{ $href }}">Lihat semua<x-lucide-chevron-right class="ico" /></a>
        @endif
    </div>
    {{ $slot }}
</section>
