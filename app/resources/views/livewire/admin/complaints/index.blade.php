<div class="adm-page">
    <x-admin.page-header title="Retur & Komplain" />
    <x-ui.input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama toko…" />
    <x-admin.queue-chip :label="$queueLabel" />
    <x-admin.filter-pills :options="['all' => ['Semua', $counts['all']], 'waiting' => ['Menunggu', $counts['waiting']], 'done' => ['Selesai', $counts['done']]]" :active="$filter" action="setFilter" />
    <x-admin.list cols="80px minmax(0,2fr) minmax(0,1.4fr) 140px" :headers="['Order', 'Toko', 'Tipe', 'Status']" :empty="$complaints->isEmpty()" empty-message="Belum ada komplain.">
        @foreach($complaints as $complaint)
            <x-admin.list-row :href="route('admin.complaints.show', $complaint)">
                <span class="adm-num"><b>#{{ $complaint->order_id }}</b></span>
                <span class="adm-cell-main">{{ $complaint->order->buyer->name }}</span>
                <span class="adm-cell-desk">{{ $complaint->tipe->label() }}</span>
                <span><x-admin.pill :tone="match ($complaint->status->value) { 'diajukan' => 'new', 'diresolusi' => 'ok', default => 'muted' }">{{ $complaint->status->label() }}</x-admin.pill></span>
            </x-admin.list-row>
        @endforeach
    </x-admin.list>
    {{ $complaints->links() }}
</div>
