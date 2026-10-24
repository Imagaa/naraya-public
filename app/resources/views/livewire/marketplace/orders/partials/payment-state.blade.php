{{-- Paying, as the store sees it (Order::paymentState). --}}
@php($state = $order->status === \App\Enums\OrderStatus::Cancelled ? null : $order->paymentState())
@if($state === 'awaiting_proof')
    <section class="panel pay-state" aria-labelledby="h-paystate">
        <div class="panel-head"><h2 id="h-paystate">Menunggu bukti bayar</h2></div>
        @if($order->payment_reject_reason)
            <p class="note sale"><x-lucide-circle-alert class="ico" /><span>Bukti sebelumnya ditolak: {{ $order->payment_reject_reason }}</span></p>
        @endif
        <p>Transfer <b class="num">@rupiah($order->total)</b> ke <b>{{ config('naraya.transfer_to') }}</b>, lalu unggah foto bukti transfernya di sini.</p>
        @if($order->payment_expires_at)
            <p class="muted num" x-data="{ left: Math.max(0, {{ $order->payment_expires_at->getTimestamp() }} - Math.floor(Date.now() / 1000)), get mmss() { return String(Math.floor(this.left / 60)).padStart(2, '0') + ':' + String(this.left % 60).padStart(2, '0') } }"
               x-init="setInterval(() => left = Math.max(0, left - 1), 1000)">
                Sisa waktu <b x-text="mmss"></b> — tanpa bukti, pesanan batal otomatis.
            </p>
        @endif
        <form wire:submit="uploadProof" class="stack">
            <x-ui.file-picker wire:model="proof" :file="$proof" label="Pilih foto bukti transfer" hint="Foto struk atau tangkapan layar m-banking. Maks. 5 MB." accept="image/*" />
            @error('proof')<p class="form-error">{{ $message }}</p>@enderror
            <x-ui.button type="submit" variant="primary" wire:loading.attr="disabled">Kirim bukti transfer</x-ui.button>
        </form>
    </section>
@elseif($state === 'awaiting_review')
    <p class="note brand" role="status"><x-lucide-clock class="ico" /><span><b>Menunggu verifikasi</b> — bukti transfer sudah kami terima dan sedang diperiksa admin.</span></p>
@elseif($state === 'verified' && ! in_array($order->status, [\App\Enums\OrderStatus::Completed, \App\Enums\OrderStatus::RejectedByUser], true))
    <p class="note ok" role="status"><x-lucide-circle-check class="ico" /><span><b>Pembayaran terverifikasi.</b> Terima kasih! Pesanan Anda kami kirim sebagai pesanan prioritas. Tunggu barang sampai di toko Anda.</span></p>
@elseif($state === 'cash' && in_array($order->status, [\App\Enums\OrderStatus::New, \App\Enums\OrderStatus::Processing, \App\Enums\OrderStatus::Shipped], true))
    <p class="note ok" role="status"><x-lucide-circle-check class="ico" /><span>Terima kasih! Pesanan Anda kami kirim sebagai pesanan prioritas. Tunggu barang sampai, dan <b class="num">Siapkan uang Rp {{ number_format($order->total, 0, ',', '.') }}</b> untuk dibayar ke sales.</span></p>
@endif
