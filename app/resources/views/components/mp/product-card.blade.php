@props(['product', 'qty' => 0, 'alerted' => false])

@php
    $unit = $product->unit ?: 'pcs';
    $available = $product->isAvailable();
    $meta = collect([$product->sizeLabel(), $product->pieces_per_unit ? 'isi '.$product->pieces_per_unit : null])->filter()->implode(' · ');
@endphp

<article {{ $attributes->merge(['class' => 'pc']) }} data-product-id="{{ $product->id }}" style="--c: {{ $product->category?->color ?? '#F0B90B' }}">
    <a class="pc-link" href="{{ $product->url() }}">
        <div class="ph">
            <x-mp.product-photo :product="$product" />
            @if($product->hasDiscount())
                <span class="ph-sale num">−{{ $product->discountPercent() }}%</span>
            @endif
            @unless($available)
                <span class="ph-out">Stok habis</span>
            @endunless
        </div>
        <div class="pc-body">
            @if($meta)
                <div class="pc-meta">{{ $meta }}</div>
            @endif
            <h3 class="pc-name">{{ $product->name }}</h3>
            <div class="price">
                @if($product->hasDiscount())
                    <s class="num">@rupiah($product->price)</s>
                @endif
                <strong class="num">@rupiah($product->effectivePrice())</strong>
                <span class="per">/{{ $unit }}</span>
            </div>
            @if($product->pricePerPiece())
                <div class="price-pcs num">≈ @rupiah($product->pricePerPiece()) per pcs</div>
            @endif
            @if($product->discountDaysLeft())
                <div class="pc-ends num">Sisa {{ $product->discountDaysLeft() }} hari</div>
            @endif
        </div>
    </a>
    <div class="pc-foot">
        <span class="chip num">Min. {{ $product->minQuantity() }} {{ $unit }}</span>
        @if(! $available && auth()->guest())
            <a class="btn btn-secondary btn-sm" href="{{ route('login') }}"><x-lucide-bell class="ico" />Kabari saya</a>
        @elseif(! $available)
            <button type="button" class="btn btn-secondary btn-sm" wire:click="toggleStockAlert({{ $product->id }})" aria-pressed="{{ $alerted ? 'true' : 'false' }}">
                @if($alerted)<x-lucide-bell-ring class="ico" />Dikabari @else<x-lucide-bell class="ico" />Kabari saya @endif
            </button>
        @elseif(! config('naraya.marketplace_purchasing_open'))
        @elseif(auth()->guest())
            <a class="add" href="{{ route('login') }}" aria-label="Masuk untuk menambah {{ $product->name }}"><x-lucide-plus class="ico" /></a>
        @elseif($qty > 0)
            <div class="step" role="group" aria-label="Jumlah {{ $product->name }} di keranjang">
                <button type="button" wire:click="setCartQuantity({{ $product->id }}, {{ $qty - 1 }})" aria-label="Kurangi"><x-lucide-minus class="ico" /></button>
                <span class="num">{{ $qty }}</span>
                <button type="button" wire:click="setCartQuantity({{ $product->id }}, {{ $qty + 1 }})" aria-label="Tambah" @disabled($qty >= $product->unitsInStock())><x-lucide-plus class="ico" /></button>
            </div>
        @else
            <button type="button" class="add" wire:click="addToCart({{ $product->id }})" aria-label="Tambah {{ $product->name }} ke keranjang"><x-lucide-plus class="ico" /></button>
        @endif
    </div>
</article>
