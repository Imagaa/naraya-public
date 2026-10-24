@php
    $profile = $order->buyer->buyerProfile;
    $active = in_array($order->status, [\App\Enums\OrderStatus::Processing, \App\Enums\OrderStatus::Shipped], true);
    $ownsStore = (int) $profile?->sales_user_id === auth()->id();
@endphp
<x-sales.page :title="'Pengiriman #'.$order->id" :back="route('sales.orders.index')">
    @if($active)
        <x-sales.stepper :stage="$order->salesStage()" />
    @else
        <p><x-ui.tag variant="neutral">{{ $order->status->label() }}</x-ui.tag></p>
    @endif

    <x-ui.card>
        <x-sales.row :href="$ownsStore ? route('sales.buyers.show', $order->buyer) : null">
            <x-slot:lead><x-ui.store-avatar :buyer="$order->buyer" :size="44" /></x-slot:lead>
            <b>{{ $order->buyer->name }}</b>
            <x-slot:meta>{{ $order->delivery_address }}</x-slot:meta>
        </x-sales.row>
        <x-sales.contact :phone="$profile?->phone" :lat="$order->delivery_lat" :lng="$order->delivery_lng" :address="$order->delivery_address" />
    </x-ui.card>

    <x-ui.card title="Barang">
        <ul class="sales-items">
            @foreach($order->items as $item)
                <li>{{ $item->product_name }} &times; {{ $item->quantity }}</li>
            @endforeach
        </ul>
        <div class="sales-total"><span>Total Tagihan</span><strong>@rupiah($order->total)</strong></div>
        @if($order->payment_method === 'cash')
            <p class="sales-note">Dibayar tunai saat barang diterima.</p>
        @endif
        @if($order->isReceiptReady())
            <x-ui.button tag="a" :href="route('sales.deliveries.receipt', $order)" variant="secondary" class="btn-block">Lihat Nota</x-ui.button>
        @endif
    </x-ui.card>

    @if($order->delivery_lat && $order->delivery_lng && $order->sales_received_at && ! $order->sales_delivered_at)
        <x-ui.card title="Rute ke Pembeli">
            <x-map.route-map :dest-lat="$order->delivery_lat" :dest-lng="$order->delivery_lng" />
        </x-ui.card>
    @endif

    @if($order->status->value === 'processing' && $order->gudang_handover_at && ! $order->sales_received_at)
        <x-ui.card title="Konfirmasi Terima dari Gudang">
            <form wire:submit="confirmReceivedFromGudang" class="sales-form">
                <x-ui.camera-photo wire-model="receivedPhoto" label="Foto Barang Diterima" />
                <x-ui.field-error name="receivedPhoto" />
                <x-ui.button type="submit" variant="primary">Konfirmasi Terima</x-ui.button>
            </form>
        </x-ui.card>
    @elseif($order->status->value === 'processing' && $order->sales_received_at)
        <div><x-ui.button variant="primary" class="btn-block" wire:click="markShipped">Berangkat Kirim</x-ui.button></div>
    @elseif($order->status->value === 'processing')
        <p class="sales-note">Menunggu Gudang serah terima barang.</p>
    @endif

    @if($order->status->value === 'shipped' && ! $order->sales_delivered_at)
        <x-ui.card title="Selesaikan Pengiriman">
            <form wire:submit="confirmDeliveredToBuyer" class="sales-form">
                @if($order->payment_method === 'cash')
                    <label class="sales-check">
                        <input type="checkbox" wire:model="cashRecountConfirmed">
                        <span>Saya sudah menghitung ulang uang tunai sebesar <strong>@rupiah($order->total)</strong> dan jumlahnya sesuai.</span>
                    </label>
                    <x-ui.field-error name="cashRecountConfirmed" />
                @endif

                <x-ui.camera-photo wire-model="deliveredPhoto" label="Foto Barang Diterima Pembeli" />
                <x-ui.field-error name="deliveredPhoto" />

                <x-ui.button type="submit" variant="primary">Selesaikan Pesanan</x-ui.button>
            </form>
        </x-ui.card>

        <div><x-ui.button tag="a" :href="route('sales.deliveries.reject', $order)" variant="secondary" class="btn-block sales-danger-link">Barang Ditolak Pembeli</x-ui.button></div>
    @elseif($order->status->value === 'shipped' && $order->sales_delivered_at)
        <p class="sales-note">Sudah dikirim pada {{ $order->sales_delivered_at->timezone(config('app.business_timezone'))->format('d/m/Y H:i') }}. Menunggu konfirmasi pembeli dan penutupan oleh Admin.</p>
    @endif
</x-sales.page>
