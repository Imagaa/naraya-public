@php($rp = fn ($v) => 'Rp '.number_format($v, 0, ',', '.'))
@php($dur = fn ($m) => intdiv($m, 60).'j '.($m % 60).'m')
{{-- Perjalanan sales, live: the list re-renders every 15 s; the map follows by event (Admin\Tracking\Index::tick) --}}
<div class="adm-page tracking" wire:poll.15s="tick">
    <x-admin.page-header title="Perjalanan sales">
        <strong>{{ $working }} dari {{ $activeSales }} sales sedang bekerja</strong> · hari ini · diperbarui otomatis tiap 15 detik ({{ now()->wib()->format('H.i') }})
    </x-admin.page-header>

    @if($shifts->isEmpty())
        <x-ui.empty-state message="Belum ada sales yang absen masuk hari ini." />
    @else
        <div class="tracking-grid">
            <div class="tracking-list">
                @foreach($shifts as $s)
                    @php($r = $s['recap'])
                    <button type="button" @class(['tracking-card', 'is-on' => $salesId === $s['masuk']->sales_id]) wire:click="pick({{ $s['masuk']->sales_id }})" wire:key="tc-{{ $s['masuk']->id }}" style="--who: {{ $s['color'] }}">
                        <span class="tracking-card-head">
                            <span class="adm-with-avatar"><i class="tracking-dot"></i><b>{{ $s['masuk']->salesUser->name }}</b></span>
                            <x-admin.pill :tone="$r['open'] ? 'ok' : 'muted'">{{ $r['open'] ? 'Sedang bekerja' : 'Sudah pulang' }}</x-admin.pill>
                        </span>
                        <span class="tracking-stats">
                            <span><b>{{ $r['start']->wib()->format('H.i') }}</b><small>masuk</small></span>
                            <span><b>{{ $dur($r['minutes']) }}</b><small>{{ $r['open'] ? 'sudah bekerja' : 'sampai '.$r['end']->wib()->format('H.i') }}</small></span>
                            <span><b>{{ number_format($r['km'], 1, ',', '.') }} km</b><small>{{ count($r['stores']) }} toko</small></span>
                        </span>
                        <span class="tracking-goods">
                            <span><small>Dibawa dari gudang</small><x-ui.item-lines :items="$s['taken']" :limit="3" /></span>
                            <span><small>Keluar ke toko</small><x-ui.item-lines :items="$s['out']" :limit="3" /></span>
                        </span>
                        <span class="tracking-last">
                            @if($s['last'])
                                GPS terakhir {{ $s['last']->recorded_at->wib()->format('H.i') }} ({{ $s['last']->recorded_at->locale('id')->diffForHumans() }})
                                @if($r['open'] && $s['last']->recorded_at->lt(now()->subMinutes(10)))<b class="text-danger"> · tidak ada sinyal GPS &gt;10 menit</b>@endif
                            @else
                                Belum ada titik GPS
                            @endif
                            @if($r['total']) · penjualan {{ $rp($r['total']) }}@endif
                        </span>
                    </button>
                @endforeach
            </div>

            <div @class(['tracking-detail', 'adm-phone-sheet' => $salesId !== null])>
                @if($salesId !== null)
                    @php($picked = $shifts->first(fn ($s) => $s['masuk']->sales_id === $salesId))
                    <div class="adm-phone-sheet-head">
                        <b>Perjalanan {{ $picked['masuk']->salesUser->name ?? '' }}</b>
                        <x-ui.button variant="secondary" wire:click="pick({{ $salesId }})">Tutup</x-ui.button>
                    </div>
                @endif
                <div wire:ignore x-data="trackingMap(@js($map))" x-on:tracking-update.window="draw($event.detail.data)">
                    <div x-ref="mapEl" class="tracking-map"></div>
                </div>
                <p class="adm-muted tracking-hint">Garis = jalur GPS, lingkaran berongga = tempat absen masuk, angka = urutan toko, titik besar = posisi terakhir. Ketuk kartu sales untuk memperbesar ke jalurnya; ketuk lagi untuk melihat semua.</p>
                @foreach($shifts->filter(fn ($s) => $salesId === null || $s['masuk']->sales_id === $salesId) as $s)
                    @if($s['recap']['stores'])
                        <x-ui.card :title="'Toko dikunjungi · '.$s['masuk']->salesUser->name" wire:key="ts-{{ $s['masuk']->id }}">
                            <ol class="tracking-stores">
                                @foreach($s['recap']['stores'] as $st)
                                    <li><span class="adm-num">{{ $st['at']->wib()->format('H.i') }}</span><b>{{ $st['name'] }}</b>
                                        <span class="adm-muted">{{ $st['closed'] ? 'tutup' : ($st['notes'] ? $st['notes'].' nota · '.$rp($st['total']) : 'kunjungan') }}</span></li>
                                @endforeach
                            </ol>
                        </x-ui.card>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
</div>
