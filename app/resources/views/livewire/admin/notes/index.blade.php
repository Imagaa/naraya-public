<div class="adm-page">
    <x-admin.page-header title="Nota penjualan">Penjualan sales ke toko di lini {{ $line->label() }} (jual putus).</x-admin.page-header>
    <x-ui.input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari toko atau sales…" />
    <x-admin.queue-chip :label="$queueLabel" />
    <x-admin.filter-pills :options="['all' => ['Semua', $counts['all']], 'tunai' => ['Tunai', $counts['tunai']], 'menunggu' => ['Transfer/QRIS menunggu', $counts['menunggu']], 'ditolak' => ['Bukti ditolak', $counts['ditolak']]]" :active="$filter" action="setFilter" />

    <x-admin.list cols="120px 130px minmax(0,1.6fr) minmax(0,1.3fr) 100px 130px 150px" :headers="['No.', 'Tanggal', 'Toko', 'Sales', 'Bayar', 'Total', 'Bukti']" :empty="$notes->isEmpty()" empty-message="Belum ada nota di filter ini.">
        @foreach($notes as $note)
            <x-admin.list-row :href="route('admin.notes.show', $note)" wire:key="an-{{ $note->id }}">
                <span class="adm-num"><b>{{ $note->number() }}</b></span>
                <span class="adm-num">{{ $note->created_at->wib()->locale('id')->translatedFormat('j M H.i') }}</span>
                <span class="adm-cell-main">{{ $note->buyer?->name }}</span>
                <span class="adm-with-avatar"><x-ui.person-avatar :user="$note->salesUser" :size="24" />{{ $note->salesUser?->name }}</span>
                <span>{{ $note->payment_method->label() }}</span>
                <span class="adm-num">Rp {{ number_format($note->total, 0, ',', '.') }}</span>
                <span>@if($note->proof_status)<x-admin.pill :tone="$note->proof_status->tone()">{{ $note->proof_status->label() }}</x-admin.pill>@else<span class="adm-muted">—</span>@endif</span>
            </x-admin.list-row>
        @endforeach
    </x-admin.list>
    {{ $notes->links() }}
</div>
