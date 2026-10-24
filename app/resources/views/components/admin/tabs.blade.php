@props(['items'])

<nav {{ $attributes->class('adm-tabs') }}>
    @foreach($items as $route => $label)
        <a href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
