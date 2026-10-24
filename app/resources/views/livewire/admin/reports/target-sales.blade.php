<x-admin.report :title="'Laporan Target Sales'" :headings="$this->headings()" :rows="$this->rows()" :start-date="$startDate" :end-date="$endDate" :period="$this->activePeriod()" />
