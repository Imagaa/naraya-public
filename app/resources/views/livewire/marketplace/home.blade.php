@php
    // The visible sections in order; tones alternate over what actually renders, so a skipped
    // rail (fewer than 3 products) never leaves two bands of the same colour side by side.
    $rails = collect([
        ['key' => 'again', 'title' => 'Beli Lagi', 'subtitle' => auth()->check() ? 'Dari pesanan terakhir '.auth()->user()->name : null, 'products' => $buyAgain, 'more' => auth()->check() ? route('orders.index') : null],
        ['key' => 'promo', 'title' => 'Promo Pekan Ini', 'subtitle' => $promoEndsAt ? 'Berlaku sampai '.$promoEndsAt->timezone(config('app.business_timezone'))->format('d/m/Y') : 'Selama persediaan ada', 'products' => $promos, 'more' => route('products.index', ['sort' => 'promo'])],
        ['key' => 'categories'],
        ['key' => 'rec', 'title' => 'Rekomendasi untuk Toko Anda', 'subtitle' => 'Sering dibeli bersama barang langganan Anda', 'products' => $recommended, 'more' => null],
        ['key' => 'best', 'title' => $best['title'], 'subtitle' => null, 'products' => $best['products'], 'more' => route('products.index')],
        ...$topCategories->map(fn ($c) => ['key' => 'cat-'.$c->id, 'title' => $c->name, 'subtitle' => null, 'products' => $c->railProducts, 'more' => route('products.index', ['category' => $c->name])])->all(),
        ['key' => 'new', 'title' => 'Produk Baru', 'subtitle' => null, 'products' => $newest, 'more' => route('products.index', ['sort' => 'terbaru'])],
    ])->filter(fn ($r) => $r['key'] === 'categories' ? $categories->isNotEmpty() : $r['products']->count() >= 3)->values();
    $toneOffset = $activeOrders->isNotEmpty() ? 1 : 0;
    $tone = fn (int $i) => ($i + $toneOffset) % 2 === 0 ? 'plain' : 'tint';
