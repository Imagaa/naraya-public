@props(['order'])
@php $p = $order->buyerProgress(); @endphp
@if($p['step'] > 0)
    <div {{ $attributes->merge(['class' => 'prog']) }}>
        <div @class(['prog-bar', 'info' => $p['tone'] === 'info', 'ok' => $p['tone'] === 'ok'])>
            @for($i = 1; $i <= 4; $i++)<i @class(['on' => $i <= $p['step']])></i>@endfor
        </div>
        <p><x-dynamic-component :component="'lucide-'.$p['icon']" class="ico" />{{ $p['note'] }}</p>
    </div>
@else
    <p {{ $attributes->merge(['class' => 'prog-note muted']) }}>{{ $p['note'] }}</p>
@endif
