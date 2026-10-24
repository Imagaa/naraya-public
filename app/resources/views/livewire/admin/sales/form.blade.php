<div class="adm-page adm-detail">
    <a href="{{ route('admin.sales.index') }}" class="adm-back"><x-lucide-arrow-left width="16" height="16" /> Sales & Area</a>
    <x-admin.page-header :title="$sales?->exists ? $sales->name : 'Tambah sales'" />

    <div class="adm-detail-main">
    <x-ui.card class="sales-edit-card">
        <form wire:submit="save" style="display:flex;flex-direction:column;gap:12px">
            <x-ui.input label="Nama" wire:model="name" />
            @error('name') <span style="color:var(--color-danger);font-size:12px">{{ $message }}</span> @enderror
            <x-ui.input label="Email" type="email" wire:model="email" />
            @error('email') <span style="color:var(--color-danger);font-size:12px">{{ $message }}</span> @enderror
            <x-ui.input label="{{ $sales?->exists ? 'Kata Sandi Baru (opsional)' : 'Kata Sandi' }}" type="password" wire:model="password" />
            @error('password') <span style="color:var(--color-danger);font-size:12px">{{ $message }}</span> @enderror
            <x-ui.input label="Telepon" wire:model="phone" />

            <div class="field">
                <label>Area</label>
                <p class="text-muted" style="margin:0;font-size:13px">
                    Dibuat otomatis dari titik toko sales ini{{ $area?->lat !== null ? ': '.$area->name.' · radius '.$area->radius_km.' km' : ' — belum ada toko dengan titik' }}.
                </p>
            </div>

            <x-ui.input label="Target Bulanan (Rp)" type="number" wire:model="monthlyTarget" />
            @error('monthlyTarget') <span style="color:var(--color-danger);font-size:12px">{{ $message }}</span> @enderror

            <x-ui.save-button target="name,email,password,phone,monthlyTarget,isActive">Simpan</x-ui.save-button>
        </form>
    </x-ui.card>
    </div>

    @if($sales?->exists)
        <div class="adm-detail-side">
        <x-ui.card title="Hapus akun sales">
            <p class="text-muted" style="margin:0 0 8px;font-size:13px">Sales tidak bisa login lagi dan hilang dari daftar; toko-tokonya menunggu sales baru. Nota, absensi, stok dan kunjungannya tetap tercatat atas namanya. Hanya bisa bila cash sudah disetor dan barang sudah kembali ke gudang.</p>
            @error('delete') <p class="text-danger" role="alert" style="font-size:13px">{{ $message }}</p> @enderror
            <x-ui.confirm-button variant="danger" action="deleteAccount" yes="Ya, hapus akun" message="Hapus akun sales {{ $sales->name }}? Tidak bisa dibatalkan; riwayatnya tetap tercatat.">Hapus akun sales</x-ui.confirm-button>
        </x-ui.card>
        </div>
    @endif

    @if($performance)
    <div class="adm-span">
        @php($p = $performance)
        @php($rp = fn ($v) => 'Rp '.number_format($v, 0, ',', '.'))
        <x-admin.panel :title="'Performa · '.$perfLabel">
            <x-slot:actions>
                <input type="month" class="input" wire:model.live="perfMonth" max="{{ now()->format('Y-m') }}" aria-label="Bulan">
            </x-slot:actions>
            <div class="adm-kpis is-compact">
                <x-admin.kpi label="Kehadiran" :value="$p['hadir'].' / '.$p['hari_kerja'].' hari'" :note="$p['terlambat'].'x terlambat · '.$p['tidak_absen'].'x tidak absen'" />
                <x-admin.kpi label="Libur dipakai" :value="$p['libur'].' hari'" note="Jatah 1x per minggu" />
                <x-admin.kpi label="Putaran toko" :value="number_format($p['toko_per_hari'], 1, ',', '.').' toko/hari'" :note="$p['kunjungan_toko'].' kunjungan · '.$p['toko_unik'].' toko berbeda · '.$p['rute_selesai'].' rute selesai'" />
                <x-admin.kpi label="Penjualan (nota)" :value="$rp($p['penjualan'])" :note="$p['nota'].' nota, semua lini'" />
                <x-admin.kpi label="Setoran" :value="$rp($p['setoran_terverifikasi'])" :note="$p['setoran'].' setoran · '.$p['setoran_menunggu'].' menunggu · selisih '.$rp($p['selisih_setoran'])" />
                <x-admin.kpi label="Kewajiban tunai" :value="$rp($p['kewajiban'])" note="Tunai belum disetor + non-tunai ditolak + selisih cek stok" />
            </div>
        </x-admin.panel>
    </div>
    @endif
</div>