@endphp
<div @class(['page', 'has-cart-bar' => $cartCount > 0])>
    <x-mp.header active="home" />

    <main>
        <div class="container">
            <section class="hero" x-data="heroCarousel({{ 1 + $heroPromos->count() }})"
                     x-on:mouseenter="paused = true" x-on:mouseleave="paused = false"
                     x-on:focusin="paused = true" x-on:focusout="paused = false"
                     x-on:touchstart="touchStart($event)" x-on:touchend="touchEnd($event)"
                     aria-roledescription="carousel" aria-label="Promo utama">
                <div class="hero-slide" x-show="slide === 0">
                    <div class="hero-copy">
                        <span class="eyebrow">Naraya untuk warung &amp; toko</span>
                        <h1>Grosir, mainan anak, dan <em>investasi</em> untuk warung Anda.</h1>
                        <p class="hero-sub">Barang grosir dan mainan diantar sales Naraya, plus paket investasi yang menghasilkan tiap bulan.</p>
                        <div class="hero-cta">
                            <a href="{{ route('products.index') }}" class="btn btn-primary">Lihat Produk</a>
                        </div>
                    </div>
                </div>

                @foreach($heroPromos as $i => $promo)
                    <a class="hero-slide" href="{{ $promo->url() }}" x-show="slide === {{ $i + 1 }}" x-cloak wire:key="hero-{{ $promo->id }}">
                        <div class="hero-copy">
                            <span class="eyebrow num">Promo −{{ $promo->discountPercent() }}%@if($promo->discountDaysLeft()) · sisa {{ $promo->discountDaysLeft() }} hari @endif</span>
                            <h1 class="clamp-2">{{ $promo->name }}</h1>
                            <p class="hero-price num"><s>@rupiah($promo->price)</s> <strong>@rupiah($promo->effectivePrice())</strong> /{{ $promo->unit ?: 'pcs' }}</p>
                            <div class="hero-cta"><span class="btn btn-primary">Lihat Produk</span></div>
                        </div>
                        @if($promo->photo_path)
                            <img class="hero-photo" src="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($promo->photo_path) }}" alt="{{ $promo->name }}">
                        @endif
                    </a>
                @endforeach

                @if($heroPromos->isNotEmpty())
                    <div class="dots">
                        @foreach(range(0, $heroPromos->count()) as $i)
                            <button type="button" x-on:click="slide = {{ $i }}" :class="slide === {{ $i }} && 'on'" aria-label="Slide {{ $i + 1 }}"></button>
                        @endforeach
                    </div>
                @endif
            </section>

            @if(config('naraya.marketplace_purchasing_open'))
            <x-mp.hscroll class="trust-strip" aria-label="Keunggulan" label="keunggulan">
                <span><x-lucide-truck class="ico" /><b>{{ $salesName ? 'Diantar '.$salesName : 'Diantar sales area' }}</b></span>
                <span><x-lucide-wallet class="ico" /><b>Bayar saat barang tiba</b></span>
                <span><x-lucide-shield-check class="ico" /><b>Minimal order jelas</b></span>
                <span><x-lucide-gift class="ico" /><b>Poin tiap belanja</b></span>
            </x-mp.hscroll>
            @endif
        </div>

        <x-mp.investment-card :portfolio="$investment" />

        @if($activeOrders->isNotEmpty())
            @php($first = $activeOrders->first())
            <x-mp.section tone="plain">
                <a class="active-order" href="{{ route('orders.show', $first) }}">
                    <span class="tile"><x-dynamic-component :component="'lucide-'.$first->buyerProgress()['icon']" class="ico" /></span>
                    <span class="active-order-text">
                        <b class="num">Pesanan #{{ $first->id }} {{ \Illuminate\Support\Str::lcfirst($first->buyerProgress()['note']) }}</b>
                        @if($activeOrders->count() > 1)<small class="num">+{{ $activeOrders->count() - 1 }} pesanan lain berjalan</small>@endif
                    </span>
                    <x-lucide-chevron-right class="ico chev" />
                </a>
            </x-mp.section>
        @endif

        @foreach($rails as $i => $rail)
            @if($rail['key'] === 'categories')
                <x-mp.section :tone="$tone($i)" title="Kategori" :more-url="route('products.index')" more-label="Semua produk">
                    <x-mp.hscroll class="cat-chips-home" label="kategori">
                        @foreach($categories as $category)
                            <a class="cat-chip" href="{{ route('products.index', ['category' => $category->name]) }}" style="--c: {{ $category->color ?? '#F0B90B' }}" wire:key="cat-{{ $category->id }}">
                                <x-dynamic-component :component="'lucide-'.($category->icon ?: 'package')" class="ico" />{{ $category->name }}
                            </a>
                        @endforeach
                    </x-mp.hscroll>
                </x-mp.section>
            @else
                <x-mp.rail :tone="$tone($i)" :title="$rail['title']" :subtitle="$rail['subtitle']" :more-url="$rail['more']"
                           :products="$rail['products']" :cart-qty="$cartQty" :alert-ids="$alertIds" :key-prefix="$rail['key']" />
            @endif
        @endforeach

        @if($explore->isNotEmpty())
            <x-mp.section :tone="$tone($rails->count())" title="Jelajahi Semua" subtitle="Produk lain di katalog kami">
                <div class="grid">
                    @foreach($explore as $product)
                        <x-mp.product-card :product="$product" :qty="$cartQty[$product->id] ?? 0" :alerted="in_array($product->id, $alertIds)" wire:key="explore-{{ $product->id }}" />
                    @endforeach
                </div>
                @if($exploreHasMore)
                    <div class="explore-more">
                        <button type="button" class="btn btn-secondary" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore">Muat lagi</button>
                    </div>
                @endif
            </x-mp.section>
        @endif
    </main>

    <x-mp.cart-bar :count="$cartCount" :total="$cartTotal" />
    <x-mp.footer />
</div>
