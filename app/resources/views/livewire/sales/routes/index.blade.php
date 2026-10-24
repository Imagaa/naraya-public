@php($rp = fn ($v) => 'Rp '.number_format($v, 0, ',', '.'))
<div>
<x-sales.page title="Rute">
    @if($flash)<p class="absen-chip is-ok" role="status">{{ $flash }}</p>@endif
    @if($mapPoints !== [])
        {{-- keyed on which stores are on the map, not on which are ticked: ticking only re-colours a pin, the map is never rebuilt --}}
        <div wire:ignore wire:key="route-map-{{ md5(json_encode(array_map(fn ($p) => [$p['id'], $p['no'], $p['lat'], $p['lng']], $mapPoints))) }}">
            <div x-data="routePlannerMap(@js($mapPoints), @js($route !== null || $step === 'atur'))" class="route-map-wrap"
                 @if(! $route && $step === 'pilih') x-effect="mark($wire.selected)" @endif>
                <div x-ref="mapEl" class="route-map"></div>
            </div>
        </div>
    @endif
    @error('route')<p class="text-danger" role="alert">{{ $message }}</p>@enderror

    @if(! $route && $step === 'pilih')
        <x-sales.section title="Pilih toko">
            <p class="sales-note">Centang toko yang akan dikunjungi hari ini.</p>
            <div class="route-pick-tools">
                <x-ui.input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari toko…" aria-label="Cari toko" />
                <x-ui.button variant="secondary" wire:click="selectAll">Pilih semua</x-ui.button>
            </div>
            @if($stores->isEmpty())
                <x-ui.empty-state message="Belum ada toko yang ditugaskan ke Anda." />
            @else
                <div class="sales-list">
                    @foreach($stores as $p)
                        <div class="sales-row route-pick-row" wire:key="pick-{{ $p->user_id }}">
                            <span class="route-stop-no">{{ $numbers[$p->user_id] }}</span>
                            <x-ui.checkbox :label="$p->user?->name" wire:model.live="selected" value="{{ $p->user_id }}" />
                            @if($p->latitude === null)<small class="sales-row-meta">Tanpa titik — lengkapi lokasi toko</small>@endif
                        </div>
                    @endforeach
                </div>
            @endif
            <div x-data="{ busy: false, go() { this.busy = true; const done = (lat, lng) => $wire.arrange(lat, lng).then(() => this.busy = false); if (! navigator.geolocation) return done(null, null); navigator.geolocation.getCurrentPosition((p) => done(p.coords.latitude, p.coords.longitude), () => done(null, null), { timeout: 8000 }); } }" class="route-actions">
                <button type="button" class="btn btn-primary btn-block" x-on:click="go()" x-bind:disabled="busy" @disabled($selected === [])>
                    <span x-show="! busy">Atur jalur ({{ count($selected) }} toko)</span><span x-show="busy" x-cloak>Mencari posisi Anda…</span>
                </button>
            </div>
        </x-sales.section>
    @elseif(! $route)
        <x-sales.section title="Atur jalur">
            <p class="sales-note">Urutan awal: toko terdekat lebih dulu dari posisi Anda. Ubah dengan tombol panah.</p>
            <ol class="route-stops" data-hold-refresh>{{-- an order being arranged: an app update waits (sales-pwa.js) --}}
                @foreach($ordered as $i => $p)
                    <li class="route-stop" wire:key="order-{{ $p->user_id }}">
                        <span class="route-stop-no">{{ $i + 1 }}</span>
                        <span class="route-stop-name">{{ $p->user?->name }}@if($p->latitude === null)<small>Tanpa titik</small>@endif</span>
                        <span class="route-stop-move">
                            <button type="button" class="btn btn-secondary btn-icon" wire:click="move({{ $i }}, -1)" @disabled($i === 0) aria-label="Naikkan"><x-lucide-arrow-up class="ico" /></button>
                            <button type="button" class="btn btn-secondary btn-icon" wire:click="move({{ $i }}, 1)" @disabled($i === count($ordered) - 1) aria-label="Turunkan"><x-lucide-arrow-down class="ico" /></button>
                        </span>
                    </li>
                @endforeach
            </ol>
            <div class="route-actions">
                <x-ui.button variant="secondary" wire:click="back">Kembali</x-ui.button>
                <x-ui.button variant="primary" wire:click="start">Mulai rute</x-ui.button>
            </div>
        </x-sales.section>
    @else
        <x-sales.section :title="'Rute aktif · '.$counts['visited'].' dari '.$route->stops->count().' toko'">
            <p class="sales-note">Stok bawaan {{ $line->label() }}: <b>{{ $carriedItems }} barang</b> senilai <b>{{ $rp($carriedValue) }}</b></p>
            @if($navigateAll)<a class="btn btn-secondary btn-block" href="{{ $navigateAll }}" target="_blank" rel="noopener"><x-lucide-navigation class="ico" />Navigasi semua</a>@endif

            <ol class="route-stops">
                @foreach($route->stops as $stop)
                    @php($p = $stop->buyer?->buyerProfile)
                    <li @class(['route-stop', 'is-'.$stop->status]) wire:key="stop-{{ $stop->id }}">
                        <span class="route-stop-no">{{ $stop->position }}</span>
                        <span class="route-stop-name">
                            {{ $stop->buyer?->name }}
                            <small>{{ match ($stop->status) { 'visited' => 'Sudah dibuat nota', 'closed' => 'Toko tutup'.($stop->closed_note ? ' — '.$stop->closed_note : ''), default => $p?->address ?: 'Belum dikunjungi' } }}</small>
                        </span>
                        @if($stop->status === 'pending')
                            <span class="route-stop-actions">
                                @if($p?->latitude !== null)
                                    <a class="btn btn-secondary" href="{{ \App\Support\Sales\DeliveryRoutes::mapsUrl([\App\Livewire\Sales\Routes\Index::point($p)]) }}" target="_blank" rel="noopener"><x-lucide-navigation class="ico" />Navigasi</a>
                                @endif
                                <x-ui.button variant="primary" wire:click="open('toko', {{ $stop->buyer_id }})">Buka toko</x-ui.button>
                                <x-ui.button variant="secondary" wire:click="openClose({{ $stop->id }})">Toko tutup</x-ui.button>
                            </span>
                        @endif
                        @if($closingStopId === $stop->id)
                            <form wire:submit="saveClose" class="route-close-form">
                                <x-ui.camera-photo wire-model="closedPhoto" label="Foto toko yang tutup" />
                                @error('closedPhoto')<p class="text-danger" role="alert">{{ $message }}</p>@enderror
                                <x-ui.input label="Catatan (opsional)" wire:model="closedNote" placeholder="Mis. buka lagi jam 4 sore" />
                                <div class="route-actions">
                                    <x-ui.button variant="secondary" wire:click="cancelClose">Batal</x-ui.button>
                                    <x-ui.button type="submit" variant="primary">Simpan</x-ui.button>
                                </div>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ol>
        </x-sales.section>

        <x-sales.section title="Rekap rute">
            <div class="sales-list">
                <x-sales.row><b>Dikunjungi</b><x-slot:trail>{{ $counts['visited'] }}</x-slot:trail></x-sales.row>
                <x-sales.row><b>Toko tutup</b><x-slot:trail>{{ $counts['closed'] }}</x-slot:trail></x-sales.row>
                <x-sales.row><b>Belum dikunjungi</b><x-slot:trail>{{ $counts['pending'] }}</x-slot:trail></x-sales.row>
                @foreach($notesByKind as $kind => $sum)
                    <x-sales.row wire:key="rk-{{ $kind }}"><b>{{ $kind }}</b><x-slot:meta>{{ $sum['count'] }} nota</x-slot:meta><x-slot:trail>{{ $rp($sum['total']) }}</x-slot:trail></x-sales.row>
                @endforeach
                <x-sales.row><b>Barang terjual</b><x-slot:trail>{{ number_format($itemsSold, 0, ',', '.') }} pcs</x-slot:trail></x-sales.row>
            </div>
            <x-ui.confirm-button class="btn-block" action="finish" yes="Ya, selesaikan" message="Selesaikan rute ini? Toko yang belum dikunjungi tetap tercatat belum dikunjungi.">Selesaikan rute</x-ui.confirm-button>
        </x-sales.section>
    @endif
</x-sales.page>

    @include('livewire.sales.partials.sheet')
</div>
