<div class="adm-page">
    <x-admin.page-header title="Penukaran Hadiah" />
    <x-ui.input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama toko…" />
    <x-admin.queue-chip :label="$queueLabel" />
    <x-admin.filter-pills :options="['all' => ['Semua', $counts['all']], 'decision' => ['Perlu keputusan', $counts['decision']], 'handover' => ['Perlu diserahkan', $counts['handover']], 'done' => ['Selesai', $counts['done']]]" :active="$filter" action="setFilter" />
    <x-admin.list cols="80px minmax(0,1.6fr) minmax(0,1.6fr) 90px 150px" :headers="['No.', 'Toko', 'Hadiah', 'Point', 'Status']" :empty="$redemptions->isEmpty()" empty-message="Belum ada penukaran hadiah.">
        @foreach($redemptions as $redemption)
            <x-admin.list-row :href="route('admin.redemptions.show', $redemption)">
                <span class="adm-num"><b>#{{ $redemption->id }}</b></span>
                <span class="adm-cell-main">{{ $redemption->user->name }}<span class="adm-cell-sub">{{ $redemption->reward->name }}</span></span>
                <span class="adm-cell-desk">{{ $redemption->reward->name }}</span>
                <span class="adm-num">{{ $redemption->point_cost }}</span>
                <span><x-admin.pill :tone="match ($redemption->status->value) { 'diajukan' => 'new', 'disetujui' => 'info', 'diserahkan' => 'ok', default => 'bad' }">{{ $redemption->status->label() }}</x-admin.pill></span>
            </x-admin.list-row>
        @endforeach
    </x-admin.list>
    {{ $redemptions->links() }}
</div>
