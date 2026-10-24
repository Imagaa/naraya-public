@php
    $zone = config('app.business_timezone');
    $at = fn ($time) => $time?->copy()->timezone($zone)->locale('id')->translatedFormat('j M, H.i');
    $cancelled = $progress['step'] === 0;
    $rep = $order->buyer?->buyerProfile?->salesUser;
    $repWa = \App\View\Components\Mp\SalesContact::waNumber($rep?->salesProfile?->phone);
    $methods = ['cash' => 'Tunai saat barang tiba', 'transfer' => 'Transfer BNI', 'qris' => 'QRIS', 'gopay' => 'GoPay'];
    $payTone = ['paid' => ['ok', 'circle-check'], 'pending' => ['neutral', 'clock'], 'failed' => ['sale', 'circle-x']][$order->payment_status->value] ?? ['neutral', 'clock'];

    // Each step is done once its timestamp exists; the first missing one is current.
    $timeline = $cancelled
        ? [['Pesanan dibuat', $order->created_at, null], [$order->status->label(), $order->updated_at, 'Pesanan tidak diproses lebih lanjut']]
        : [
            ['Pesanan dibuat', $order->created_at, $methods[$order->payment_method] ?? strtoupper($order->payment_method)],
            ['Disiapkan', $order->gudang_handover_at, null],
            ['Dibawa sales', $order->sales_received_at, $order->salesUser?->name],
            ['Diantar ke toko', $order->sales_delivered_at, $order->sales_delivered_at && ! $order->buyer_confirmed_at && $order->status->value === 'shipped' ? 'menunggu konfirmasi Anda' : null],
            ['Selesai', $order->completed_at, 'Poin masuk ke akun Anda'],
        ];
    $current = collect($timeline)->search(fn ($row) => $row[1] === null);
    $proofPath = $order->sales_delivered_photo_path ?? $order->delivery_proof_path;
