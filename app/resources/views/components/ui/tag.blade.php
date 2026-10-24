@props(['variant' => 'neutral', 'color' => null])

@if($color)
    <span {{ $attributes->merge(['class' => 'tag']) }} style="background:color-mix(in srgb, {{ $color }} 18%, white); color:color-mix(in srgb, {{ $color }} 60%, black);">{{ $slot }}</span>
@else
    <span {{ $attributes->merge(['class' => 'tag tag-'.$variant]) }}>{{ $slot }}</span>
@endif
