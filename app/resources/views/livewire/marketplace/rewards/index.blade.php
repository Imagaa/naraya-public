@php
    $pts = fn (int $n) => number_format($n, 0, ',', '.');
    $zone = config('app.business_timezone');
    $statusLook = [
        'diajukan' => ['neutral', 'clock'],
        'disetujui' => ['brand', 'circle-check'],
        'diserahkan' => ['ok', 'package-check'],
        'ditolak' => ['sale', 'circle-x'],
        'dibatalkan' => ['sale', 'circle-x'],
    ];
@endphp
<div class="page">
    <x-mp.header active="account" />

    <main class="container pg">
        @error('redeem')
            <p class="note sale" role="alert"><x-lucide-circle-alert class="ico" /><span>{{ $message }}</span></p>
        @enderror

        <section class="pts-hero" aria-labelledby="h-pts">
            <div class="pts-big">
                <span id="h-pts">Poin Anda</span>
                <b class="num">{{ $pts($pointBalance) }}<small>poin</small></b>
                <p>1 poin untuk tiap {{ $progress['per'] }} pcs mainan Naraya Toys yang terjual di toko Anda.</p>
                <p class="num">{{ $progress['pcs'] }} / {{ $progress['per'] }} pcs menuju poin berikutnya</p>
            </div>
            @if($nextReward)
                <div class="next">
                    <div class="next-top">
                        <div class="ph" style="--c: #F0B90B">
                            @if($nextReward->photo_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($nextReward->photo_path) }}" alt="{{ $nextReward->name }}">
                            @else
                                <x-lucide-gift class="ph-icon" />
                            @endif
                        </div>
                        <span><b>{{ $nextReward->name }}</b><span class="num">Kurang {{ $pts($nextReward->point_cost - $pointBalance) }} poin lagi</span></span>
                    </div>
                    <div class="meter" role="meter" aria-label="Progres ke {{ $nextReward->name }}" aria-valuemin="0" aria-valuemax="{{ $nextReward->point_cost }}" aria-valuenow="{{ $pointBalance }}">
                        <i style="--pct: {{ min(100, round($pointBalance / $nextReward->point_cost * 100)) }}%"></i>
                    </div>
                    <div class="meter-cap num"><span>{{ $pts($pointBalance) }}</span><span>{{ $pts($nextReward->point_cost) }} poin</span></div>
                </div>
            @endif
        </section>

        <ol class="how" aria-label="Cara kerja poin">
            <li><span class="tile"><x-lucide-shopping-cart class="ico" /></span><span><b>Jual mainan Naraya Toys</b><span>Setiap {{ $progress['per'] }} pcs terjual di toko jadi 1 poin</span></span></li>
            <li><span class="tile"><x-lucide-circle-check class="ico" /></span><span><b>Poin masuk otomatis</b><span>Dihitung dari nota sales, barang titipan dihitung saat terjual</span></span></li>
            <li><span class="tile"><x-lucide-gift class="ico" /></span><span><b>Tukar, diantar sales</b><span>Admin menyetujui, sales menyerahkan hadiah</span></span></li>
        </ol>

        <section class="stack" aria-labelledby="h-rw">
            <div class="panel-head"><h2 id="h-rw">Hadiah tersedia</h2><span class="muted tiny num">{{ $rewards->count() }} hadiah</span></div>
            @if($rewards->isEmpty())
                <section class="panel"><x-mp.empty icon="gift" title="Belum ada hadiah tersedia." text="Hadiah baru akan muncul di sini." /></section>
            @else
                <div class="rewards">
                    @foreach($rewards as $reward)
                        <article class="reward" wire:key="reward-{{ $reward->id }}">
                            <div class="ph" style="--c: #F0B90B">
                                @if($reward->photo_path)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($reward->photo_path) }}" alt="{{ $reward->name }}" loading="lazy">
                                @else
                                    <x-lucide-gift class="ph-icon" />
                                @endif
                                @if($reward->stock > 0)<x-mp.status class="stock num">Sisa {{ $reward->stock }}</x-mp.status>@endif
                            </div>
                            <h3>{{ $reward->name }}</h3>
                            <p class="cost num"><x-lucide-coins class="ico" />{{ $pts($reward->point_cost) }} poin</p>
                            @if($reward->stock < 1)
                                <button type="button" class="btn btn-secondary btn-sm btn-block" disabled>Stok habis</button>
                            @elseif($pointBalance >= $reward->point_cost)
                                <button type="button" class="btn btn-primary btn-sm btn-block" wire:click="redeem({{ $reward->id }})">Tukar</button>
                            @else
                                <div class="short">
                                    <div class="meter" role="meter" aria-label="Progres ke {{ $reward->name }}" aria-valuemin="0" aria-valuemax="{{ $reward->point_cost }}" aria-valuenow="{{ $pointBalance }}">
                                        <i style="--pct: {{ min(100, round($pointBalance / $reward->point_cost * 100)) }}%"></i>
                                    </div>
                                    <span class="num">Kurang {{ $pts($reward->point_cost - $pointBalance) }} poin</span>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="panel" aria-labelledby="h-hist">
            <div class="panel-head"><h2 id="h-hist">Riwayat penukaran</h2></div>
            @forelse($myRedemptions as $redemption)
                @php
                    [$tone, $icon] = $statusLook[$redemption->status->value] ?? ['neutral', 'clock'];
                    $refunded = in_array($redemption->status->value, ['ditolak', 'dibatalkan'], true);
                    $when = collect([
                        $redemption->created_at->timezone($zone)->locale('id')->translatedFormat('j M Y'),
                        $redemption->diserahkan_at ? 'diserahkan '.$redemption->diserahkan_at->timezone($zone)->locale('id')->translatedFormat('j M') : null,
                        $refunded ? 'poin dikembalikan' : null,
                    ])->filter()->implode(' · ');
                @endphp
                <div class="hist-row" wire:key="redemption-{{ $redemption->id }}">
                    <span class="tile plain"><x-lucide-gift class="ico" /></span>
                    <span><b>{{ $redemption->reward?->name ?? 'Hadiah' }}</b><span class="num">{{ $when }}</span></span>
                    <span class="r">
                        <x-mp.status :tone="$tone" :icon="$icon">{{ $redemption->status->label() }}</x-mp.status>
                        @if($refunded)<s class="num">−{{ $pts($redemption->point_cost) }}</s>@else<em class="num">−{{ $pts($redemption->point_cost) }}</em>@endif
                    </span>
                </div>
            @empty
                <x-mp.empty icon="history" title="Belum ada riwayat penukaran." />
            @endforelse
        </section>
    </main>

</div>