@endphp
<div @class(['page', 'has-paybar' => $order->canBeConfirmedByBuyer() || $order->canBeReordered() || $repWa])>
    <x-mp.header :title="'Pesanan #'.$order->id" :subtitle="$progress['label']" :back="route('orders.index')" :tabbar="false" />

    <main class="container pg">
        <nav class="crumbs" aria-label="Lokasi"><a href="{{ route('orders.index') }}">Pesanan</a><x-lucide-chevron-right class="ico" /><span class="num">#{{ $order->id }}</span></nav>

        @include('livewire.marketplace.orders.partials.payment-state')

        @if($flash)
            <p class="note sale" role="status"><x-lucide-circle-alert class="ico" /><span>{{ $flash }}</span></p>
        @endif

        <div class="two">
            <div class="stack">
                <section class="band" aria-labelledby="h-state">
                    <span class="eyebrow"><x-dynamic-component :component="'lucide-'.$progress['icon']" class="ico" />{{ $progress['label'] }} · Pesanan #{{ $order->id }}</span>
                    <h2 id="h-state">{{ $order->canBeConfirmedByBuyer() ? 'Pesanan sudah diantar ke toko Anda' : $progress['note'] }}</h2>
                    <p>Dipesan {{ $at($order->created_at) }}@if($order->estimated_delivery_at && in_array($order->status->value, ['processing', 'shipped'])) · estimasi tiba {{ $at($order->estimated_delivery_at) }}@endif</p>
                    {{-- Inline from 1024px; phones get the same actions in the sticky bar below. --}}
                    <div class="band-cta hide-phone-flex">
                        @if($order->canBeConfirmedByBuyer())
                            <button type="button" class="btn btn-primary" wire:click="confirmReceived"><x-lucide-circle-check class="ico ico-sm" />Konfirmasi Diterima</button>
                        @elseif($order->canBeReordered())
                            <button type="button" class="btn btn-primary" wire:click="reorder"><x-lucide-rotate-ccw class="ico ico-sm" />Pesan Lagi</button>
                        @endif
                        @if($repWa)
                            <a class="btn btn-onnight" href="https://wa.me/{{ $repWa }}" target="_blank" rel="noopener"><x-lucide-message-circle class="ico ico-sm" />Tanya Sales</a>
                        @endif
                    </div>
                </section>

                <section class="panel" aria-labelledby="h-tl">
                    <div class="panel-head"><h2 id="h-tl">Perjalanan pesanan</h2></div>
                    <ol class="tl">
                        @foreach($timeline as $i => [$label, $time, $detail])
                            @php $state = $time ? 'done' : ($i === $current ? 'on' : 'todo'); @endphp
                            <li class="{{ $state }}">
                                <span class="dot">
                                    @if($state === 'done')<x-lucide-check class="ico" />@elseif($state === 'on')<x-dynamic-component :component="'lucide-'.$progress['icon']" class="ico" />@else<x-lucide-circle class="ico" />@endif
                                </span>
                                <span>
                                    <b>{{ $label }}</b>
                                    <span class="num">{{ collect([$time ? $at($time) : null, $detail])->filter()->implode(' · ') }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ol>
                </section>

                <section class="panel" aria-labelledby="h-items">
                    <div class="panel-head">
                        <h2 id="h-items">Barang <small>· {{ $order->items->count() }} produk</small></h2>
                        @if($order->canBeReordered())
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="reorder"><x-lucide-rotate-ccw class="ico ico-sm" />Pesan Lagi</button>
                        @endif
                    </div>
                    <div class="oi-list num">
                        @foreach($order->items as $item)
                            <div class="oi" wire:key="item-{{ $item->id }}">
                                <div class="ph" style="--c: {{ $item->product?->category?->color ?? '#6B7480' }}">
                                    @if($item->product)<x-mp.product-photo :product="$item->product" />@else<x-lucide-package class="ph-icon" />@endif
                                </div>
                                <span><b>{{ $item->product_name }}</b><span class="muted tiny">{{ $item->quantity }} × @rupiah($item->price)</span></span>
                                <span>@rupiah($item->subtotal)</span>
                            </div>
                        @endforeach
                    </div>
                </section>

                @if(in_array($order->status->value, ['shipped', 'completed']) && $proofPath)
                    @php $proofUrl = \Illuminate\Support\Facades\Storage::disk('s3')->url($proofPath); @endphp
                    <section class="panel" aria-labelledby="h-proof">
                        <div class="panel-head"><h2 id="h-proof">Bukti pengiriman</h2></div>
                        <a href="{{ $proofUrl }}" target="_blank" rel="noopener"><img class="proof-img" src="{{ $proofUrl }}" alt="Bukti pengiriman"></a>
                    </section>
                @endif
            </div>

            <aside class="stack">
                <section class="panel" aria-labelledby="h-pay">
                    <div class="panel-head">
                        <h2 id="h-pay">Pembayaran</h2>
                        <x-mp.status :tone="$payTone[0]" :icon="$payTone[1]">{{ $order->payment_status->label() }}</x-mp.status>
                    </div>
                    <dl class="kv num">
                        <div><dt>Metode</dt><dd>{{ $methods[$order->payment_method] ?? strtoupper($order->payment_method) }}</dd></div>
                        <div><dt>Subtotal</dt><dd>@rupiah($order->subtotal)</dd></div>
                        @if($order->credit_applied > 0)
                            <div><dt>Saldo kredit</dt><dd>−@rupiah($order->credit_applied)</dd></div>
                        @endif
                    </dl>
                    <div class="sum-row total num"><span>Total</span><b>@rupiah($order->total)</b></div>
                    @if($order->isReceiptReady())
                        <a class="btn btn-secondary btn-block" href="{{ route('orders.receipt', $order) }}"><x-lucide-receipt-text class="ico ico-sm" />Lihat Nota</a>
                    @endif
                </section>

                <section class="panel" aria-labelledby="h-addr">
                    <div class="panel-head"><h2 id="h-addr">Alamat</h2></div>
                    <div class="addr">
                        <span class="tile"><x-lucide-map-pin class="ico" /></span>
                        <div>
                            <p>{{ $order->delivery_address }}</p>
                            @if($order->notes)<p class="muted tiny">Catatan: {{ $order->notes }}</p>@endif
                        </div>
                    </div>
                </section>

                @if($rep)
                    <section class="panel" aria-labelledby="h-rep">
                        <div class="panel-head"><h2 id="h-rep">Sales Anda</h2></div>
                        <x-mp.sales-contact />
                    </section>
                @endif

                <div class="form-actions">
                    <a href="{{ route('orders.index') }}" class="btn btn-secondary">Lihat Semua Pesanan</a>
                    @if($order->status->value === 'completed')
                        <a href="{{ route('complaints.create', $order) }}" class="link complaint-link">Ada masalah? Ajukan komplain</a>
                    @endif
                </div>
            </aside>
        </div>
    </main>

    @if($order->canBeConfirmedByBuyer() || $order->canBeReordered() || $repWa)
        <div class="paybar">
            <x-mp.actions>
                @if($repWa)
                    <x-slot:secondary><a class="btn btn-secondary" href="https://wa.me/{{ $repWa }}" target="_blank" rel="noopener"><x-lucide-message-circle class="ico ico-sm" />Tanya Sales</a></x-slot:secondary>
                @endif
                <x-slot:primary>
                    @if($order->canBeConfirmedByBuyer())
                        <button type="button" class="btn btn-primary" wire:click="confirmReceived"><x-lucide-circle-check class="ico ico-sm" />Konfirmasi Diterima</button>
                    @elseif($order->canBeReordered())
                        <button type="button" class="btn btn-primary" wire:click="reorder"><x-lucide-rotate-ccw class="ico ico-sm" />Pesan Lagi</button>
                    @endif
                </x-slot:primary>
            </x-mp.actions>
        </div>
    @endif
</div>
