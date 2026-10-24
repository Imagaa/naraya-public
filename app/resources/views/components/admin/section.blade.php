@props(['title', 'aside' => null])

<section {{ $attributes->class('adm-section') }}>
    <div class="adm-section-head">
        <h2>{{ $title }}</h2>
        @if($aside)<span>{{ $aside }}</span>@endif
    </div>
    {{ $slot }}
</section>
