<header @class(['hdr', 'has-task' => $title])>
    @if($salesName)
        <div class="util">
            <div class="util-inner">
                <span><x-lucide-map-pin class="ico" />Kirim ke <b>{{ $storeName }}</b>@if($address), {{ $address }}@endif</span>
                <span><x-lucide-headset class="ico" />Sales Anda: <b>{{ $salesName }}</b>@if($salesPhone) · <span class="num">{{ $salesPhone }}</span>@endif</span>
            </div>
        </div>
    @endif

    <div class="hdr-main">
        <a href="{{ url('/') }}" class="logo" aria-label="Naraya, ke beranda"><x-brand.logo class="logo-img" /></a>

        <livewire:marketplace.search-box :q="$search" />

        <div class="actions">
            @auth
                <a href="{{ route('orders.index') }}" class="act" aria-label="Pesanan">
                    <x-lucide-package class="ico" />
                    <span class="act-label">Pesanan<small class="num">{{ $shippedCount }} sedang dikirim</small></span>
                </a>
                <a href="{{ route('rewards.index') }}" class="act" aria-label="Poin loyalti">
                    <x-lucide-gift class="ico" />
                    <span class="act-label num">{{ number_format($points, 0, ',', '.') }} poin<small>Tukar hadiah</small></span>
                </a>
            @endauth
            @if(auth()->user()?->isUser())
                <livewire:marketplace.notification-bell />
            @endif
            @if(config('naraya.marketplace_purchasing_open'))
            <a href="{{ route('checkout') }}" class="act" aria-label="Keranjang">
                <x-lucide-shopping-cart class="ico" />
                @if($cartCount > 0)
                    <span class="cart-badge num">{{ $cartCount }}</span>
                @endif
                <span class="act-label">Keranjang<small class="num">@rupiah($cartTotal)</small></span>
            </a>
            @endif
            @guest
                <a href="{{ route('login') }}" class="btn btn-secondary btn-sm">Masuk</a>
                <a href="{{ route('register') }}" class="btn btn-primary btn-sm hdr-register">Daftar</a>
            @endguest
        </div>
    </div>

    @php($storefront = \App\Support\ActiveLine::current())
    <form method="POST" action="{{ route('line') }}" class="store-tabs" aria-label="Pilih toko">
        @csrf
        <div class="store-tabs-in">
            {{-- open storefronts first; grosir while closed is a disabled tab marked "Sedang dikembangkan" --}}
            @foreach(collect(\App\Enums\BusinessLine::cases())->sortBy(fn ($l) => \App\Support\ActiveLine::isOpen($l) ? 0 : 1) as $line)
                @if(\App\Support\ActiveLine::isOpen($line))
                    <button type="submit" name="line" value="{{ $line->value }}" aria-pressed="{{ $storefront === $line ? 'true' : 'false' }}">
                        <x-brand.logo :line="$line->brand()" class="store-tab-logo" />
                    </button>
                @else
                    <button type="button" disabled class="is-soon" title="{{ $line->label() }} grosir — sedang dikembangkan">
                        <x-brand.logo :line="$line->brand()" class="store-tab-logo" />
                        <small class="store-tab-soon">Sedang dikembangkan</small>
                    </button>
                @endif
            @endforeach
        </div>
    </form>

    <div class="catnav-wrap hscroll-wrap" x-data="hscroll">
        <button type="button" class="hscroll-btn hscroll-prev" x-show="canLeft" x-cloak x-on:click="go(-1)" aria-label="Geser kategori ke kiri"><x-lucide-chevron-left class="ico" /></button>
        <nav class="catnav hscroll" x-ref="track" :class="{ 'can-left': canLeft, 'can-right': canRight }" aria-label="Kategori">
            <a href="{{ route('products.index') }}" class="catnav-all"><x-lucide-layout-grid class="ico" />Semua Kategori</a>
            @foreach($categories as $category)
                <a href="{{ route('products.index', ['category' => $category->name]) }}">{{ $category->name }}</a>
            @endforeach
        </nav>
        <button type="button" class="hscroll-btn hscroll-next" x-show="canRight" x-cloak x-on:click="go(1)" aria-label="Geser kategori ke kanan"><x-lucide-chevron-right class="ico" /></button>
    </div>
</header>

<x-mp.purchase-hold />

@if($title)
    <div class="taskbar">
        <a href="{{ $back ?: url('/') }}" class="icon-btn" aria-label="Kembali"><x-lucide-chevron-left class="ico" /></a>
        <h1>{{ $title }}@if($subtitle)<small>{{ $subtitle }}</small>@endif</h1>
        @if(config('naraya.marketplace_purchasing_open'))<a href="{{ route('checkout') }}" class="icon-btn" aria-label="Keranjang"><x-lucide-shopping-cart class="ico" />@if($cartCount > 0)<span class="cart-badge num">{{ $cartCount }}</span>@endif</a>@endif
    </div>
@endif

@if($tabbar)
    <nav class="tabbar" aria-label="Navigasi utama">
        <a href="{{ url('/') }}" @if($active === 'home') aria-current="page" @endif><x-lucide-house class="ico" />Beranda</a>
        <a href="{{ route('products.index') }}" @if($active === 'products') aria-current="page" @endif><x-lucide-layout-grid class="ico" />Kategori</a>
        <a href="{{ route('orders.index') }}" @if($active === 'orders') aria-current="page" @endif><x-lucide-package class="ico" />Pesanan</a>
        <a href="{{ route('account') }}" @if($active === 'account') aria-current="page" @endif><x-lucide-user class="ico" />Akun</a>
    </nav>
@endif
