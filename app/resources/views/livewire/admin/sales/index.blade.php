<div class="adm-page">
    @if(session('status'))<p class="text-success" role="status">{{ session('status') }}</p>@endif
    <x-admin.page-header title="Sales & Area">
        {{ $counts['active'] }} sales aktif
        <x-slot:actions>
            <x-ui.button variant="secondary" tag="a" href="{{ route('admin.sales.map') }}">Peta persebaran</x-ui.button>
            <x-ui.button variant="secondary" tag="a" href="{{ route('admin.areas.index') }}">Lihat area</x-ui.button>
            <x-ui.button variant="primary" tag="a" href="{{ route('admin.sales.create') }}">Tambah sales</x-ui.button>
        </x-slot:actions>
    </x-admin.page-header>

    <x-ui.input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama sales…" />
    <x-admin.queue-chip :label="$queueLabel" />
    <x-admin.filter-pills :options="['all' => ['Semua', $counts['all']], 'active' => ['Aktif', $counts['active']], 'inactive' => ['Nonaktif', $counts['inactive']]]" :active="$filter" action="setFilter" />

    <x-admin.list cols="minmax(0,2fr) minmax(0,1fr) 70px 70px 90px 130px 130px 100px minmax(0,1.2fr)" :headers="['Sales', 'Area', 'Toko', 'Libur', 'Toko/hari', 'Penjualan', 'Target', 'Status', '']" :empty="$salesUsers->isEmpty()" empty-message="Belum ada sales.">
        @foreach($salesUsers as $sales)
            @php($p = $sales->salesProfile)
            <x-admin.list-row>
                <span class="adm-cell-main adm-with-avatar">
                    <x-ui.person-avatar :user="$sales" :size="32" />
                    <span><a href="{{ route('admin.sales.edit', $sales) }}">{{ $sales->name }}</a>
                        <span class="adm-cell-sub">{{ $p?->phone }}@if($p?->pending_phone) · minta ganti ke <b>{{ $p->pending_phone }}</b>@endif</span>
                    </span>
                </span>
                @php($perf = $performance[$sales->id])
                <span class="adm-cell-desk">{{ $p?->area?->name ?? '—' }}</span>
                <span class="adm-num"><span class="adm-phone-label">Toko </span>{{ $sales->assigned_stores_count }}</span>
                <span class="adm-num">{{ $perf['libur'] }}</span>
                <span class="adm-num">{{ number_format($perf['toko_per_hari'], 1, ',', '.') }}</span>
                <span class="adm-num">Rp {{ number_format($perf['penjualan'], 0, ',', '.') }}</span>
                <span class="adm-num adm-cell-desk">Rp {{ number_format($p?->monthly_target ?? 0, 0, ',', '.') }}</span>
                <span><x-admin.pill :tone="$p?->is_active ? 'ok' : 'muted'">{{ $p?->is_active ? 'Aktif' : 'Nonaktif' }}</x-admin.pill></span>
                <span class="adm-cell-actions">
                    @if($p?->pending_phone)
                        <x-ui.button variant="primary" wire:click="approvePhone({{ $sales->id }})">Setujui nomor</x-ui.button>
                        <x-ui.button variant="secondary" wire:click="rejectPhone({{ $sales->id }})">Tolak</x-ui.button>
                    @endif
                </span>
            </x-admin.list-row>
        @endforeach
    </x-admin.list>

    {{ $salesUsers->links() }}
</div>
