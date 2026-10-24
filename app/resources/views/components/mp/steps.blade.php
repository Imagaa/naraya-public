@props(['current' => 1])
<ol {{ $attributes->merge(['class' => 'steps']) }} aria-label="Langkah checkout">
    @foreach(['Keranjang', 'Pengiriman', 'Pembayaran'] as $i => $label)
        @php $n = $i + 1; @endphp
        <li @class(['done' => $n < $current, 'on' => $n === $current]) @if($n === $current) aria-current="step" @endif>
            <span class="n">@if($n < $current)<x-lucide-check class="ico" />@else{{ $n }}@endif</span><span class="lbl">{{ $label }}</span>
        </li>
    @endforeach
</ol>
