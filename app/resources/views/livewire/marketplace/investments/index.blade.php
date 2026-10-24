@php
    $rp = fn ($v) => 'Rp'.number_format($v, 0, ',', '.');
    $tone = ['menunggu_verifikasi' => 'inv-st-wait', 'dibayar' => 'inv-st-wait', 'aktif' => 'inv-st-ok', 'selesai' => 'inv-st-done', 'ditolak' => 'inv-st-bad'];
@endphp
<div class="page">
    <x-mp.header title="Investasi" />

    <main class="container pg inv-page">
        <x-brand.logo line="investasi" class="inv-brand inv-page-brand" />
        @if($flash)<p class="note" role="status"><x-lucide-circle-check class="ico" /><span>{{ $flash }}</span></p>@endif

        @if($p['has_any'] || $p['balance'] > 0)
        <section class="inv-wallet" aria-labelledby="h-wallet">
            <span id="h-wallet" class="inv-label">Dompet investasi</span>
            <b class="inv-big num">{{ $rp($p['balance']) }}</b>
            @foreach($waiting as $w)
                <span class="inv-pending" wire:key="w-{{ $w->id }}">Penarikan {{ $rp($w->amount) }} ke {{ $w->maskedAccount() }} · Menunggu transfer</span>
            @endforeach
            @if(! $withdrawOpen)
                @if($p['balance'] >= \App\Support\Investment\Investments::MIN_WITHDRAWAL)
                    <button type="button" class="btn btn-primary" wire:click="$set('withdrawOpen', true)">Tarik dana</button>
                @else
                    <p class="inv-small">Pendapatan masuk ke dompet ini tiap bulan dan bisa ditarik kapan saja.</p>
                @endif
            @else
                <form wire:submit="withdraw" class="inv-withdraw">
                    <label for="wd-amount">Nominal yang ditarik</label>
                    <input id="wd-amount" class="input" type="number" inputmode="numeric" min="1" max="{{ $p['balance'] }}" wire:model="amount" placeholder="Maks. {{ $rp($p['balance']) }}">
                    @error('amount')<p class="inv-err" role="alert">{{ $message }}</p>@enderror
                    <div class="inv-bank">
                        <span><label for="wd-bank">Bank</label><input id="wd-bank" class="input" wire:model="bank" placeholder="BCA"></span>
                        <span><label for="wd-no">Nomor rekening</label><input id="wd-no" class="input" inputmode="numeric" wire:model="accountNumber"></span>
                    </div>
                    <label for="wd-name">Atas nama (pemilik toko)</label>
                    <input id="wd-name" class="input" wire:model="accountName">
                    @error('bank')<p class="inv-err" role="alert">{{ $message }}</p>@enderror
                    @error('accountNumber')<p class="inv-err" role="alert">{{ $message }}</p>@enderror
                    @error('accountName')<p class="inv-err" role="alert">{{ $message }}</p>@enderror
                    <p class="inv-small">Ditransfer manual oleh admin Naraya, paling lambat 1×24 jam hari kerja.</p>
                    <div class="inv-two">
                        <button type="button" class="btn btn-secondary" wire:click="$set('withdrawOpen', false)">Batal</button>
                        <button type="submit" class="btn btn-primary">Ajukan</button>
                    </div>
                </form>
            @endif
        </section>

        <section class="inv-stats" aria-label="Ringkasan">
            <div><span>Bulan ini</span><b class="num">{{ $rp($p['this_month']) }}</b>@if($p['next_payout'])<small>masuk {{ $p['next_payout']->locale('id')->translatedFormat('j M') }}</small>@endif</div>
            <div><span>Total pendapatan</span><b class="num">{{ $rp($p['total_income']) }}</b>@if($p['first_purchase'])<small>sejak {{ $p['first_purchase']->locale('id')->translatedFormat('M Y') }}</small>@endif</div>
            <div><span>Modal berjalan</span><b class="num">{{ $rp($p['running_capital']) }}</b><small>{{ $p['active_packages'] + $p['processing_packages'] }} paket</small></div>
        </section>

        <section aria-labelledby="h-packages" class="inv-sec">
            <h2 id="h-packages">Paket saya</h2>
            @forelse($p['purchases'] as $buy)
                <article class="inv-pkg" wire:key="pkg-{{ $buy->id }}">
                    <div class="inv-pkg-head"><b>{{ $buy->quantity }} paket · {{ $buy->number() }}</b><span class="inv-st {{ $tone[$buy->status] ?? '' }}">{{ $buy->statusLabel() }}</span></div>
                    @if($buy->status === 'aktif')
                        <div class="inv-row"><span>Bulan ke-{{ $buy->months_paid }} dari {{ $buy->months }}</span><span class="num">{{ $rp($buy->monthlyTotal()) }} / bulan</span></div>
                        <progress max="{{ $buy->months }}" value="{{ $buy->months_paid }}" aria-label="Bulan ke-{{ $buy->months_paid }} dari {{ $buy->months }}">{{ $buy->months_paid }}</progress>
                        <small>Aktif {{ $buy->activated_at->wib()->locale('id')->translatedFormat('j M Y') }} · pendapatan berikutnya {{ $buy->next_payout_on?->locale('id')->translatedFormat('j M Y') }} · modal {{ $rp($buy->amount) }} kembali di bulan ke-{{ $buy->months + $buy->skipped_months }}</small>
                    @elseif($buy->status === 'selesai')
                        <small>Selesai · {{ $buy->months }}× {{ $rp($buy->monthlyTotal()) }} dan modal {{ $rp($buy->amount) }} sudah masuk dompet.</small>
                    @elseif($buy->status === 'ditolak')
                        <small>Bukti pembayaran ditolak: {{ $buy->reject_reason }}</small>
                    @elseif($buy->status === 'menunggu_verifikasi')
                        <small>Bukti {{ $buy->methodLabel() }} {{ $rp($buy->amount) }} sedang diperiksa admin.</small>
                    @else
                        <small>Pembayaran {{ $rp($buy->amount) }} sudah diterima ({{ $buy->methodLabel() }}{{ $buy->salesUser ? ', '.$buy->salesUser->name : '' }}). Paket aktif setelah diproses Naraya; pendapatan pertama {{ $buy->skipped_months ? 'di bulan ke-2' : 'sebulan' }} setelahnya.</small>
                    @endif
                </article>
            @empty
                <p class="muted">Belum ada paket. Mulai dari 1 paket di bawah.</p>
            @endforelse
        </section>

        @else
        <p class="inv-intro-note">Beli paket investasi Naraya: {{ $rp($terms['package_price']) }} per paket, pendapatan {{ $rp($terms['monthly_return']) }} tiap bulan, {{ $terms['months'] }} kali{{ $terms['first'] ? ' mulai bulan ke-2' : '' }}, lalu modal kembali utuh di bulan ke-{{ $terms['term'] }}.</p>
        @endif

        <section aria-labelledby="h-buy" class="inv-buy">
            <h2 id="h-buy">{{ $p['has_any'] ? 'Beli paket lagi' : 'Beli paket' }}</h2>
            @php($stepNames = [1 => 'Jumlah', 2 => 'Cara bayar', 3 => $method === 'wallet' ? 'Saldo' : 'Bukti bayar', 4 => 'Konfirmasi'])
            <ol class="inv-steps" aria-label="Langkah pembelian">
                @foreach($stepNames as $n => $name)
                    <li @class(['is-done' => $n < $buyStep, 'is-now' => $n === $buyStep]) @if($n === $buyStep) aria-current="step" @endif><span class="num">{{ $n }}</span>{{ $name }}</li>
                @endforeach
            </ol>

            @if($buyStep === 1)
                <div class="inv-row">
                    <span>Jumlah paket</span>
                    <div class="step step-lg" role="group" aria-label="Jumlah paket">
                        <button type="button" wire:click="changeQty(-1)" aria-label="Kurangi" @disabled($buyQty <= 1)><x-lucide-minus class="ico" /></button>
                        <span class="num" aria-live="polite">{{ $buyQty }}</span>
                        <button type="button" wire:click="changeQty(1)" aria-label="Tambah"><x-lucide-plus class="ico" /></button>
                    </div>
                </div>
                <dl class="inv-sum">
                    <div><dt>Modal</dt><dd class="num">{{ $rp($buyQty * $terms['package_price']) }}</dd></div>
                    <div><dt>Pendapatan / bulan</dt><dd class="num">{{ $rp($buyQty * $terms['monthly_return']) }}</dd></div>
                    <div><dt>Diterima total dalam {{ $terms['term'] }} bulan</dt><dd class="num">{{ $rp($buyQty * ($terms['monthly_return'] * $terms['months'] + $terms['package_price'])) }}</dd></div>
                </dl>
                @if($terms['first'])<p class="inv-small">Pembelian pertama: pendapatan mulai bulan ke-2, {{ $terms['months'] }} kali. Pembelian berikutnya {{ $terms['term'] }} kali, mulai bulan pertama.</p>@endif
                @error('buyQty')<p class="inv-err" role="alert">{{ $message }}</p>@enderror
                <p class="inv-small">Bisa juga dibayar tunai lewat sales Naraya yang mengunjungi toko Anda.</p>
            @elseif($buyStep === 2)
                <fieldset class="inv-pay">
                    <legend>Bayar dengan apa?</legend>
                    @foreach(['transfer' => ['Transfer bank', 'Ke '.$transferTo.', lalu unggah bukti', 'landmark']] as $value => [$title, $desc, $icon])
                        <label class="inv-pay-opt">
                            <input type="radio" name="method" value="{{ $value }}" wire:model.live="method">
                            <span class="inv-pay-ico"><x-dynamic-component :component="'lucide-'.$icon" class="ico" /></span>
                            <span><b>{{ $title }}</b><small>{{ $desc }}</small></span>
                        </label>
                    @endforeach
                </fieldset>
                @error('method')<p class="inv-err" role="alert">{{ $message }}</p>@enderror
            @elseif($buyStep === 3)
                @if($method === 'wallet')
                    <dl class="inv-sum">
                        <div><dt>Saldo dompet</dt><dd class="num">{{ $rp($p['balance']) }}</dd></div>
                        <div><dt>Dipotong</dt><dd class="num">{{ $rp($buyQty * $terms['package_price']) }}</dd></div>
                        <div><dt>Sisa saldo</dt><dd class="num">{{ $rp($p['balance'] - $buyQty * $terms['package_price']) }}</dd></div>
                    </dl>
                @else
                    <div class="inv-payto">
                        <span>Bayar <b class="num">{{ $rp($buyQty * $terms['package_price']) }}</b> {{ $method === 'qris' ? 'lewat QRIS' : 'dengan transfer' }}</span>
                        <small>{{ $transferTo ? 'Ke '.$transferTo : 'Minta nomor rekening atau QRIS Naraya ke sales Anda.' }}</small>
                    </div>
                    <x-ui.file-picker wire:model="proof" :file="$proof" :label="'Pilih foto bukti '.($method === 'qris' ? 'QRIS' : 'transfer')" hint="Screenshot atau foto struk, maks. 8 MB" />
                @endif
                @error('proof')<p class="inv-err" role="alert">{{ $message }}</p>@enderror
                @error('method')<p class="inv-err" role="alert">{{ $message }}</p>@enderror
            @else
                <dl class="inv-sum">
                    <div><dt>Paket</dt><dd class="num">{{ $buyQty }} paket</dd></div>
                    <div><dt>Dibayar</dt><dd class="num">{{ $rp($buyQty * $terms['package_price']) }} · {{ \App\Models\InvestmentPurchase::METHOD_LABELS[$method] }}</dd></div>
                    <div><dt>Pendapatan / bulan</dt><dd class="num">{{ $rp($buyQty * $terms['monthly_return']) }}</dd></div>
                </dl>
                <details class="inv-terms" open>
                    <summary>Investasi Naraya, singkatnya</summary>
                    <ol>
                        <li><b>1 paket = {{ $rp($terms['package_price']) }}.</b> Beli berapa pun, mulai 1 paket.</li>
                        <li><b>{{ $rp($terms['monthly_return']) }} per paket tiap bulan</b> masuk dompet investasi pada tanggal paket aktif, {{ $terms['months'] }} kali{{ $terms['first'] ? ', mulai bulan ke-2 (pembelian pertama; pembelian berikutnya '.$terms['term'].' kali mulai bulan pertama)' : '' }}.</li>
                        <li><b>Modal kembali utuh</b> ke dompet bersama pendapatan bulan ke-{{ $terms['term'] }}.</li>
                        <li><b>Tarik dana kapan saja</b> ke rekening atas nama pemilik toko. Admin mentransfer paling lambat 1×24 jam hari kerja.</li>
                        <li><b>Paket aktif setelah diproses Naraya</b> begitu pembayaran diterima. Paket yang sudah aktif tidak bisa dibatalkan.</li>
                    </ol>
                </details>
                <x-ui.checkbox label="Saya sudah membaca dan setuju" wire:model="agree" />
                @error('agree')<p class="inv-err" role="alert">{{ $message }}</p>@enderror
                @error('quantity')<p class="inv-err" role="alert">{{ $message }}</p>@enderror
            @endif

            <div class="inv-nav">
                @if($buyStep > 1)<button type="button" class="btn btn-secondary" wire:click="prevStep">Kembali</button>@endif
                @if($buyStep < 4)
                    <button type="button" class="btn btn-primary" wire:click="nextStep" wire:loading.attr="disabled">Lanjut</button>
                @else
                    <button type="button" class="btn btn-primary" wire:click="buy" wire:loading.attr="disabled">Beli {{ $buyQty }} paket</button>
                @endif
            </div>
        </section>

        @if($entries->isNotEmpty())
        <section aria-labelledby="h-history" class="inv-sec">
            <h2 id="h-history">Riwayat dompet</h2>
            @foreach($entries as $e)
                <div class="inv-entry" wire:key="e-{{ $e->id }}">
                    <span><b>{{ $e->note }}</b><small>{{ $e->created_at->wib()->locale('id')->translatedFormat('j M Y') }}</small></span>
                    <b @class(['num', 'inv-plus' => $e->amount > 0])>{{ $e->amount > 0 ? '+' : '−' }}{{ $rp(abs($e->amount)) }}</b>
                </div>
            @endforeach
        </section>
        @endif
    </main>
</div>
