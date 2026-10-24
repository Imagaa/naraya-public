<div class="adm-page store-report">
    <x-admin.page-header title="Laporan Toko">
        Penjualan, kunjungan, titipan dan poin semua toko
        <x-slot:actions><x-ui.button variant="secondary" tag="a" href="{{ route('admin.buyers.index') }}"><x-lucide-store class="ico" />Daftar toko</x-ui.button></x-slot:actions>
    </x-admin.page-header>

    @include('livewire.admin.reports.partials.store-period')

    <div class="adm-kpis store-kpis is-recap">
        @include('livewire.admin.reports.partials.store-figures')
        <x-admin.kpi label="Toko dengan penjualan" :value="$counts['dengan_penjualan'].' dari '.$counts['total']" />
        <x-admin.kpi label="Akun toko" :value="$counts['berakun'].' dari '.$counts['total']" :note="'sudah punya akun · '.$counts['tanpa_akun'].' belum'" />
    </div>

    <x-admin.panel title="Per barang">
        @include('livewire.admin.reports.partials.store-items')
    </x-admin.panel>

    <x-admin.panel title="Peringkat toko">
        <x-slot:actions>
            <select class="input" wire:model.live="sort" aria-label="Urutkan">
                @foreach($sorts as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
            </select>
        </x-slot:actions>
        @php($n = fn ($v) => number_format($v, 0, ',', '.'))
        <x-admin.list cols="minmax(0,2fr) minmax(0,1.2fr) 90px 120px 90px 140px 110px 70px 100px"
            :headers="['Toko', 'Sales', 'Terjual', 'Uang', 'Kunjungan', 'Kunjungan terakhir', 'Masih tertitip', 'Poin', 'Akun']"
            :empty="$ranking->isEmpty()" empty-message="Belum ada toko.">
            @foreach($ranking as $store)
                <x-admin.list-row wire:key="rank-{{ $store->id }}">
                    <span class="adm-cell-main"><a href="{{ route('admin.buyers.show', $store) }}" class="adm-row-link">{{ $store->name }}</a></span>
                    <span>{{ $store->buyerProfile->salesUser?->name ?? '—' }}</span>
                    <span class="adm-num">{{ $n($store->terjual) }} pcs</span>
                    <span class="adm-num">Rp {{ $n($store->uang) }}</span>
                    <span class="adm-num">{{ $store->kunjungan }}×</span>
                    <span class="adm-num">{{ $store->kunjungan_terakhir?->wib()->locale('id')->translatedFormat('j M Y') ?? '—' }}</span>
                    <span class="adm-num">{{ $n($store->masih_tertitip) }} pcs</span>
                    <span class="adm-num">{{ $n($store->buyerProfile->loyalty_points) }}</span>
                    <span><x-admin.pill :tone="$store->hasAccount() ? 'ok' : 'muted'">{{ $store->hasAccount() ? 'Sudah' : 'Belum' }}</x-admin.pill></span>
                </x-admin.list-row>
            @endforeach
        </x-admin.list>
        {{ $ranking->links() }}
    </x-admin.panel>
</div>
