<x-sales.page title="Tugas Kirim">
    @forelse($groups as $stage => $orders)
        @php($done = $stage === 'awaiting_buyer_confirm')
        <x-sales.section :title="($done ? 'Terkirim · Menunggu Konfirmasi' : \App\Models\Order::SALES_STAGE_META[$stage]['label']).' ('.$orders->count().')'" wire:key="stage-{{ $stage }}">
            <div class="sales-list">
                @foreach($orders as $order)
                    <x-sales.order-row :order="$order" :muted="$done" :show-stage="false" wire:key="order-{{ $order->id }}" />
                @endforeach
            </div>
        </x-sales.section>
    @empty
        <div><x-ui.empty-state message="Belum ada tugas kirim." /></div>
    @endforelse
</x-sales.page>
