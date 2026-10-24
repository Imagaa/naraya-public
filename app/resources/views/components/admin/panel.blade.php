@props(['title' => null])

<section {{ $attributes->class('adm-panel') }}>
    @if($title || isset($actions))
        <div class="adm-panel-head">
            <h2>{{ $title }}</h2>
            @isset($actions)<div class="adm-panel-actions">{{ $actions }}</div>@endisset
        </div>
    @endif
    {{ $slot }}
</section>
