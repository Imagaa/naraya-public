@php($t = $recap['totals'])
<div class="adm-page">
    <x-admin.page-header :title="'Rekap · '.$user->name">
        {{ $note }}
        <x-slot:actions><a class="btn btn-secondary" href="{{ route('admin.attendances.recap', ['bulan' => $start->format('Y-m')]) }}">Semua sales</a></x-slot:actions>
    </x-admin.page-header>

    <div class="adm-month-nav">
        <a class="btn btn-secondary" href="{{ route('admin.attendances.recap-sales', ['user' => $user->id, 'bulan' => $start->copy()->subMonthNoOverflow()->format('Y-m')]) }}">◀</a>
        <strong>{{ $start->locale('id')->translatedFormat('F Y') }}</strong>
        @if($start->copy()->addMonthNoOverflow()->lte(\App\Support\BusinessDay::today()))
            <a class="btn btn-secondary" href="{{ route('admin.attendances.recap-sales', ['user' => $user->id, 'bulan' => $start->copy()->addMonthNoOverflow()->format('Y-m')]) }}">▶</a>
        @endif
    </div>

    <div class="adm-kpis">
        <x-admin.kpi label="Hadir" :value="$t['hadir'].' / '.$t['hari_kerja']" />
        <x-admin.kpi label="Terlambat" :value="$t['terlambat'].'x'" :note="$t['menit_terlambat'].' mnt total'" />
        <x-admin.kpi label="Tidak absen" :value="$t['tidak_absen']" />
        <x-admin.kpi label="Tepat waktu" :value="$t['persen_tepat'] === null ? '—' : $t['persen_tepat'].'%'" :progress="$t['persen_tepat']" :note="$t['pulang_cepat'].'x pulang cepat'" />
    </div>

    <div class="att-charts" wire:ignore>
        <x-admin.panel title="Jam masuk harian">
            <div class="att-chart"><canvas id="att-checkin-chart" data-points="{{ json_encode($points) }}" aria-label="Jam masuk harian dibanding jadwal"></canvas></div>
        </x-admin.panel>
        <x-admin.panel title="Terlambat per bulan">
            <div class="att-chart"><canvas id="att-trend-chart" data-points="{{ json_encode($trend) }}" aria-label="Jumlah terlambat enam bulan terakhir"></canvas></div>
        </x-admin.panel>
    </div>

    <x-admin.list cols="120px 80px minmax(0,1.3fr) minmax(0,1.3fr) minmax(0,1.2fr) 80px" :headers="['Tanggal', 'Jadwal', 'Masuk', 'Pulang', 'Lokasi', '']" :empty="empty($recap['days'])" empty-message="Belum ada hari di bulan ini.">
        @foreach(array_reverse($recap['days']) as $d)
            <x-admin.list-row wire:key="rd-{{ $d['date']->format('Ymd') }}">
                <span class="adm-num">{{ $d['date']->locale('id')->translatedFormat('D, j M') }}</span>
                <span class="adm-num">{{ $d['schedule'] ? $d['schedule']['masuk']->format('H:i') : 'Libur' }}</span>
                @foreach(['masuk', 'pulang'] as $tipe)
                    <span class="att-stamp">
                        @if($a = $d[$tipe])
                            <b class="adm-num">{{ $a->created_at->wib()->format('H:i') }}</b>
                            @if($a->status)<x-admin.pill :tone="$a->status->tone()">{{ $a->statusText() }}</x-admin.pill>@endif
                        @elseif($tipe === 'masuk' && $d['state'] === 'tidak_absen')
                            <x-admin.pill tone="bad">Tidak absen</x-admin.pill>
                        @else
                            <span class="adm-muted">—</span>
                        @endif
                    </span>
                @endforeach
                <span class="adm-cell-desk">{{ $d['masuk']?->location?->nama ?? '—' }}</span>
                <span>@if(collect([$d['masuk']?->flags, $d['pulang']?->flags])->flatten()->filter()->isNotEmpty())<x-admin.pill tone="bad">⚠</x-admin.pill>@endif</span>
            </x-admin.list-row>
        @endforeach
    </x-admin.list>
</div>
