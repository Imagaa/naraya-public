@use('App\Enums\OrderStatus')
@php
    $s = $order->status;
    $waitingSince = $order->gudang_handover_at ?? $order->sales_assigned_at;
@endphp
<section class="adm-next">
    <h2>Langkah berikutnya</h2>

    @if($s === OrderStatus::RejectedByUser && $complaint)
        <p><strong>Verifikasi kondisi barang</strong> — alasan toko: {{ $complaint->alasan }}</p>
        <div class="adm-next-actions">
            <x-ui.button variant="primary" wire:click="resolveRejection('good')">Kondisi baik (kembalikan stok)</x-ui.button>
            <x-ui.button variant="secondary" wire:click="resolveRejection('damaged')">Kondisi rusak (write-off)</x-ui.button>
        </div>
    @elseif($order->awaitingAdminClose())
        <p><strong>Tutup pesanan</strong> — sales dan toko sudah mengonfirmasi. Periksa foto di alur pesanan, lalu tutup.</p>
        <div class="adm-next-actions"><x-ui.button variant="primary" wire:click="closeOrder">Tutup pesanan</x-ui.button></div>
    @elseif($s === OrderStatus::New)
        <p><strong>Konfirmasi & proses</strong></p>
        <div class="adm-next-fields">
            <label class="field">Sales
                <select class="input" wire:model="salesUserId">
                    <option value="">Otomatis sesuai area</option>
                    @foreach($salesUsers as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                </select>
            </label>
        </div>
        <div class="adm-next-actions"><x-ui.button variant="primary" wire:click="confirm">Konfirmasi & Proses</x-ui.button></div>
        <p class="adm-muted">Stok dipotong saat dikonfirmasi.</p>
    @elseif($s === OrderStatus::Processing && ! $order->sales_id)
        <p><strong>Tetapkan sales atau kirim sendiri</strong></p>
        <form wire:submit="assignSales" class="adm-inline-form">
            <select class="input" wire:model="salesUserId" aria-label="Sales">
                <option value="">— Pilih sales —</option>
                @foreach($salesUsers as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
            </select>
            <x-ui.button type="submit" variant="primary">Tetapkan</x-ui.button>
        </form>
        <div class="adm-next-alt">
            <x-ui.button variant="secondary" wire:click="markShipped">Tandai dikirim (diantar admin)</x-ui.button>
        </div>
    @elseif($s === OrderStatus::Processing && $order->sales_received_at)
        <p><strong>Tandai dikirim</strong> — sales sudah menerima barangnya.</p>
        <div class="adm-next-actions"><x-ui.button variant="primary" wire:click="markShipped">Tandai dikirim</x-ui.button></div>
    @elseif($s === OrderStatus::Processing && ! $order->gudang_handover_at)
        <p><strong>Serahkan barang ke sales</strong> — siapkan barangnya, lalu serahkan ke {{ $order->salesUser?->name ?? 'sales' }}.</p>
        <div class="adm-next-actions"><x-ui.button variant="primary" wire:click="handOverToSales">Serahkan ke sales</x-ui.button></div>
    @elseif($s === OrderStatus::Processing)
        <p @class(['adm-wait', 'is-late' => $waitingSince && $waitingSince->lt(now()->subDay())])>
            Menunggu sales menerima barang
            @if($waitingSince) · {{ $waitingSince->locale('id')->diffForHumans(null, true) }}@endif
        </p>
    @elseif($s === OrderStatus::Shipped && ! $order->sales_id)
        <p><strong>Tandai selesai</strong> — unggah foto bukti pengiriman.</p>
        <div class="adm-next-fields"><x-ui.file-picker wire:model="deliveryProof" :file="$deliveryProof" label="Pilih foto bukti pengiriman" /></div>
        @error('deliveryProof')<p class="text-danger">{{ $message }}</p>@enderror
        <div class="adm-next-actions"><x-ui.button variant="primary" wire:click="markCompleted">Tandai selesai</x-ui.button></div>
    @elseif($s === OrderStatus::Shipped)
        <p class="adm-wait">{{ $order->sales_delivered_at ? 'Menunggu konfirmasi toko (otomatis selesai 3 hari setelah diantar)' : 'Sedang diantar oleh '.$order->salesUser?->name }}</p>
    @elseif($s === OrderStatus::Completed)
        <p class="adm-wait">Selesai {{ $order->completed_at?->wib()->locale('id')->translatedFormat('j M Y H.i') }}</p>
    @elseif($s === OrderStatus::Cancelled)
        <p class="adm-wait">Dibatalkan{{ $order->cancelled_at ? ' '.$order->cancelled_at->wib()->locale('id')->translatedFormat('j M Y H.i') : '' }}{{ $order->cancelledBy ? ' oleh '.$order->cancelledBy->name : '' }}{{ $order->cancel_reason ? ': '.$order->cancel_reason->label() : '' }}{{ $order->cancel_note ? ' — '.$order->cancel_note : '' }}</p>
    @else
        <p class="adm-wait">Ditolak toko saat diterima.</p>
    @endif
</section>
