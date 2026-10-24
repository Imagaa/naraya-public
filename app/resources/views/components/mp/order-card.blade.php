@props(['order'])
@php
    $p = $order->buyerProgress();
    $items = $order->items;
    $names = $items->pluck('product_name');
    $itemsText = match (true) {
        $names->count() <= 2 => $names->implode(', '),
        default => $names->take(2)->implode(', ').', dan '.($names->count() - 2).' lainnya',
    };
@endphp
<article {{ $attributes->merge(['class' => 'order'.($p['step'] === 0 ? ' cancel' : '')]) }} wire:key="order-{{ $order->id }}">
    <div class="order-top">
        <div>
            <h3 class="num">Pesanan #{{ $order->id }}</h3>
            <p>{{ $order->created_at->timezone(config('app.business_timezone'))->locale('id')->translatedFormat('j M Y, H.i') }}</p>
        </div>
        <x-mp.status :tone="$p['tone']" :icon="$p['icon']">{{ $p['label'] }}</x-mp.status>
    </div>

    <div class="o-thumbs">
        @foreach($items->take(3) as $item)
            <div class="ph" style="--c: {{ $item->product?->category?->color ?? '#6B7480' }}">
                @if($item->product)<x-mp.product-photo :product="$item->product" />@else<x-lucide-package class="ph-icon" />@endif
            </div>
        @endforeach
        @if($items->count() > 3)<span class="more num">+{{ $items->count() - 3 }}</span>@endif
        <span class="thumbs-text">{{ $itemsText }}</span>
    </div>

    <x-mp.order-progress :order="$order" />

    {{-- One primary action per card: confirm when it's waiting on the buyer, else reorder when it's over. --}}
    <div class="order-foot">
        <p class="tot num">Total<b>@rupiah($order->total)</b></p>
        <x-mp.actions>
            <x-slot:secondary><a href="{{ route('orders.show', $order) }}" class="btn btn-secondary btn-sm">Detail</a></x-slot:secondary>
            <x-slot:primary>
                @if($order->canBeConfirmedByBuyer())
                    <button type="button" class="btn btn-primary btn-sm" wire:click="confirmReceived({{ $order->id }})">Konfirmasi Diterima</button>
                @elseif($order->canBeReordered())
                    <button type="button" class="btn btn-primary btn-sm" wire:click="reorder({{ $order->id }})"><x-lucide-rotate-ccw class="ico ico-sm" />Pesan Lagi</button>
                @endif
            </x-slot:primary>
        </x-mp.actions>
    </div>
</article>
