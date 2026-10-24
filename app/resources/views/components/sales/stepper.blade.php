@props(['stage'])
@php($current = array_search($stage, array_keys(\App\Models\Order::SALES_STAGE_META), true))

<ol {{ $attributes->merge(['class' => 'sales-stepper']) }}>
    @foreach(\App\Models\Order::SALES_STAGE_META as $key => $meta)
        <li @class(['is-done' => $loop->index < $current, 'is-current' => $loop->index === $current]) @if($loop->index === $current) aria-current="step" @endif>{{ $meta['label'] }}</li>
    @endforeach
</ol>
