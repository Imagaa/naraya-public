<x-sales.page :title="'Barang Ditolak Pembeli #'.$order->id" :back="route('sales.deliveries.show', $order)">
    <x-ui.card title="Konfirmasi Penolakan">
        <p class="sales-note">Yakin barang ditolak pembeli? Pesanan akan dibatalkan dan diteruskan ke Admin untuk ditindaklanjuti.</p>

        <div>
            <b>Barang yang Akan Diretur</b>
            <ul class="sales-items">
                @foreach($order->items as $item)
                    <li>{{ $item->product_name }} &times; {{ $item->quantity }}</li>
                @endforeach
            </ul>
        </div>

        <form wire:submit="rejectDelivery" class="sales-form">
            <x-ui.input label="Alasan Penolakan" wire:model="rejectionReason" />
            <x-ui.field-error name="rejectionReason" />

            <x-ui.camera-photo wire-model="rejectionPhoto" label="Foto Barang Ditolak" />
            <x-ui.field-error name="rejectionPhoto" />

            <x-ui.button type="submit" variant="danger">Catat Penolakan</x-ui.button>
            <x-ui.button tag="a" :href="route('sales.deliveries.show', $order)" variant="secondary">Batal</x-ui.button>
        </form>
    </x-ui.card>
</x-sales.page>
