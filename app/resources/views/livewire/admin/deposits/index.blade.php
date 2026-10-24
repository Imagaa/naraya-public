<div class="adm-page">
    <x-admin.page-header title="Setoran" />
    <x-ui.input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama sales…" />
    <x-admin.queue-chip :label="$queueLabel" />
    <x-admin.filter-pills :options="['all' => ['Semua', $counts['all']], 'pending' => ['Perlu verifikasi', $counts['pending']], 'disputed' => ['Sengketa', $counts['disputed']], 'done' => ['Selesai', $counts['done']]]" :active="$filter" action="setFilter" />
    <x-admin.list cols="80px minmax(0,2fr) 150px 150px" :headers="['No.', 'Sales', 'Klaim', 'Status']" :empty="$deposits->isEmpty()" empty-message="Belum ada setoran.">
        @foreach($deposits as $deposit)
            <x-admin.list-row :href="route('admin.deposits.show', $deposit)">
                <span class="adm-num"><b>#{{ $deposit->id }}</b></span>
                <span class="adm-cell-main adm-with-avatar"><x-ui.person-avatar :user="$deposit->salesUser" :size="28" />{{ $deposit->salesUser->name }}</span>
                <span class="adm-num">Rp {{ number_format($deposit->claimed_amount, 0, ',', '.') }}</span>
                <span><x-admin.pill :tone="match ($deposit->status->value) { 'submitted' => 'new', 'disputed' => 'bad', default => 'ok' }">{{ $deposit->status->label() }}</x-admin.pill></span>
            </x-admin.list-row>
        @endforeach
    </x-admin.list>
    {{ $deposits->links() }}
</div>
