<div class="adm-page">
    <x-admin.page-header title="Rekap Absensi">
        <x-slot:actions><a class="btn btn-secondary" href="{{ route('admin.attendances.index') }}">Absensi harian</a></x-slot:actions>
    </x-admin.page-header>

    <div class="adm-month-nav">
        <a class="btn btn-secondary" href="{{ route('admin.attendances.recap', ['bulan' => $start->copy()->subMonthNoOverflow()->format('Y-m')]) }}">◀</a>
        <strong>{{ $start->locale('id')->translatedFormat('F Y') }}</strong>
        @if($start->copy()->addMonthNoOverflow()->lte(\App\Support\BusinessDay::today()))
            <a class="btn btn-secondary" href="{{ route('admin.attendances.recap', ['bulan' => $start->copy()->addMonthNoOverflow()->format('Y-m')]) }}">▶</a>
        @endif
    </div>

    <x-admin.list cols="minmax(0,1.8fr) 90px 80px 100px 110px 100px 100px 90px" :headers="['Sales', 'Hari kerja', 'Hadir', 'Terlambat', 'Total telat', 'Tidak absen', 'Pulang cepat', 'Tepat waktu']" :empty="$rows->isEmpty()" empty-message="Belum ada sales aktif.">
        @foreach($rows as $r)
            @php($t = $r['totals'])
            <x-admin.list-row :href="route('admin.attendances.recap-sales', ['user' => $r['sales']->id, 'bulan' => $start->format('Y-m')])" wire:key="rk-{{ $r['sales']->id }}">
                <span class="adm-cell-main adm-with-avatar"><x-ui.person-avatar :user="$r['sales']" :size="28" />{{ $r['sales']->name }}</span>
                <span class="adm-num">{{ $t['hari_kerja'] }}</span>
                <span class="adm-num">{{ $t['hadir'] }}</span>
                <span class="adm-num">{{ $t['terlambat'] }}x</span>
                <span class="adm-num">{{ $t['menit_terlambat'] }} mnt</span>
                <span class="adm-num">{{ $t['tidak_absen'] }}</span>
                <span class="adm-num">{{ $t['pulang_cepat'] }}</span>
                <span class="adm-num">{{ $t['persen_tepat'] === null ? '—' : $t['persen_tepat'].'%' }}</span>
            </x-admin.list-row>
        @endforeach
    </x-admin.list>
</div>
