@props(['label' => null, 'type' => 'text'])

<div class="field">
    @if($label)
        <label>{{ $label }}</label>
    @endif
    <input type="{{ $type }}" {{ $attributes->merge(['class' => 'input']) }}>
    {{ $slot }}
</div>
