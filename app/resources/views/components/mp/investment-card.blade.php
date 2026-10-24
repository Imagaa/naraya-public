{{-- Investasi Naraya on the marketplace home (both storefronts): a calculator until the store has bought, then this month's income. --}}
@props(['portfolio' => null])
@php
    $t = app(\App\Support\Investment\Investments::class)->termsFor(auth()->user());
    $rp = fn ($v) => 'Rp'.number_format($v, 0, ',', '.');
    $pct = rtrim(rtrim(number_format($t['monthly_return'] / $t['package_price'] * 100, 1, ',', ''), '0'), ',');
    $pctYear = rtrim(rtrim(number_format($t['monthly_return'] / $t['package_price'] * 1200, 1, ',', ''), '0'), ',');
@endphp
<section class="container inv-wrap" aria-labelledby="inv-title">
    @if($portfolio && $portfolio['has_any'])
        <a class="inv-card inv-active" href="{{ route('investments.index') }}">
            <x-brand.logo line="investasi" class="inv-brand" />
            <span class="inv-head"><span id="inv-title">Pendapatan investasi bulan ini</span><x-lucide-chevron-right class="ico" /></span>
            <b class="inv-big num">{{ $rp($portfolio['this_month']) }}</b>
            <span class="inv-chips">
                <span>{{ $portfolio['active_packages'] }} paket aktif</span>
                @if($portfolio['processing_packages'])<span>{{ $portfolio['processing_packages'] }} diproses</span>@endif
                @if($portfolio['next_payout'])<span>Masuk {{ $portfolio['next_payout']->locale('id')->translatedFormat('j M') }}</span>@endif
                <span>Saldo {{ $rp($portfolio['balance']) }}</span>
            </span>
        </a>
    @else
        <div class="inv-card inv-calc-card" x-data="investCalc(10, {{ $t['package_price'] }}, {{ $t['monthly_return'] }}, {{ $t['months'] }})">
            <div class="inv-col">
                <x-brand.logo line="investasi" class="inv-brand" />
                <h2 id="inv-title">Uang toko ikut bekerja, cair tiap bulan</h2>
                <p class="inv-lead">{{ $rp($t['package_price']) }} per paket · {{ $rp($t['monthly_return']) }}/bulan, {{ $t['months'] }} kali{{ $t['first'] ? ' mulai bulan ke-2' : '' }} · modal kembali utuh.</p>
                <div class="inv-presets" role="group" aria-label="Jumlah paket">
                    @foreach([1, 10, 30, 50] as $n)
                        <button type="button" x-on:click="pick({{ $n }})" x-bind:aria-pressed="qty === {{ $n }}">{{ $n }}</button>
                    @endforeach
                </div>
                <label class="inv-slider">
                    <input type="range" min="1" max="100" x-model.number="qty" aria-label="Jumlah paket">
                    <b class="num"><span x-text="qty"></span> paket</b>
                </label>
            </div>
            <div class="inv-col">
                <dl class="inv-result">
                    <div><dt>Modal</dt><dd class="num" x-text="modal"></dd></div>
                    <div><dt>Per bulan</dt><dd class="num inv-hl" x-text="perMonth"></dd></div>
                    <div><dt>{{ $t['months'] }} bulan</dt><dd class="num" x-text="perYear"></dd></div>
                    <div><dt>Total diterima</dt><dd class="num" x-text="total"></dd></div>
                </dl>
                <p class="inv-note">{{ $pct }}% per bulan · {{ $pctYear }}% per tahun · modal kembali di bulan ke-{{ $t['term'] }}</p>
                <a class="btn btn-primary inv-cta" href="{{ route('investments.index') }}">Mulai investasi</a>
            </div>
        </div>
    @endif
</section>
