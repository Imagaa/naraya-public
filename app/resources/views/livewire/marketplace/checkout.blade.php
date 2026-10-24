<div @class(['page', 'has-paybar' => $items->isNotEmpty()])
     x-data="{ confirmCash() { this.$wire.paymentMethod === 'cash' ? this.$refs.cashDialog.showModal() : this.$wire.placeOrder() } }">
    <x-mp.header :title="$stepTitle" :subtitle="$unitCount.' barang'" :back="$step === 'review' ? url('/') : route('checkout')" :tabbar="false" />

    <main class="container pg">
        <x-mp.steps :current="['review' => 1, 'shipping' => 2, 'payment' => 3][$step] ?? 1" />

        @if(session('status'))
            <p class="note ok" role="status"><x-lucide-circle-check class="ico" /><span>{{ session('status') }}</span></p>
        @endif
        @error('cart')
            <p class="note sale" role="alert"><x-lucide-circle-alert class="ico" /><span>{{ $message }}</span></p>
        @enderror

        {{-- The three step panels always render and toggle with the hidden
             attribute, not @if: Livewire's single-root wrapping has tripped
             over sibling @if groups containing component tags here before. --}}

        {{-- 1 · Keranjang --}}
        <section class="two" @if($step !== 'review') hidden @endif>
            <div class="stack">
                <div class="deliver">
                    <span class="av"><x-lucide-truck class="ico" /></span>
                    <p><b>{{ $salesName ? "Diantar {$salesName}, sales area Anda" : 'Diantar sales area' }}</b>Pesan hari ini, tiba 1–2 hari kerja. Bayar saat barang tiba.</p>
                </div>

                <section class="panel" aria-labelledby="h-cart">
                    <div class="panel-head">
                        <h2 id="h-cart">Keranjang <small>· {{ $items->count() }} produk</small></h2>
                        @if($items->isNotEmpty())
                            <button type="button" class="btn btn-ghost btn-sm" wire:click="clearCart" wire:confirm="Kosongkan keranjang?"><x-lucide-trash-2 class="ico ico-sm" />Kosongkan</button>
                        @endif
                    </div>

                    <div class="lines">
                        @forelse($items as $item)
                            @php
                                $p = $item['product'];
                                $meta = collect([
                                    $p->sizeLabel(),
                                    $p->pricePerPiece() ? '≈ Rp '.number_format($p->pricePerPiece(), 0, ',', '.').' per pcs' : null,
                                    $p->minQuantity() > 1 ? 'min. '.$p->minQuantity().' '.($p->unit ?: 'pcs') : null,
                                ])->filter()->implode(' · ');
                            @endphp
                            <div class="line-item" wire:key="line-{{ $p->id }}">
                                <a class="ph" href="{{ $p->url() }}" style="--c: {{ $p->category?->color ?? '#F0B90B' }}"><x-mp.product-photo :product="$p" /></a>
                                <div class="li-info">
                                    <h3><a href="{{ $p->url() }}">{{ $p->name }}</a></h3>
                                    @if($meta)<p class="li-meta">{{ $meta }}</p>@endif
                                    <p class="li-price num">
                                        <b>@rupiah($item['unitPrice'])</b>
                                        @if($p->hasDiscount())<s>@rupiah($p->price)</s><span class="chip">−{{ $p->discountPercent() }}%</span>@endif
                                    </p>
                                </div>
                                <div class="li-ctl">
                                    <div class="step">
                                        <button type="button" wire:click="updateQty({{ $p->id }}, {{ $item['quantity'] - 1 }})" @disabled($item['quantity'] <= $p->minQuantity()) aria-label="Kurangi {{ $p->name }}"><x-lucide-minus class="ico" /></button>
                                        <span class="num">{{ $item['quantity'] }}</span>
                                        <button type="button" wire:click="updateQty({{ $p->id }}, {{ $item['quantity'] + 1 }})" @disabled($item['quantity'] >= $p->unitsInStock()) aria-label="Tambah {{ $p->name }}"><x-lucide-plus class="ico" /></button>
                                    </div>
                                    <p class="li-sub num">
                                        @rupiah($item['subtotal'])
                                        @if($p->hasDiscount())<small>Hemat @rupiah(($p->price - $item['unitPrice']) * $item['quantity'])</small>@endif
                                    </p>
                                    <button type="button" class="icon-btn" wire:click="removeItem({{ $p->id }})" aria-label="Hapus {{ $p->name }}"><x-lucide-trash-2 class="ico ico-sm" /></button>
                                </div>
                            </div>
                        @empty
                            <x-mp.empty icon="shopping-cart" title="Keranjang masih kosong" text="Tambahkan produk dari beranda atau katalog.">
                                <a href="{{ url('/') }}" class="btn btn-primary">Belanja Sekarang</a>
                            </x-mp.empty>
                        @endforelse
                    </div>
                </section>

                @if($recommendations->isNotEmpty())
                    <section class="panel" aria-labelledby="h-with">
                        <div class="panel-head"><h2 id="h-with">{{ $items->isEmpty() ? 'Terlaris' : 'Lengkapi belanja Anda' }}</h2></div>
                        <x-mp.hscroll class="mini-rail" label="saran produk">
                            @foreach($recommendations as $rec)
                                <article class="mini" wire:key="rec-{{ $rec->id }}">
                                    <a class="ph" href="{{ $rec->url() }}" style="--c: {{ $rec->category?->color ?? '#F0B90B' }}"><x-mp.product-photo :product="$rec" /></a>
                                    <h3><a href="{{ $rec->url() }}">{{ $rec->name }}</a></h3>
                                    <div class="mini-foot num">
                                        @rupiah($rec->effectivePrice())
                                        @if($rec->isAvailable())
                                            <button type="button" class="add" wire:click="addToCart({{ $rec->id }})" aria-label="Tambah {{ $rec->name }}"><x-lucide-plus class="ico" /></button>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </x-mp.hscroll>
                    </section>
                @endif
            </div>

            <aside class="stack">
                <section class="panel" aria-labelledby="h-sum">
                    <div class="panel-head"><h2 id="h-sum">Ringkasan</h2></div>
                    @include('livewire.marketplace.partials.checkout-summary')
                    <div class="aside-cta">
                        <button type="button" class="btn btn-primary btn-block" wire:click="goToShipping" @disabled($items->isEmpty())>Lanjut ke Pengiriman<x-lucide-arrow-right class="ico ico-sm" /></button>
                    </div>
                </section>
                <p class="note"><x-lucide-shield-check class="ico" /><span>Harga dan stok dicek ulang saat pesanan dibuat. Promo yang habis masa berlakunya kembali ke harga normal.</span></p>
            </aside>
        </section>

        {{-- 2 · Pengiriman --}}
        <section class="two" @if($step !== 'shipping') hidden @endif>
            <section class="panel" aria-labelledby="h-addr">
                <div class="panel-head"><h2 id="h-addr">Alamat pengiriman</h2></div>

                @if(! $addingNewAddress)
                    <div class="opts" role="radiogroup" aria-labelledby="h-addr">
                        @foreach($addresses as $addr)
                            <label class="opt" wire:key="addr-{{ $addr->id }}">
                                <span class="tile"><x-lucide-map-pin class="ico" /></span>
                                <span>
                                    <span class="opt-title">{{ $addr->label ?: 'Alamat' }}@if($addr->is_default)<x-mp.status tone="brand">Utama</x-mp.status>@endif</span>
                                    <span class="opt-desc">{{ $addr->toFullAddress() }}</span>
                                </span>
                                <input type="radio" name="selectedAddressId" value="{{ $addr->id }}" wire:click="selectAddress({{ $addr->id }})" @checked($selectedAddressId === $addr->id)>
                            </label>
                        @endforeach
                    </div>
                    @error('selectedAddressId')<p class="form-error">{{ $message }}</p>@enderror

                    <button type="button" class="addr-new" wire:click="showAddAddressForm"><x-lucide-plus class="ico" />Tambah alamat baru</button>

                    <div class="form-actions">
                        <x-ui.button type="button" variant="secondary" wire:click="backToReview">Kembali</x-ui.button>
                        <x-ui.button type="button" variant="primary" wire:click="goToPayment" class="hide-phone">Lanjut ke Pembayaran</x-ui.button>
                    </div>
                @else
                    <x-mp.address-form-fields>
                        <div class="form-actions">
                            @if($addresses->isNotEmpty())
                                <x-ui.button type="button" variant="secondary" wire:click="cancelAddAddressForm">Batal</x-ui.button>
                            @else
                                <x-ui.button type="button" variant="secondary" wire:click="backToReview">Kembali</x-ui.button>
                            @endif
                            <x-ui.button type="button" variant="primary" wire:click="goToPayment" class="hide-phone">Lanjut ke Pembayaran</x-ui.button>
                        </div>
                    </x-mp.address-form-fields>
                @endif
            </section>

            <aside class="stack">
                <section class="panel" aria-labelledby="h-sum-2">
                    <div class="panel-head"><h2 id="h-sum-2">Ringkasan</h2></div>
                    @include('livewire.marketplace.partials.checkout-summary')
                </section>
            </aside>
        </section>

        {{-- 3 · Pembayaran --}}
        <section class="two" @if($step !== 'payment') hidden @endif>
            <div class="stack">
                @php $selectedAddress = $addresses->firstWhere('id', $selectedAddressId); @endphp
                <section class="panel" aria-labelledby="h-ship">
                    <div class="panel-head">
                        <h2 id="h-ship">Dikirim ke</h2>
                        <button type="button" class="btn btn-ghost btn-sm" wire:click="backToShipping"><x-lucide-pencil class="ico ico-sm" />Ubah</button>
                    </div>
                    @if($selectedAddress)
                        <div class="addr">
                            <span class="tile"><x-lucide-map-pin class="ico" /></span>
                            <div>
                                <p class="opt-title">{{ $selectedAddress->label ?: 'Alamat' }}@if($selectedAddress->is_default)<x-mp.status tone="brand">Utama</x-mp.status>@endif</p>
                                <p class="opt-desc">{{ $selectedAddress->toFullAddress() }}</p>
                            </div>
                        </div>
                    @endif
                    <div class="divider"></div>
                    <div class="addr">
                        <span class="tile night"><x-lucide-truck class="ico" /></span>
                        <div>
                            <p class="opt-title">{{ $salesName ? "Diantar {$salesName}" : 'Diantar sales area' }}</p>
                            <p class="opt-desc">Tiba 1–2 hari kerja setelah pesanan dikonfirmasi</p>
                        </div>
                    </div>
                </section>

                <section class="panel" aria-labelledby="h-pay">
                    <div class="panel-head"><h2 id="h-pay">Metode pembayaran</h2></div>
                    <div class="opts" role="radiogroup" aria-labelledby="h-pay">
                        @foreach([
                            ['cash', 'banknote', 'ok', 'Tunai saat barang tiba', 'Bayar ke sales saat pesanan diantar'],
                            ['transfer', 'landmark', 'info', 'Transfer BNI', 'Unggah foto bukti transfer setelah pesanan dibuat'],
                        ] as [$value, $icon, $tone, $name, $desc])
                            <label class="opt" wire:key="pay-{{ $value }}">
                                <span class="tile {{ $tone }}"><x-dynamic-component :component="'lucide-'.$icon" class="ico" /></span>
                                <span><span class="opt-title">{{ $name }}</span><span class="opt-desc">{{ $desc }}</span></span>
                                <input type="radio" name="paymentMethod" wire:model.live="paymentMethod" value="{{ $value }}">
                            </label>
                        @endforeach
                    </div>
                    @error('paymentMethod')<p class="form-error">{{ $message }}</p>@enderror

                    @if($paymentMethod === 'transfer')
                        <p class="note brand"><x-lucide-landmark class="ico" /><span>Transfer <b class="num">@rupiah($grandTotal)</b> ke {{ config('naraya.transfer_to') }}, lalu unggah foto buktinya di halaman pesanan dalam 30 menit.</span></p>
                    @endif
                    @if($paymentMethod === 'cash')
                        <p class="note brand"><x-lucide-banknote class="ico" /><span>Siapkan uang tunai <b class="num">@rupiah($grandTotal)</b> saat barang tiba. Sales akan menghitung bersama Anda.</span></p>
                    @endif

                    <div class="form-actions">
                        <x-ui.button type="button" variant="secondary" wire:click="backToShipping">Kembali</x-ui.button>
                    </div>
                </section>
            </div>

            <aside class="stack">
                <section class="panel" aria-labelledby="h-order">
                    <div class="panel-head">
                        <h2 id="h-order">Pesanan Anda</h2>
                        <button type="button" class="btn btn-ghost btn-sm" wire:click="backToReview">Ubah</button>
                    </div>
                    <div class="oi-list num">
                        @foreach($items as $item)
                            <div class="oi" wire:key="oi-{{ $item['product']->id }}">
                                <div class="ph" style="--c: {{ $item['product']->category?->color ?? '#F0B90B' }}"><x-mp.product-photo :product="$item['product']" /></div>
                                <span><b>{{ $item['product']->name }}</b><span class="muted tiny">{{ $item['quantity'] }} {{ $item['product']->unit ?: 'pcs' }}</span></span>
                                <span>@rupiah($item['subtotal'])</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="divider"></div>
                    @include('livewire.marketplace.partials.checkout-summary')
                    <div class="aside-cta">
                        <button type="button" class="btn btn-primary btn-block" x-on:click="confirmCash()">Buat Pesanan</button>
                    </div>
                </section>
            </aside>

            @if($paymentMethod === 'cash')
                <dialog class="dlg" x-ref="cashDialog" aria-labelledby="dlg-cash-title" wire:ignore.self>
                    <div class="dlg-body">
                        <span class="tile ok"><x-lucide-banknote class="ico" /></span>
                        <h2 id="dlg-cash-title">Bayar tunai saat barang tiba</h2>
                        <p>Siapkan <b class="num">@rupiah($grandTotal)</b>. Pesanan lunas setelah sales menerima pembayaran ini.</p>
                        <div class="dlg-actions">
                            <button type="button" class="btn btn-secondary" x-on:click="$refs.cashDialog.close()">Batal</button>
                            <button type="button" class="btn btn-primary" x-on:click="$refs.cashDialog.close(); $wire.placeOrder()">Ya, buat pesanan</button>
                        </div>
                    </div>
                </dialog>
            @endif
        </section>
    </main>

    @if($items->isNotEmpty())
        <div class="paybar">
            <div>
                <small>{{ $step === 'payment' ? 'Total bayar' : "Total · {$unitCount} barang" }}</small>
                <b class="num">@rupiah($grandTotal)</b>
            </div>
            @if($step === 'review')
                <button type="button" class="btn btn-primary" wire:click="goToShipping">Lanjut<x-lucide-arrow-right class="ico ico-sm" /></button>
            @elseif($step === 'shipping')
                <button type="button" class="btn btn-primary" wire:click="goToPayment">Lanjut<x-lucide-arrow-right class="ico ico-sm" /></button>
            @else
                <button type="button" class="btn btn-primary" x-on:click="confirmCash()">Buat Pesanan</button>
            @endif
        </div>
    @endif
</div>
