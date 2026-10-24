{{--
    The one card action row: [secondary] [primary], right-aligned;
    on phones two actions split the width and a lone action is full width.
--}}
@php
    $hasSecondary = isset($secondary) && trim((string) $secondary) !== '';
    $hasPrimary = isset($primary) && trim((string) $primary) !== '';
@endphp
@if($hasSecondary || $hasPrimary)
    <div {{ $attributes->merge(['class' => 'actions'.($hasSecondary && $hasPrimary ? '' : ' is-single')]) }}>
        @if($hasSecondary){{ $secondary }}@endif
        @if($hasPrimary){{ $primary }}@endif
    </div>
@endif
