@props(['status'])
@php
    $tone = match ($status) {
        \App\Enums\OrderStatus::New => 'new',
        \App\Enums\OrderStatus::Processing => 'info',
        \App\Enums\OrderStatus::Shipped => 'ship',
        \App\Enums\OrderStatus::Completed => 'ok',
        default => 'bad',
    };
@endphp
<x-admin.pill :tone="$tone" :attributes="$attributes">{{ $status->label() }}</x-admin.pill>
