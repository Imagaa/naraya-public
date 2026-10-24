{{-- A sales person's face wherever they appear: their profile photo, or initials until they upload one.
     Self-styled so it renders the same in the admin, gudang, sales and marketplace stylesheets. --}}
@props(['user', 'size' => 32])
@php
    $url = $user?->salesProfile?->photoUrl();
    $initials = collect(explode(' ', (string) $user?->name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp
@if($url)
    <img src="{{ $url }}" alt="Foto {{ $user->name }}" width="{{ $size }}" height="{{ $size }}" {{ $attributes->merge(['style' => "width:{$size}px;height:{$size}px;border-radius:50%;object-fit:cover;flex:none;display:inline-block;vertical-align:middle"]) }}>
@else
    <span aria-hidden="true" {{ $attributes->merge(['style' => "width:{$size}px;height:{$size}px;border-radius:50%;background:#16181D;color:#F0B90B;display:inline-grid;place-items:center;font-weight:700;font-size:".round($size * 0.38)."px;flex:none;vertical-align:middle"]) }}>{{ $initials }}</span>
@endif
