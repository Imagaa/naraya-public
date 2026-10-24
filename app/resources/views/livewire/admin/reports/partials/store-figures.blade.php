{{-- The figure tiles shared by the store page and Laporan Toko (StoreReport::summary). --}}
@php($n = fn ($v) => number_format($v, 0, ',', '.'))
<x-admin.kpi label="Mainan terjual" :value="$n($summary['terjual']).' pcs'" />
<x-admin.kpi label="Uang" :value="'Rp '.$n($summary['uang'])" :note="'Tunai Rp '.$n($summary['tunai']).' · Transfer Rp '.$n($summary['transfer'])" />
<x-admin.kpi label="Kunjungan" :value="$n($summary['kunjungan']).'×'" note="1 toko per hari dihitung 1" />
<x-admin.kpi label="Masih tertitip" :value="$n($summary['masih_tertitip']).' pcs'" note="saat ini" />
<x-admin.kpi label="Dibawa kembali" :value="$n($summary['dibawa_kembali']).' pcs'" note="retur di periode ini" />
