<x-admin.report :title="'Laporan Buku Kas'" :headings="$this->headings()" :rows="$this->rows()" :start-date="$startDate" :end-date="$endDate" :period="$this->activePeriod()" />
