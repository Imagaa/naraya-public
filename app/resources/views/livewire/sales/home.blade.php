{{-- Kerja: the sales app's one working page. Tasks open as sheets. --}}
@php
    $rp = fn ($v) => 'Rp '.number_format($v, 0, ',', '.');
    $pending = $stores->where('status', 'pending');
    $navigateAll = $route ? \App\Support\Sales\DeliveryRoutes::mapsUrl($pending->filter(fn ($s) => $s['lat'] !== null)->take(10)->values()->all()) : null;
@endphp
<div>
    <x-sales.page>
        @if($flash || session('absen_ok'))<p class="absen-chip is-ok" role="status">{{ $flash ?? session('absen_ok') }}</p>@endif

        {{-- no signal: the outbox and the light offline forms (data-offline buttons below open them) --}}
        @include('livewire.sales.partials.offline')

        {{-- Absen, with the greeting: any time of day; check-in also takes the stock and starts the day's route --}}
        <section class="work-card" aria-labelledby="h-absen">
            <div class="work-head">
                <h2 id="h-absen">Halo, {{ auth()->user()->name }}</h2>
                <span class="work-times">
                    Masuk <b>{{ $masuk?->created_at->wib()->format('H:i') ?? '—' }}</b> · Pulang <b>{{ $pulang?->created_at->wib()->format('H:i') ?? '—' }}</b>
                </span>
            </div>
            @if(! $masuk)
                <p class="absen-hello">Jangan lupa absen!</p>
                <x-ui.button variant="primary" class="btn-block" wire:click="open('masuk')">Absen masuk &amp; pilih stok</x-ui.button>
            @elseif(! $pulang)
                <x-ui.button variant="secondary" class="btn-block" wire:click="open('pulang')" data-offline-guard>Absen pulang</x-ui.button>
            @else
                <p class="absen-hello">Terima kasih atas kerja kerasnya! Selamat beristirahat!</p>
            @endif
        </section>

        {{-- The day's shift: live while checked in, the full recap after check-out --}}
        @if($recap)
            <section class="work-card" aria-labelledby="h-recap">
                <div class="work-head"><h2 id="h-recap">{{ $recap['open'] ? 'Perjalanan hari ini' : 'Rekap hari ini' }}</h2></div>
                @if($recap['open'])
                    <x-sales.shift-recap :recap="$recap" :full="false" />
                    @if($recap['stores'])
                        <details class="work-fold"><summary>Lihat {{ count($recap['stores']) }} toko</summary><x-sales.shift-recap :recap="$recap" class="recap-only-stores" /></details>
                    @endif
                @else
                    <x-sales.shift-recap :recap="$recap" />
                @endif
            </section>
        @endif

        {{-- Stok bawaan --}}
        <section class="work-card" aria-labelledby="h-stok">
            <div class="work-head">
                <h2 id="h-stok">Stok bawaan · {{ $line->label() }}</h2>
                <span class="work-times"><b>{{ $carried->count() }} barang</b> · {{ $rp($carriedValue) }}</span>
            </div>
            @if($carried->isNotEmpty())
                <details class="work-fold">
                    <summary>Lihat {{ $carried->count() }} barang</summary>
                    <ul class="work-items">
                        @foreach($carried as $id => $q)
                            <li wire:key="c-{{ $id }}"><span>{{ $carriedProducts[$id]->name ?? '?' }}</span><b class="num">{{ $carriedProducts[$id]?->qtyLabel($q) ?? $q }}</b></li>
                        @endforeach
                    </ul>
                </details>
            @endif
            <div class="work-actions">
                <x-ui.button variant="secondary" wire:click="open('ambil')"><x-lucide-package-plus class="ico" />Tambah barang</x-ui.button>
                @if($carried->isNotEmpty())<x-ui.button variant="secondary" wire:click="open('retur')"><x-lucide-undo-2 class="ico" />Retur</x-ui.button>@endif
            </div>
        </section>

        {{-- Toko hari ini: the route once started, otherwise the stores
             scheduled for today; every store is one search or the "Semua toko" panel away --}}
        <section class="work-card" aria-labelledby="h-toko" x-data="storePanel">
            <div class="work-head">
                <h2 id="h-toko">{{ $route ? 'Rute hari ini' : 'Toko hari ini · '.$weekday }}</h2>
                <span class="work-times">@if($route)<b>{{ $stores->count() - $pending->count() }}</b> dari {{ $stores->count() }} toko @else {{ $stores->count() }} toko @endif</span>
            </div>
            @if($all->isNotEmpty())<x-sales.store-search :stores="$all" />@endif
            @if($navigateAll)<a class="btn btn-secondary btn-block" href="{{ $navigateAll }}" target="_blank" rel="noopener"><x-lucide-navigation class="ico" />Navigasi rute</a>@endif
            @if($all->isEmpty())
                <x-ui.empty-state message="Belum ada toko yang ditugaskan ke Anda." />
            @elseif($stores->isEmpty())
                <p class="sales-note">Belum ada toko terjadwal hari {{ $weekday }}. Atur jadwal kunjungan mingguan Anda, atau cari toko di atas.</p>
            @else
                @include('livewire.sales.partials.store-rows', ['rows' => $stores, 'actions' => true])
            @endif
            {{-- one tidy block: every store on its own row, then schedule and new store side by side --}}
            <div class="work-store-actions">
                @if($all->isNotEmpty())<x-ui.button variant="secondary" class="is-wide" x-on:click="show()"><x-lucide-list class="ico" />Semua toko saya ({{ $all->count() }})</x-ui.button>@endif
                <x-ui.button variant="secondary" wire:click="open('jadwal')"><x-lucide-calendar-days class="ico" />Atur jadwal</x-ui.button>
                <x-ui.button variant="secondary" wire:click="open('toko-baru')" data-offline="toko-baru"><x-lucide-store class="ico" />Toko baru</x-ui.button>
            </div>

            {{-- Semua toko saya: rendered with the page, so it opens without signal too --}}
            <div class="store-panel" x-show="open" x-cloak x-transition.opacity role="dialog" aria-modal="true" aria-labelledby="h-semua">
                <header class="sales-head">
                    <button type="button" class="sales-back" aria-label="Tutup" x-on:click="hide()"><x-lucide-arrow-left class="ico" /></button>
                    <h1 id="h-semua">Semua toko saya</h1>
                    <span class="work-times">{{ $all->count() }} toko</span>
                </header>
                <div class="store-panel-body">
                    <x-sales.store-search :stores="$all" />
                    @include('livewire.sales.partials.store-rows', ['rows' => $all, 'actions' => false])
                </div>
            </div>
        </section>

        {{-- Setoran + investasi --}}
        <section class="work-card" aria-labelledby="h-cash">
            <div class="work-head">
                <h2 id="h-cash">Cash belum disetor</h2>
                <span class="work-times"><b>{{ $rp($cashHeld) }}</b></span>
            </div>
            <div class="work-actions">
                <x-ui.button :variant="$cashHeld ? 'primary' : 'secondary'" wire:click="open('setor')"><x-lucide-wallet class="ico" />Setor</x-ui.button>
                <x-ui.button variant="secondary" wire:click="open('investasi')"><x-lucide-trending-up class="ico" />Investasi toko</x-ui.button>
            </div>
        </section>

        <x-sales.hero :realized="$realized" :target="$target" />
    </x-sales.page>

    @include('livewire.sales.partials.sheet')
</div>
