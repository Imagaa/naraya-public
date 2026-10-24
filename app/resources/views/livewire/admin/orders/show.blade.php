<div class="adm-page">
    <a href="{{ route('admin.orders.index') }}" class="adm-back"><x-lucide-arrow-left width="16" height="16" /> Pesanan</a>

    <x-admin.page-header :title="'Pesanan #'.$order->id">
        <x-admin.status-pill :status="$order->status" />
        {{ $order->created_at->wib()->locale('id')->translatedFormat('j M Y H.i') }} · {{ $order->created_at->locale('id')->diffForHumans() }}
        <x-slot:actions>
            @if($order->isReceiptReady())
                <x-ui.button tag="a" :href="route('admin.orders.receipt', $order)" variant="secondary">Lihat nota</x-ui.button>
            @endif
            @if($canCancel)
                <button type="button" class="btn btn-secondary adm-btn-danger-outline" x-data @click="document.getElementById('cancel-dialog').showModal()">Batalkan</button>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    @if($order->needs_payment_review)
        <div class="adm-alert">
            <div>
                <strong>Perlu review pembayaran</strong>
                <p>Settlement pembayaran diterima setelah order ini sempat ditutup sebagai gagal/dibatalkan. Periksa histori pembayaran sebelum melanjutkan.</p>
            </div>
            <x-ui.button variant="secondary" wire:click="acknowledgePaymentReview">Tandai sudah direview</x-ui.button>
        </div>
    @endif

    @if($order->refund_status === 'pending')
        <div class="adm-alert">
            <div>
                <strong>Perlu refund manual</strong>
                <p>Pesanan dibatalkan setelah pembayaran {{ strtoupper($order->payment_method) }} Rp {{ number_format($order->total, 0, ',', '.') }} masuk. Kembalikan dana ke toko, lalu tandai di sini.</p>
            </div>
            <x-ui.button variant="secondary" wire:click="markRefunded">Tandai refund selesai</x-ui.button>
        </div>
    @endif

    @error('stock')<div class="adm-alert"><p>{{ $message }}</p></div>@enderror

    @include('livewire.admin.orders.partials.next-step')

    <div class="adm-detail-grid">
        <div class="adm-detail-col">
            <x-admin.panel title="Barang" class="adm-o-2">
                <x-ui.table :headers="['Produk', 'Harga', 'Qty', 'Terkirim', 'Subtotal']">
                    @foreach($order->items as $item)
                        <tr>
                            <td>{{ $item->product_name }}</td>
                            <td>Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>
                                @if(is_null($item->delivered_quantity))
                                    —
                                @elseif($item->delivered_quantity < $item->quantity)
                                    <span class="text-danger">{{ $item->delivered_quantity }}</span>
                                @else
                                    {{ $item->delivered_quantity }}
                                @endif
                            </td>
                            <td>Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </x-ui.table>
                <div class="adm-kv adm-total"><span>Total pembelian</span><strong>Rp {{ number_format($order->total, 0, ',', '.') }}</strong></div>
            </x-admin.panel>

            <x-admin.panel title="Alur pesanan" class="adm-o-3">
                <x-admin.timeline :steps="$timeline" />
            </x-admin.panel>
        </div>

        <div class="adm-detail-col">
            @include('livewire.admin.orders.partials.buyer-panel')

            <x-admin.panel title="Pembayaran" class="adm-o-4">
                <div class="adm-kv"><span>Metode</span><strong>{{ strtoupper($order->payment_method) }}</strong></div>
                @include('livewire.admin.orders.partials.transfer-proof')
                <div class="adm-kv"><span>Status</span><x-ui.tag :variant="match ($order->payment_status->value) { 'paid' => 'success', 'failed' => 'danger', default => 'outline' }">{{ $order->payment_status->label() }}</x-ui.tag></div>
                @if($order->credit_applied > 0)
                    <div class="adm-kv"><span>Kredit dipakai</span><strong>Rp {{ number_format($order->credit_applied, 0, ',', '.') }}</strong></div>
                @endif
                <div class="adm-kv"><span>Total</span><strong>Rp {{ number_format($order->total, 0, ',', '.') }}</strong></div>
                @if($order->estimated_delivery_at)
                    <div class="adm-kv"><span>Estimasi sampai</span><strong>{{ $order->estimated_delivery_at->wib()->locale('id')->translatedFormat('j M Y H.i') }}</strong></div>
                @endif
            </x-admin.panel>

            <x-admin.panel title="Sales" class="adm-o-5">
                @if($order->status === \App\Enums\OrderStatus::New)
                    <p class="adm-muted">Dipilih saat konfirmasi; otomatis sesuai area bila dikosongkan.</p>
                @elseif($order->status === \App\Enums\OrderStatus::Processing && $order->sales_id)
                    <form wire:submit="assignSales" class="adm-inline-form">
                        <select class="input" wire:model="salesUserId" aria-label="Sales">
                            @foreach($salesUsers as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                        <x-ui.button type="submit" variant="secondary">Ganti</x-ui.button>
                    </form>
                @else
                    <p>{{ $order->salesUser?->name ?? 'Tanpa sales (diantar admin)' }}</p>
                @endif
            </x-admin.panel>
        </div>
    </div>

    @include('livewire.admin.orders.partials.cancel-dialog')
</div>
