<x-admin.report :title="'Laporan Loyalty'" :headings="$this->headings()" :rows="$this->rows()" :start-date="$startDate" :end-date="$endDate" :period="$this->activePeriod()">
    <x-admin.panel title="Saldo point tertinggi (saat ini)">
        <x-admin.list cols="minmax(0,2fr) 120px" :headers="['Toko', 'Point']" :empty="$topBalances->isEmpty()" empty-message="Belum ada saldo point.">
            @foreach($topBalances as $profile)
                <x-admin.list-row>
                    <span class="adm-cell-main">{{ $profile->user->name }}</span>
                    <span class="adm-num">{{ $profile->loyalty_points }}</span>
                </x-admin.list-row>
            @endforeach
        </x-admin.list>
    </x-admin.panel>
    <div class="adm-section-head"><h2>Riwayat penukaran hadiah</h2></div>
</x-admin.report>
