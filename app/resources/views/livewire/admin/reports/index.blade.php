<div class="adm-page">
    <x-admin.page-header title="Laporan" />

    <div>
        <div class="admin-chart-controls">
            <h2 style="margin:0;font-size:16px">Grafik</h2>
            <div class="admin-chart-range">
                <x-admin.filter-pills :options="[7 => ['7 hari', null], 30 => ['30 hari', null], 90 => ['90 hari', null]]" :active="$this->chartPreset()" action="setChartRangePreset" />
                <form wire:submit="applyChartRange" class="admin-chart-daterange">
                    <input type="date" class="input" wire:model="chartStart">
                    <span class="text-muted">–</span>
                    <input type="date" class="input" wire:model="chartEnd">
                    <x-ui.button type="submit" variant="secondary">Terapkan</x-ui.button>
                </form>
            </div>
        </div>
        @error('chartEnd') <p class="text-danger" style="font-size:12.5px;margin:4px 0 0">{{ $message }}</p> @enderror

        <div class="admin-charts-row" style="margin-top:12px">
            <x-ui.card title="Tren Penjualan">
                <div class="admin-chart-wrap admin-chart-wrap--lg" wire:ignore>
                    <canvas id="sales-trend-chart" data-points="{{ json_encode($salesTrend) }}"></canvas>
                </div>
            </x-ui.card>

            <x-ui.card title="Produk Terlaris">
                <div class="admin-chart-wrap admin-chart-wrap--lg" wire:ignore>
                    <canvas id="top-products-chart" data-points="{{ json_encode($topProducts) }}"></canvas>
                </div>
            </x-ui.card>
        </div>

        <x-ui.card title="Alur Order (Semua Waktu)" style="margin-top:16px">
            <div class="admin-pipeline">
                @foreach($orderPipeline as $stage)
                    <div class="admin-pipeline-stage">
                        <div class="admin-pipeline-count">{{ $stage['total'] }}</div>
                        <div class="admin-pipeline-label">{{ $stage['label'] }}</div>
                        <div class="admin-pipeline-percent">{{ $stage['percent'] }}%</div>
                    </div>
                    @if(!$loop->last)
                        <div class="admin-pipeline-arrow">→</div>
                    @endif
                @endforeach
            </div>
        </x-ui.card>
    </div>

    <x-admin.section title="Semua laporan">
        @php
            $reports = [
                'penjualan' => ['Penjualan', 'Order selesai dan omzet per periode'],
                'toko' => ['Toko', 'Penjualan, kunjungan, titipan dan poin per toko'],
                'order' => ['Order', 'Semua order yang masuk beserta statusnya'],
                'stok' => ['Stok', 'Pergerakan stok masuk, keluar dan koreksi'],
                'cash' => ['Cash & Setoran', 'Uang tunai yang dipegang sales dan setorannya'],
                'absensi' => ['Absensi', 'Kehadiran sales per hari'],
                'target-sales' => ['Target Sales', 'Realisasi dibanding target bulanan per sales'],
                'buku-kas' => ['Buku Kas', 'Transaksi buku kas per tanggal'],
                'loyalty' => ['Loyalty', 'Penukaran hadiah dan saldo point toko'],
                'komplain' => ['Retur/Komplain', 'Retur dan komplain dari toko'],
            ];
        @endphp
        <x-admin.list cols="minmax(0,1fr) minmax(0,2fr) 24px">
            @foreach($reports as $suffix => [$label, $description])
                @if(Route::has('admin.reports.'.$suffix))
                    <x-admin.list-row :href="route('admin.reports.'.$suffix)">
                        <span class="adm-cell-main"><b>{{ $label }}</b></span>
                        <span class="adm-cell-sub">{{ $description }}</span>
                        <x-lucide-chevron-right width="16" height="16" aria-hidden="true" />
                    </x-admin.list-row>
                @endif
            @endforeach
        </x-admin.list>
    </x-admin.section>
</div>
