{{-- The day's shift (App\Support\Sales\Shift::recap): distance, time, stores, sales, GPS gaps; $full lists each store. --}}
@props(['recap', 'full' => true])
@php
    $rp = fn ($v) => 'Rp '.number_format($v, 0, ',', '.');
    $wib = fn ($t) => $t->copy()->timezone(\App\Support\BusinessDay::zone())->format('H:i');
    $hours = intdiv($recap['minutes'], 60).'j '.($recap['minutes'] % 60).'m';
@endphp
<div {{ $attributes->merge(['class' => 'recap']) }}>
    <div class="recap-stats">
        <div><b class="num">{{ number_format($recap['km'], 1, ',', '.') }} km</b><small>perjalanan</small></div>
        <div><b class="num">{{ count($recap['stores']) }}</b><small>toko dikunjungi</small></div>
        <div><b class="num">{{ $hours }}</b><small>{{ $wib($recap['start']) }}–{{ $recap['open'] ? 'sekarang' : $wib($recap['end']) }}</small></div>
    </div>
    <p class="sales-note">Penjualan <b>{{ $rp($recap['total']) }}</b> · tunai {{ $rp($recap['cash']) }} · transfer {{ $rp($recap['transfer']) }}</p>
    @if($recap['gaps'])
        <p class="recap-gap">GPS tidak tercatat {{ collect($recap['gaps'])->map(fn ($g) => $wib($g[0]).'–'.$wib($g[1]))->join(', ') }} — buka aplikasi dan nyalakan lokasi selama bekerja.</p>
    @endif
    @if($full && $recap['stores'])
        <ol class="recap-stores">
            @foreach($recap['stores'] as $s)
                <li wire:key="rs-{{ $s['id'] }}">
                    <time>{{ $wib($s['at']) }}</time>
                    <span>{{ $s['name'] }}@if($s['closed']) <small class="sales-row-meta">· tutup</small>@endif</span>
                    <b class="num">{{ $s['notes'] ? $rp($s['total']) : '—' }}</b>
                </li>
            @endforeach
        </ol>
    @endif
</div>
