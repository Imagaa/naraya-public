{{-- showStage: off inside a band that already names the stage (Pesanan tab). --}}
@props(['order', 'muted' => false, 'showStage' => true])
@php($stage = \App\Models\Order::SALES_STAGE_META[$order->salesStage()])

<x-sales.row :href="route('sales.deliveries.show', $order)" :class="$muted ? 'is-muted' : ''">
    <x-slot:lead><x-ui.store-avatar :buyer="$order->buyer" :size="36" /></x-slot:lead>
    <b>#{{ $order->id }}</b>
    <x-slot:meta>{{ $order->buyer->name }}</x-slot:meta>
    <x-slot:trail>
        @if($order->salesStage() === 'in_transit' && $order->delivery_lat && $order->delivery_lng)
            <x-lucide-map-pin width="14" height="14" aria-label="Rute tersedia" />
        @endif
        @if($showStage)
            <x-ui.tag :variant="$stage['variant']">{{ $stage['label'] }}</x-ui.tag>
        @endif
    </x-slot:trail>
</x-sales.row>
