@props(['title'])

<div {{ $attributes->class('adm-queue-group') }}>
    <h3>{{ $title }}</h3>
    {{ $slot }}
</div>
