<x-admin.report :title="'Laporan Stok'" :headings="$this->headings()" :rows="$this->rows()" :start-date="$startDate" :end-date="$endDate" :period="$this->activePeriod()">
    <div class="adm-kpis is-compact">
        <x-admin.kpi label="Nilai stok gudang (saat ini)" :value="'Rp '.number_format($stockValue, 0, ',', '.')" :note="$products->count().' produk aktif · harga jual'" />
        <x-admin.kpi label="Stok habis (saat ini)" :value="$stockOut" />
        <x-admin.kpi label="Stok menipis (saat ini)" :value="$stockLow" />
    </div>

    <x-admin.section title="Stok saat ini">
        <x-admin.list cols="minmax(0,2fr) minmax(0,1.2fr) minmax(0,1fr) minmax(0,1fr)" :headers="['Produk', 'Stok', 'Dalam pcs', 'Nilai']" :empty="$products->isEmpty()" empty-message="Belum ada produk aktif.">
            @foreach($products as $p)
                <x-admin.list-row wire:key="cur-{{ $p->id }}">
                    <span class="adm-cell-main">{{ $p->name }}</span>
                    <span class="adm-num {{ (int) $p->stock === 0 ? 'is-out' : '' }}"><b>{{ $p->qtyLabel((int) $p->stock) }}</b></span>
                    <span class="adm-num">{{ number_format((int) $p->stock, 0, ',', '.') }} pcs</span>
                    <span class="adm-num">Rp {{ number_format($p->valueOf((int) $p->stock), 0, ',', '.') }}</span>
                </x-admin.list-row>
            @endforeach
        </x-admin.list>
    </x-admin.section>

    <h2 class="adm-subhead">Pergerakan stok di periode ini</h2>
</x-admin.report>
