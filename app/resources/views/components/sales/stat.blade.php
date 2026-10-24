@props(['value', 'label'])

<div {{ $attributes->merge(['class' => 'sales-stat']) }}>
    <b>{{ $value }}</b>
    <span>{{ $label }}</span>
</div>
