{{--
    The open sheet of a sales page (App\Livewire\Sales\Concerns\HostsSheets). Rendered only while open, so a
    closed sheet costs nothing; salesSheet (resources/js/sales.js) adds a history entry so Back closes it.
--}}
@if($sheet !== '')
    <div class="sales-sheet" role="dialog" aria-modal="true" x-data="salesSheet" wire:key="sales-sheet">
        @php($k = $sheet.'-'.$for.'-'.(int) $akun) {{-- a new store or note re-mounts its component --}}
        @switch($sheet)
            @case('masuk')
            @case('ambil')
            @case('retur')
                <livewire:sales.stock.index :mode="$sheet" :key="$k" />
                @break
            @case('pulang')
                <livewire:sales.check-out :key="$k" />
                @break
            @case('nota')
                <livewire:sales.notes.create :toko="$for" :key="$k" />
                @break
            @case('toko')
                <livewire:sales.buyers.show :buyer="$for" :akun="$akun" :key="$k" />
                @break
            @case('stok-awal')
                <livewire:sales.consignment.opening :store-id="$for" :key="$k" />
                @break
            @case('titip')
                <livewire:sales.consignment.visit :store-id="$for" :key="$k" />
                @break
            @case('toko-baru')
                <livewire:sales.buyers.form :key="$k" />
                @break
            @case('setor')
                <livewire:sales.deposits.index :key="$k" />
                @break
            @case('investasi')
                <livewire:sales.investments.index :store-id="$for" :key="$k" />
                @break
            @case('akun')
                <livewire:sales.account :key="$k" />
                @break
            @case('notifikasi')
                <livewire:sales.notifications :key="$k" />
                @break
            @case('absensi')
                <livewire:sales.attendances.index :key="$k" />
                @break
            @case('nota-saya')
                <livewire:sales.notes.index :key="$k" />
                @break
            @case('nota-detail')
                <livewire:sales.notes.show :id="$for" :key="$k" />
                @break
            @case('kunjungan')
                <livewire:sales.visits.index :key="$k" />
                @break
            @case('target')
                <livewire:sales.target.index :key="$k" />
                @break
            @case('jadwal')
                <livewire:sales.schedule :key="$k" />
                @break
            @case('riwayat-stok')
                <livewire:sales.stock.history :key="$k" />
                @break
        @endswitch
    </div>
@endif
