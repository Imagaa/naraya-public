<div class="adm-page">
    <x-admin.page-header title="Pesanan">{{ $inFlight }} sedang jalan</x-admin.page-header>

    <x-ui.input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari no. pesanan, nama toko, atau no. HP…" />

    <x-admin.queue-chip :label="$queueLabel" />

    @php
        $closed = ($statusCounts['cancelled'] ?? 0) + ($statusCounts['rejected_by_user'] ?? 0);
        $tabs = ['all' => ['Semua', $statusCounts->sum()], 'new' => ['Baru', $statusCounts['new'] ?? 0], 'processing' => ['Diproses', $statusCounts['processing'] ?? 0], 'shipped' => ['Dikirim', $statusCounts['shipped'] ?? 0], 'completed' => ['Selesai', $statusCounts['completed'] ?? 0], 'closed' => ['Batal/Ditolak', $closed]];
    @endphp
    <x-admin.filter-pills :options="$tabs" :active="$statusFilter" action="setStatusFilter" />

        <x-admin.list cols="72px minmax(0,2.2fr) minmax(0,1fr) 124px 104px minmax(0,1.5fr)" :headers="['No.', 'Toko', 'Sales', 'Total', 'Status', 'Perlu tindakan']" :empty="$orders->isEmpty()" empty-message="Belum ada pesanan.">
            @foreach($orders as $order)
                @php($flag = $flags[$order->id] ?? null)
                <x-admin.list-row :href="route('admin.orders.show', $order)">
                    <span class="adm-num"><b>#{{ $order->id }}</b></span>
                    <span class="adm-cell-main">
                        {{ $order->buyer->name }}
                        <span class="adm-cell-sub">{{ $order->created_at->wib()->locale('id')->translatedFormat('j M') }} · {{ strtoupper($order->payment_method) }} · {{ $order->created_at->locale('id')->diffForHumans() }}</span>
                    </span>
                    <span class="adm-cell-desk">{{ $order->salesUser?->name ?? '—' }}</span>
                    <span class="adm-num">Rp {{ number_format($order->total, 0, ',', '.') }}</span>
                    <span><x-admin.status-pill :status="$order->status" /></span>
                    {{-- kept truly empty (no whitespace) when nothing is needed, so phones hide the cell and its label --}}
                    <span>@if($flag)<span class="adm-flag is-{{ $flag['severity'] }}"><span class="adm-dot"></span>{{ $flag['label'] }}</span>@endif</span>
                </x-admin.list-row>
            @endforeach
        </x-admin.list>

    {{ $orders->links() }}
</div>
