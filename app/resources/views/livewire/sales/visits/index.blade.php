<x-sales.page title="Riwayat kunjungan" back="1" x-data="{ showForm: {{ $toko ? 'true' : 'false' }} }" x-on:visit-logged.window="showForm = false">
    <x-slot:actions>
        <x-ui.button variant="primary" x-on:click="showForm = ! showForm">+ Catat</x-ui.button>
    </x-slot:actions>

    <x-ui.card x-show="showForm" x-cloak>
        <livewire:sales.visits.log-form :buyer-id="$toko" />
    </x-ui.card>

    <div class="sales-list">
        @forelse($visits as $visit)
            <x-sales.row wire:key="visit-{{ $visit->id }}">
                <b>{{ $visit->buyer?->name ?? 'Toko dihapus' }}</b>
                <x-slot:meta>{{ $visit->visited_at->timezone(config('app.business_timezone'))->format('d/m/Y H:i') }}@if($visit->notes) · {{ $visit->notes }}@endif</x-slot:meta>
            </x-sales.row>
        @empty
            <x-ui.empty-state message="Belum ada kunjungan tercatat." />
        @endforelse
    </div>
</x-sales.page>
