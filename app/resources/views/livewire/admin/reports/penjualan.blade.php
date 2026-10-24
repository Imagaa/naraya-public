<x-admin.report :title="'Laporan Penjualan'" :headings="$this->headings()" :rows="$this->rows()" :start-date="$startDate" :end-date="$endDate" :period="$this->activePeriod()" />
