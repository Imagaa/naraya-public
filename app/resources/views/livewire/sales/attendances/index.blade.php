@php($tz = config('app.business_timezone'))
<x-sales.page title="Absensi & libur" back="1">
    <x-sales.section title="Libur mingguan">
        <p class="sales-note">Jatah 1 hari libur per minggu (Senin–Minggu), diajukan paling lambat H-1. Langsung berlaku.</p>
        <div class="sales-list">
            <x-sales.row>
                <b>Libur minggu ini</b>
                <x-slot:meta>{{ $weekOff ? $weekOff->date->locale('id')->translatedFormat('l, j F') : 'Masih bisa diambil' }}</x-slot:meta>
                <x-slot:trail><x-ui.tag :variant="$weekOff ? 'neutral' : 'success'">{{ $weekOff ? 'Sudah dipakai' : 'Belum dipakai' }}</x-ui.tag></x-slot:trail>
            </x-sales.row>
        </div>
        <form wire:submit="requestDayOff" class="sales-form">
            <x-ui.input label="Tanggal libur" type="date" wire:model="dayOffDate" :min="$dayOffMin" :max="$dayOffMax" />
            <x-ui.field-error name="dayOffDate" />
            <x-ui.input label="Keperluan (opsional)" wire:model="dayOffNote" placeholder="Mis. acara keluarga" />
            <x-ui.button type="submit" variant="primary">Ajukan libur</x-ui.button>
        </form>
        @if($dayOffs->isNotEmpty())
            <div class="sales-list">
                @foreach($dayOffs as $off)
                    <x-sales.row wire:key="off-{{ $off->id }}">
                        <b>{{ $off->date->locale('id')->translatedFormat('l, j F Y') }}</b>
                        <x-slot:meta>{{ $off->cancelled_at ? 'Dibatalkan'.($off->cancel_reason ? ' admin: '.$off->cancel_reason : '') : ($off->note ?? 'Tanpa keterangan') }}</x-slot:meta>
                        <x-slot:trail>
                            @if(! $off->cancelled_at && $off->date->toDateString() >= $dayOffMin)
                                <x-ui.confirm-button variant="secondary" action="cancelDayOff({{ $off->id }})" message="Batalkan libur ini?" yes="Ya, batalkan">Batalkan</x-ui.confirm-button>
                            @elseif($off->cancelled_at)
                                <x-ui.tag variant="neutral">Batal</x-ui.tag>
                            @endif
                        </x-slot:trail>
                    </x-sales.row>
                @endforeach
            </div>
        @endif
    </x-sales.section>

    <x-sales.section title="Riwayat">
        <div class="sales-list">
            @forelse($attendances as $attendance)
                <x-sales.row wire:key="att-{{ $attendance->id }}">
                    <b>{{ $attendance->tipe->label() }}</b>
                    @if($attendance->status)<x-ui.tag :variant="$attendance->status->salesVariant()">{{ $attendance->statusText() }}</x-ui.tag>@endif
                    <x-slot:meta>{{ $attendance->created_at->timezone($tz)->format('d/m/Y H:i') }}@if($attendance->catatan) · {{ $attendance->catatan }}@endif</x-slot:meta>
                    <x-slot:trail>
                        @if($attendance->latitude && $attendance->longitude)
                            <a href="https://maps.google.com/?q={{ $attendance->latitude }},{{ $attendance->longitude }}" target="_blank" rel="noopener" class="sales-link">Peta</a>
                        @endif
                    </x-slot:trail>
                </x-sales.row>
            @empty
                <x-ui.empty-state message="Belum ada riwayat absensi." />
            @endforelse
        </div>
    </x-sales.section>
</x-sales.page>
