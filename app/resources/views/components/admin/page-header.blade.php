@props(['title'])

<header {{ $attributes->class('adm-page-head') }}>
    <div>
        <h1>{{ $title }}</h1>
        @if($slot->isNotEmpty())
            <p>{{ $slot }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="adm-page-head-actions">{{ $actions }}</div>
    @endisset
</header>
