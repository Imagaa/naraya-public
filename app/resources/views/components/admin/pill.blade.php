@props(['tone' => 'muted'])

<span {{ $attributes->class(['adm-pill', 'tone-'.$tone]) }}>{{ $slot }}</span>
