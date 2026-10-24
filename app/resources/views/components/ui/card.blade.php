@props(['title' => null])

<div {{ $attributes->merge(['class' => 'card']) }}>
    @if($title)
        <div class="card-title">{{ $title }}</div>
    @endif
    {{ $slot }}
</div>
