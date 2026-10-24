{{-- A full-width band: alternate tone="plain" / "tint"; one shared header pattern. --}}
@props(['tone' => 'plain', 'title' => null, 'subtitle' => null, 'moreUrl' => null, 'moreLabel' => 'Lihat semua'])
@php($headingId = $title ? 'h-'.\Illuminate\Support\Str::slug($title).'-'.substr(md5($title.$subtitle), 0, 4) : null)
<section {{ $attributes->merge(['class' => 'seg seg-'.$tone]) }} @if($headingId) aria-labelledby="{{ $headingId }}" @endif>
    <div class="container">
        @if($title)
            <div class="sec-head">
                <div>
                    <h2 id="{{ $headingId }}">{{ $title }}</h2>
                    @if($subtitle)<p>{{ $subtitle }}</p>@endif
                </div>
                @if($moreUrl)<a class="more" href="{{ $moreUrl }}">{{ $moreLabel }}<x-lucide-chevron-right class="ico" /></a>@endif
            </div>
        @endif
        {{ $slot }}
    </div>
</section>
