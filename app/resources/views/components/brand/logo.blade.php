{{--
    Naraya logo from the brand kit (public/brand, source: mockups/logo-naraya). Never retype the wordmark.
    line: naraya | grosir | toys | investasi · variant: horizontal | stacked | symbol | wordmark
    tone: color | reversed (white wordmark, for dark grounds) | black / white (one colour, e.g. notes)
    Kit rules: line symbols need ≥ 32px, line lockups ≥ 160px wide; never on a yellow ground.
--}}
@props(['line' => 'naraya', 'variant' => 'horizontal', 'tone' => 'color'])
@php
    $name = ($line === 'naraya' ? 'naraya' : 'naraya-'.$line).'-'.$variant;
    // a symbol is the same on dark grounds; only lockups and the wordmark have a reversed file
    $suffix = match ($tone) {
        'reversed' => $variant === 'symbol' ? '' : '-reversed',
        'black', 'white' => '-'.$tone,
        default => '',
    };
    $alt = $line === 'naraya' ? 'naraya' : 'naraya '.$line;
@endphp
<img src="{{ asset('brand/'.$name.$suffix.'.svg') }}" alt="{{ $alt }}" {{ $attributes->class(['brand-logo', 'brand-'.$variant]) }}>
