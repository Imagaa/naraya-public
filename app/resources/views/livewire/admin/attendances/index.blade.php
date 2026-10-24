@php
    $statePill = [
        'hadir' => null, 'terlambat' => null,
        'tidak_absen' => ['bad', 'Tidak absen'],
        'belum' => ['info', 'Belum absen'],
        'libur' => ['ship', 'Libur'],
    ];
@endphp
<div class="adm-page">
    <x-admin.page-header title="Absensi">
        <x-slot:actions>
            <a class="btn btn-secondary" href="{{ route('admin.attendances.day-offs') }}">Libur</a>
            <a class="btn btn-secondary" href="{{ route('admin.attendances.recap') }}">Rekap</a>
            <a class="btn btn-secondary" href="{{ route('admin.attendance-settings.edit') }}">Pengaturan</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="adm-month-nav att-day-nav">
        <button type="button" class="btn btn-secondary" wire:click="shiftDay(-1)" aria-label="Hari sebelumnya">◀</button>
        {{-- the day is the picker: the date input lies invisibly over it, so a tap opens the phone's calendar --}}
        <label class="att-day-pick">
            <strong>{{ $day->locale('id')->translatedFormat('l, j F Y') }}</strong>
            <x-lucide-calendar class="ico" />
            <input type="date" wire:model.live="date" max="{{ \App\Support\BusinessDay::today()->format('Y-m-d') }}" aria-label="Pilih tanggal">
        </label>
        <button type="button" class="btn btn-secondary" wire:click="shiftDay(1)" @disabled($isToday) aria-label="Hari berikutnya">▶</button>
    </div>

    @if($holiday)<div class="adm-alert"><p>Libur: {{ $holiday }}</p></div>@endif

    <div class="adm-kpis att-kpis">
        <x-admin.kpi label="Terjadwal" :value="$counts['terjadwal']" />
        <x-admin.kpi label="Hadir" :value="$counts['hadir']" />
        <x-admin.kpi label="Terlambat" :value="$counts['terlambat']" />
        <x-admin.kpi label="Tidak absen" :value="$counts['tidak_absen']" :note="$isToday && $counts['belum'] ? $counts['belum'].' belum absen' : null" />
        <x-admin.kpi label="Libur (diajukan)" :value="$counts['libur'].' sales'" :note="$offNames->isEmpty() ? 'Tidak ada' : $offNames->join(', ')" />
    </div>

    @if($upcomingOffs->isNotEmpty())
        <div class="adm-alert att-upcoming-offs">
            <p><b>Akan libur</b> (sudah diajukan, 7 hari ke depan) · <a href="{{ route('admin.attendances.day-offs') }}">Semua libur</a></p>
            <ul>
                @foreach($upcomingOffs as $date => $offs)
                    <li><b>{{ \Illuminate\Support\Carbon::parse($date)->locale('id')->translatedFormat('l, j M') }}</b>: {{ $offs->map(fn ($o) => $o->salesUser?->name.($o->note ? ' ('.$o->note.')' : ''))->join(', ') }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-ui.input type="search" wire:model.live.debounce.400ms="search" placeholder="Cari nama sales…" />
    <x-admin.filter-pills :options="['all' => ['Semua', $counts['all']], 'hadir' => ['Sudah absen', $counts['hadir']], 'terlambat' => ['Terlambat', $counts['terlambat']], 'tidak_absen' => ['Belum / tidak absen', $counts['tidak_absen'] + $counts['belum']], 'libur' => ['Libur', $counts['libur']]]" :active="$filter" action="setFilter" />

    <x-admin.list cols="minmax(0,1.6fr) minmax(0,1.2fr) minmax(0,1.2fr) minmax(0,1.2fr) 84px 168px" :headers="['Sales', 'Masuk', 'Pulang', 'Lokasi', 'Foto', '']" :empty="$rows->isEmpty()" empty-message="Tidak ada sales di filter ini.">
        @foreach($rows as $row)
            @php($s = $row['sales'])
            <x-admin.list-row wire:key="day-{{ $s->id }}">
                <span class="adm-cell-main adm-with-avatar"><x-ui.person-avatar :user="$s" :size="28" />
                    <span>{{ $s->name }}<span class="adm-cell-sub">{{ $s->salesProfile?->area?->name ?? '—' }}</span></span></span>

                @foreach(['masuk', 'pulang'] as $tipe)
                    @php($a = $row[$tipe])
                    <span class="att-stamp">
                        @if($a)
                            <span class="att-stamp-time">
                                <b class="adm-num">{{ $a->created_at->wib()->format('H:i') }}</b>
                                @if($a->flags)<span class="att-flag" title="{{ collect($a->flags)->map(fn ($f) => \App\Models\Attendance::FLAG_LABELS[$f] ?? $f)->join('; ') }}" aria-label="Perlu dicek: {{ collect($a->flags)->map(fn ($f) => \App\Models\Attendance::FLAG_LABELS[$f] ?? $f)->join('; ') }}"><x-lucide-triangle-alert width="14" height="14" /></span>@endif
                            </span>
                            @if($a->status)<x-admin.pill :tone="$a->status->tone()">{{ $a->statusText() }}</x-admin.pill>@endif
                            @if($a->koreksi_alasan)<span class="att-stamp-note" title="{{ $a->koreksi_alasan }}">Koreksi: {{ $a->koreksi_alasan }}</span>@endif
                        @elseif($tipe === 'masuk' && $statePill[$row['state']])
                            <x-admin.pill :tone="$statePill[$row['state']][0]">{{ $row['day_off'] ? 'Libur (diajukan)' : $statePill[$row['state']][1] }}</x-admin.pill>
                            @if($row['day_off']?->note)<span class="adm-cell-sub">{{ $row['day_off']->note }}</span>@endif
                        @else
                            <span class="adm-muted">—</span>
                        @endif
                    </span>
                @endforeach

                <span class="adm-cell-desk">
                    @if($row['masuk']?->location)
                        {{ $row['masuk']->location->nama }} · {{ $row['masuk']->jarak_m }} m
                        <a href="https://maps.google.com/?q={{ $row['masuk']->latitude }},{{ $row['masuk']->longitude }}" target="_blank" rel="noopener">Peta</a>
                    @else — @endif
                </span>

                <span class="att-thumbs">
                    @foreach(['masuk', 'pulang'] as $tipe)
                        @if($row[$tipe]?->foto_path)
                            @php($url = \Illuminate\Support\Facades\Storage::disk('s3')->url($row[$tipe]->foto_path))
                            <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="Foto {{ $tipe }}"><img src="{{ $url }}" alt="" loading="lazy" class="att-thumb"></a>
                        @endif
                    @endforeach
                </span>

                <span class="adm-cell-actions">
                    @if(in_array($row['state'], ['tidak_absen', 'belum'], true) && ($wa = \App\Support\Phone::normalize($s->salesProfile?->phone)))
                        <a class="btn btn-secondary" target="_blank" rel="noopener" href="https://wa.me/{{ $wa }}?text={{ rawurlencode('Halo '.$s->name.', Anda belum absen masuk '.($isToday ? 'hari ini ('.$day->locale('id')->translatedFormat('j M').'). Mohon segera absen atau kabari admin.' : 'pada '.$day->locale('id')->translatedFormat('j M').'. Mohon kabari admin alasannya.')) }}"><x-lucide-message-circle class="ico" />WhatsApp</a>
                    @endif
                    <x-admin.row-menu :id="'att-menu-'.$s->id">
                        @foreach(['masuk', 'pulang'] as $tipe)
                            @if($row[$tipe] && ! $row[$tipe]->koreksi_alasan)
                                <button type="button" class="adm-menu-item" role="menuitem" wire:click="startCorrecting({{ $row[$tipe]->id }})"><x-lucide-pencil />Koreksi {{ $tipe }}</button>
                            @endif
                        @endforeach
                        <a class="adm-menu-item" role="menuitem" href="{{ route('admin.attendances.recap-sales', ['user' => $s->id, 'bulan' => $day->format('Y-m')]) }}"><x-lucide-chart-column />Lihat rekap</a>
                    </x-admin.row-menu>
                </span>
            </x-admin.list-row>
            @if($correctingId && in_array($correctingId, [$row['masuk']?->id, $row['pulang']?->id], true))
                <div class="adm-list-extra adm-inline-form">
                    <input type="text" class="input" wire:model="koreksiAlasan" placeholder="Alasan koreksi" aria-label="Alasan koreksi">
                    <x-ui.save-button type="button" target="koreksiAlasan" wire:click="correct">Simpan koreksi</x-ui.save-button>
                    <x-ui.button variant="secondary" wire:click="cancelCorrecting">Batal</x-ui.button>
                    @error('koreksiAlasan')<p class="text-danger">{{ $message }}</p>@enderror
                </div>
            @endif
        @endforeach
    </x-admin.list>
</div>
