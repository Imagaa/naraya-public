@php
    $rp = fn ($v) => 'Rp '.number_format($v, 0, ',', '.');
    $ptone = ['menunggu_verifikasi' => 'new', 'dibayar' => 'bad', 'aktif' => 'ok', 'selesai' => 'muted', 'ditolak' => 'muted'];
    $wtone = ['menunggu' => 'new', 'ditransfer' => 'ok', 'ditolak' => 'muted'];
@endphp
<div class="adm-page inv-scope">
    <x-admin.page-header title="Investasi">Paket yang dibeli toko, penyaluran ke mitra, dompet dan penarikan dana.</x-admin.page-header>

    <x-admin.filter-pills :options="collect($tabs)->map(fn ($label) => [$label, null])->all()" :active="$tab" action="setTab" />

    @if($tab === 'ringkasan')
        <div class="adm-kpis is-compact">
            <x-admin.kpi label="Paket aktif" :value="number_format($stats['active_packages'], 0, ',', '.')" :note="'di '.$stats['active_stores'].' toko'" />
            <x-admin.kpi label="Total aset dibeli toko" :value="$rp($stats['bought_amount'])" :note="number_format($stats['bought_packages'], 0, ',', '.').' paket, termasuk yang belum disalurkan'" />
            <x-admin.kpi label="Pendapatan toko bulan ini" :value="$rp($stats['month_income'])" note="sudah dan akan masuk dompet" />
            <x-admin.kpi label="Saldo dompet semua toko" :value="$rp($stats['wallets'])" note="bisa ditarik kapan saja" />
            <x-admin.kpi label="Belum disalurkan ke mitra" :value="$stats['forward_packages'].' paket'" :note="$rp($stats['forward_amount'])" />
            <x-admin.kpi label="Penarikan menunggu" :value="$stats['withdrawals_waiting']" :note="$rp($stats['withdrawals_amount']).($stats['proofs_waiting'] ? ' · '.$stats['proofs_waiting'].' bukti beli perlu dicek' : '')" />
        </div>

        <x-admin.panel title="Belum disalurkan ke mitra">
            <p class="adm-muted inv-adm-hint">Sudah dibayar toko. Paket aktif (dan pendapatannya mulai berjalan) setelah ditandai disalurkan.</p>
            @include('livewire.admin.investments.partials.purchases', ['rows' => $forward, 'empty' => 'Semua pembelian yang dibayar sudah disalurkan.'])
        </x-admin.panel>

        @if($proofs->isNotEmpty())
            <x-admin.panel title="Bukti beli perlu dicek">
                @include('livewire.admin.investments.partials.purchases', ['rows' => $proofs, 'empty' => ''])
            </x-admin.panel>
        @endif

        <div class="inv-adm-two">
            <x-admin.panel title="Penarikan menunggu transfer">
                @include('livewire.admin.investments.partials.withdrawals', ['rows' => $withdrawals, 'empty' => 'Tidak ada penarikan yang menunggu.'])
            </x-admin.panel>
            <x-admin.panel title="Jadwal 30 hari">
                <dl class="note-admin-facts">
                    <dt>Pendapatan bulanan</dt><dd><b>{{ $rp($schedule['income']) }}</b> · {{ $schedule['income_packages'] }} paket</dd>
                    <dt>Modal kembali</dt><dd><b>{{ $rp($schedule['principal']) }}</b> · {{ $schedule['principal_packages'] }} paket</dd>
                    <dt>Dana dibutuhkan</dt><dd><b>{{ $rp($schedule['income'] + $schedule['principal']) }}</b></dd>
                </dl>
            </x-admin.panel>
        </div>
    @elseif($tab === 'pembelian')
        <x-admin.filter-pills :options="['' => ['Semua', $counts->sum()]] + collect(\App\Models\InvestmentPurchase::STATUS_LABELS)->map(fn ($l, $k) => [$k === 'dibayar' ? 'Belum disalurkan' : $l, (int) ($counts[$k] ?? 0)])->all()" :active="$status" action="setStatus" />
        @include('livewire.admin.investments.partials.purchases', ['rows' => $purchases, 'empty' => 'Belum ada pembelian di filter ini.'])
        {{ $purchases->links() }}
    @elseif($tab === 'penarikan')
        <x-admin.filter-pills :options="['' => ['Semua', null]] + collect(\App\Models\InvestmentWithdrawal::STATUS_LABELS)->map(fn ($l) => [$l, null])->all()" :active="$status" action="setStatus" />
        @include('livewire.admin.investments.partials.withdrawals', ['rows' => $withdrawalList, 'empty' => 'Belum ada penarikan di filter ini.'])
        {{ $withdrawalList->links() }}
    @else
        <x-admin.list cols="minmax(0,1.6fr) minmax(0,1.2fr) 100px 140px 130px 140px 130px" :headers="['Toko', 'Sales', 'Paket aktif', 'Modal berjalan', 'Saldo dompet', 'Total pendapatan', 'Beli terakhir']" :empty="$investors->isEmpty()" empty-message="Belum ada toko yang berinvestasi.">
            @foreach($investors as $r)
                <x-admin.list-row :href="route('admin.buyers.edit', $r['store'])" wire:key="ivr-{{ $r['store']->id }}">
                    <span class="adm-cell-main">{{ $r['store']->name }}</span>
                    <span>{{ $r['store']->buyerProfile?->salesUser?->name ?? '—' }}</span>
                    <span class="adm-num">{{ $r['active'] }}</span>
                    <span class="adm-num">{{ $rp($r['capital']) }}</span>
                    <span class="adm-num">{{ $rp($r['balance']) }}</span>
                    <span class="adm-num">{{ $rp($r['income']) }}</span>
                    <span class="adm-num">{{ $r['last']?->wib()->format('d/m/Y') }}</span>
                </x-admin.list-row>
            @endforeach
        </x-admin.list>
    @endif
</div>
