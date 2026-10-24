<div class="page page-pdp">
    <x-mp.header :tabbar="false" />

    <main class="container">
        <nav class="crumbs" aria-label="Lokasi">
            <a href="{{ url('/') }}">Beranda</a><x-lucide-chevron-right class="ico" />
            @if($product->category)
                <a href="{{ route('products.index', ['category' => $product->category->name]) }}">{{ $product->category->name }}</a><x-lucide-chevron-right class="ico" />
            @endif
            <span aria-current="page">{{ $product->name }}</span>
        </nav>

        <div class="pdp" style="--c: {{ $product->category?->color ?? '#F0B90B' }}">
            <div class="gallery" x-data="{ active: 0 }">
                <div class="ph ph-main">
                    @forelse($gallery as $i => $src)
                        <img src="{{ $src }}" alt="{{ $product->name }}" x-show="active === {{ $i }}" @if($i > 0) x-cloak @endif>
                    @empty
                        <x-mp.product-photo :product="$product" />
                    @endforelse
                    @if($product->hasDiscount())
                        <span class="ph-sale num">−{{ $product->discountPercent() }}%</span>
                    @endif
                </div>
                @if($gallery->count() > 1)
                    <div class="thumbs" aria-label="Foto lain">
                        @foreach($gallery as $i => $src)
                            <button type="button" class="ph" :class="active === {{ $i }} && 'on'" x-on:click="active = {{ $i }}" aria-label="Lihat foto {{ $i + 1 }}"><img src="{{ $src }}" alt=""></button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="info">
                <div class="info-top">
                    @if($product->category)
                        <a class="cat-link" href="{{ route('products.index', ['category' => $product->category->name]) }}">{{ $product->category->name }}</a>
                    @endif
                    <h1>{{ $product->name }}</h1>
                    <div class="sku num">{{ collect([
                        $product->sizeLabel() ? $product->sizeLabel().' per bungkus' : null,
                        $product->pieces_per_unit ? 'isi '.$product->pieces_per_unit.' pcs' : null,
                        'dijual per '.$unit,
                    ])->filter()->implode(' · ') }}</div>
                    @if($available)
                        <span class="stock num"><i></i>Stok {{ $product->unitsInStock() }} {{ $unit }}</span>
                    @else
                        <span class="stock stock-out">Stok habis</span>
                    @endif
                </div>

                <div class="buybox">
                    <div class="pbig">
                        @if($product->hasDiscount())
                            <div class="pbig-row"><span class="sale-pill num">−{{ $product->discountPercent() }}%</span><s class="num">@rupiah($product->price)</s></div>
                        @endif
                        <div class="pbig-row"><strong class="num">@rupiah($product->effectivePrice())</strong><span class="per">/{{ $unit }}</span></div>
                        @php
                            $priceNotes = collect([
                                $product->pricePerPiece() ? '≈ Rp '.number_format($product->pricePerPiece(), 0, ',', '.').' per pcs' : null,
                                $product->discountEndsLabel() ? 'harga promo sampai '.$product->discountEndsLabel() : null,
                            ])->filter()->implode(' · ');
                        @endphp
                        @if($priceNotes)
                            <small class="num">{{ $priceNotes }}</small>
                        @endif
                        @if($product->line === \App\Enums\BusinessLine::Naraya)
                            {{-- grosir prices move often --}}
                            <small class="price-note">Harga dapat berubah sewaktu-waktu.</small>
                            @if($product->price_changed_at)
                                <small class="price-note num">Harga per {{ $product->price_changed_at->wib()->locale('id')->translatedFormat('l, j F Y, H.i') }} WIB</small>
                            @endif
                        @endif
                    </div>

                    @if($available && ! config('naraya.marketplace_purchasing_open'))
                        <p class="muted">Pemesanan online ditutup sementara. Pesan produk ini lewat sales Naraya yang mengunjungi toko Anda.</p>
                    @elseif($available)
                        <div class="qty">
                            <div class="qty-row">
                                <span class="qty-label" id="qty-label">Jumlah ({{ $unit }})</span>
                                <div class="step step-lg" role="group" aria-labelledby="qty-label">
                                    <button type="button" wire:click="decrement" aria-label="Kurangi" @disabled($quantity <= $product->minQuantity())><x-lucide-minus class="ico" /></button>
                                    <span class="num" aria-live="polite">{{ $quantity }}</span>
                                    <button type="button" wire:click="increment" aria-label="Tambah" @disabled($quantity >= $product->unitsInStock())><x-lucide-plus class="ico" /></button>
                                </div>
                            </div>
                            <div class="qty-hint num">Minimal {{ $product->minQuantity() }} · maksimal {{ $product->unitsInStock() }}</div>
                            <div class="subtotal"><span>Subtotal</span><strong class="num">@rupiah($subtotal)</strong></div>
                        </div>
                        {{-- Inline from 1024px; phones and tablets use the sticky buy bar below. --}}
                        <div class="buy-btns hide-phone-flex">
                            <button type="button" class="btn btn-secondary" wire:click="addSelectedToCart"><x-lucide-shopping-cart class="ico" />{{ $added ? 'Ditambahkan' : '+ Keranjang' }}</button>
                            <button type="button" class="btn btn-primary" wire:click="buyNow">Beli Sekarang</button>
                        </div>
                    @else
                        <p class="muted">Produk ini sedang habis. Kami kabari begitu stoknya tersedia lagi.</p>
                        <div class="buy-btns">
                            @guest
                                <a class="btn btn-primary" href="{{ route('login') }}"><x-lucide-bell class="ico" />Kabari saya</a>
                            @else
                                <button type="button" class="btn {{ in_array($product->id, $alertIds) ? 'btn-secondary' : 'btn-primary' }}" wire:click="toggleStockAlert({{ $product->id }})" aria-pressed="{{ in_array($product->id, $alertIds) ? 'true' : 'false' }}">
                                    @if(in_array($product->id, $alertIds))<x-lucide-bell-ring class="ico" />Dikabari, batalkan @else<x-lucide-bell class="ico" />Kabari saya @endif
                                </button>
                            @endguest
                        </div>
                    @endif
                </div>

                <div class="facts">
                    <div class="fact"><x-lucide-truck class="ico" /><div><b>{{ $salesName ? 'Diantar '.$salesName : 'Diantar sales area' }}</b><span>Tiba 1–2 hari kerja setelah pesanan dikonfirmasi</span></div></div>
                    <div class="fact"><x-lucide-wallet class="ico" /><div><b>Bayar saat barang tiba</b><span>Tunai, transfer bank, atau QRIS</span></div></div>
                </div>

                <div class="share" x-data="copyLink(@js($product->url()))">
                    <span>Bagikan:</span>
                    <a class="btn btn-secondary btn-sm" href="https://wa.me/?text={{ rawurlencode($product->name.' — '.$product->url()) }}" target="_blank" rel="noopener"><x-lucide-message-circle class="ico" />WhatsApp</a>
                    <button type="button" class="btn btn-secondary btn-sm" x-on:click="copy()"><x-lucide-link class="ico" />Salin tautan</button>
                    <span class="copied" x-show="copied" x-cloak>Tautan disalin</span>
                </div>
            </div>
        </div>

        <div class="detail">
            @if($product->description)
                <section class="panel" aria-labelledby="h-desc">
                    <h2 id="h-desc">Deskripsi</h2>
                    <p>{{ $product->description }}</p>
                </section>
            @endif
            <section class="panel" aria-labelledby="h-spec">
                <h2 id="h-spec">Spesifikasi</h2>
                <dl class="spec num">
                    @foreach(array_filter([
                        'Ukuran' => $product->sizeLabel(),
                        'Isi' => $product->pieces_per_unit ? $product->pieces_per_unit.' pcs per '.$unit : null,
                        'Satuan jual' => $unit,
                        'Minimal order' => $product->minQuantity().' '.$unit,
                        'Kategori' => $product->category?->name,
                    ]) as $label => $value)
                        <dt>{{ $label }}</dt><dd>{{ $value }}</dd>
                    @endforeach
                </dl>
            </section>
        </div>

    </main>

    <x-mp.rail title="Sering dibeli bersama" tone="tint" :products="$related" :cart-qty="$cartQty" :alert-ids="$alertIds" key-prefix="rel" />
    <x-mp.rail title="Produk serupa" subtitle="Kategori sama, harga tidak jauh berbeda" tone="plain" :products="$similar" :cart-qty="$cartQty" :alert-ids="$alertIds" key-prefix="sim" />

    @if($available && config('naraya.marketplace_purchasing_open'))
        <div class="buybar">
            <div><small class="num">Subtotal {{ $quantity }} {{ $unit }}</small><strong class="num">@rupiah($subtotal)</strong></div>
            <x-mp.actions>
                <x-slot:secondary><button type="button" class="btn btn-secondary" wire:click="addSelectedToCart">{{ $added ? 'Ditambahkan' : '+ Keranjang' }}</button></x-slot:secondary>
                <x-slot:primary><button type="button" class="btn btn-primary" wire:click="buyNow">Beli Sekarang</button></x-slot:primary>
            </x-mp.actions>
        </div>
    @endif

</div>
