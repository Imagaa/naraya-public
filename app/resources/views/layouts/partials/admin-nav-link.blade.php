@php([$route, $match, $label, $icon, $badge] = $link)
@php($active = request()->routeIs(...explode('|', $match)))
<a href="{{ route($route) }}" class="adm-nav-link" data-nav="{{ $label }}"@if($active) aria-current="page"@endif>
    <x-dynamic-component :component="'lucide-'.$icon" width="17" height="17" aria-hidden="true" />
    {{ $label }}
    @if($badge > 0)
        <span class="adm-nav-badge">{{ $badge }}</span>
    @endif
</a>
