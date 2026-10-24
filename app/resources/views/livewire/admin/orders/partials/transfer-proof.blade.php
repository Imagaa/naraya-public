{{-- BNI transfer proof review (TransferPayments). --}}
@if($order->payment_method === 'transfer')
    @php($state = $order->paymentState())
    <div class="adm-transfer">
        <p class="adm-kv"><span>Bukti transfer</span><strong>{{ ['awaiting_proof' => 'Belum diunggah', 'awaiting_review' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi'][$state] ?? '—' }}</strong></p>
        @if($order->payment_reject_reason && $state === 'awaiting_proof')
            <p class="adm-muted">Ditolak: {{ $order->payment_reject_reason }}</p>
        @endif
        @if($order->payment_proof_path)
            <a href="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($order->payment_proof_path) }}" target="_blank" rel="noopener">
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($order->payment_proof_path) }}" alt="Bukti transfer pesanan #{{ $order->id }}" class="adm-transfer-photo">
            </a>
        @endif
        @if($state === 'awaiting_review' && $order->status === \App\Enums\OrderStatus::New)
            <div class="adm-next-actions"><x-ui.button variant="primary" wire:click="verifyTransfer">Verifikasi</x-ui.button></div>
            <form wire:submit="rejectTransfer" class="adm-inline-form">
                <input class="input" wire:model="rejectReason" placeholder="Alasan penolakan (mis. nominal kurang)" aria-label="Alasan penolakan">
                <x-ui.button type="submit" variant="secondary">Tolak</x-ui.button>
            </form>
            @error('rejectReason')<p class="text-danger">{{ $message }}</p>@enderror
        @endif
        @error('payment')<p class="text-danger">{{ $message }}</p>@enderror
    </div>
@endif
