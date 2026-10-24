@props(['href' => null])

@if($href)
    <a href="{{ $href }}" {{ $attributes->class('adm-list-row') }}>{{ $slot }}</a>
@else
    <div {{ $attributes->class('adm-list-row is-static') }}>{{ $slot }}</div>
@endif
