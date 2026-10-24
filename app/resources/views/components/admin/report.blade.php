@props(['title', 'headings', 'rows', 'startDate', 'endDate', 'period' => null])

{{-- One layout for every Laporan page: back link, header with period and exports, date range + quick periods, fixed-column list. --}}
<div {{ $attributes->class('adm-page') }}>
    <a href="{{ route('admin.reports.index') }}" class="adm-back"><x-lucide-arrow-left width="16" height="16" /> Laporan</a>

    <x-admin.page-header :title="$title">
        {{ \Illuminate\Support\Carbon::parse($startDate)->locale('id')->translatedFormat('j M Y') }} – {{ \Illuminate\Support\Carbon::parse($endDate)->locale('id')->translatedFormat('j M Y') }}
        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="exportExcel"><x-lucide-file-spreadsheet width="16" height="16" /> Excel</x-ui.button>
            <x-ui.button variant="secondary" wire:click="exportPdf"><x-lucide-file-text width="16" height="16" /> PDF</x-ui.button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="adm-report-range">
        <label class="field">Dari <input type="date" class="input" wire:model.live="startDate"></label>
        <label class="field">Sampai <input type="date" class="input" wire:model.live="endDate"></label>
        <x-admin.filter-pills :options="['this_month' => ['Bulan ini', null], 'last_month' => ['Bulan lalu', null], 'last_30' => ['30 hari', null]]" :active="$period" action="setPeriod" />
    </div>

    {{ $slot }}

    <x-admin.list :cols="'repeat('.count($headings).', minmax(0, 1fr))'" :headers="$headings" :empty="count($rows) === 0" empty-message="Belum ada data pada periode ini.">
        @foreach($rows as $row)
            <x-admin.list-row>
                @foreach(array_values((array) $row) as $i => $value)
                    <span @class(['adm-cell-main' => $i === 0, 'adm-num' => $i > 0])>{{ $value }}</span>
                @endforeach
            </x-admin.list-row>
        @endforeach
    </x-admin.list>
</div>
