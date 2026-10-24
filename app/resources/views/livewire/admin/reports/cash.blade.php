<x-admin.report :title="'Laporan Cash dan Setoran'" :headings="$this->headings()" :rows="$this->rows()" :start-date="$startDate" :end-date="$endDate" :period="$this->activePeriod()">
    <div class="adm-kpis is-compact">
        <x-admin.kpi label="Cash belum disetor (saat ini)" :value="'Rp '.number_format($cashUnremitted, 0, ',', '.')" />
    </div>
</x-admin.report>
