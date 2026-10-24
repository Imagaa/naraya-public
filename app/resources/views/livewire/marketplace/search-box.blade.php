{{-- Header search (combobox): blurs the page behind it while open. ↑/↓ pick a row, Enter opens it (or searches all), Esc closes. --}}
<div class="sbox" x-data="searchBox" :class="{ 'is-open': open }" x-on:keydown.escape.window="close()">
    <form class="search" method="GET" action="{{ route('products.index') }}" role="search" x-on:submit="submit($event)">
        <x-lucide-search class="ico" />
        <input type="search" name="q" value="{{ $q }}" wire:model.live.debounce.250ms="q" x-ref="input" autocomplete="off"
               x-on:focus="open = true" x-on:input="active = -1"
               x-on:keydown.down.prevent="move(1)" x-on:keydown.up.prevent="move(-1)"
               placeholder="Cari produk, merek, atau kategori" aria-label="Cari produk"
               role="combobox" aria-autocomplete="list" aria-controls="sbox-panel" :aria-expanded="open.toString()">
    </form>

    <div class="sbox-backdrop" x-show="open" x-cloak x-transition.opacity x-on:click="close()"></div>

    <div class="sbox-panel" id="sbox-panel" role="listbox" aria-label="Saran pencarian" x-show="open" x-cloak>
        @if($term !== '')
            <a href="{{ route('products.index', ['q' => $term]) }}" class="sbox-all sbox-opt" role="option">
                <x-lucide-search class="ico" />
                <span class="sbox-all-text">Cari semua "<b>{{ $term }}</b>"</span>
                <span class="sbox-count num">{{ $total }} produk</span>
            </a>
            @forelse($results as $product)
                <a href="{{ $product->url() }}" wire:click.prevent="visit({{ $product->id }})" class="sbox-item sbox-opt" role="option" wire:key="sbox-{{ $product->id }}">
                    <span class="sbox-ph"><x-mp.product-photo :product="$product" /></span>
                    <span class="sbox-name">
                        {{ $product->name }}
                        @unless($product->isAvailable())<small class="sbox-out">Stok habis</small>@endunless
                    </span>
                    <span class="sbox-price">
                        @if($product->hasDiscount())<s class="num">@rupiah($product->price)</s>@endif
                        <strong class="num">@rupiah($product->effectivePrice())</strong><small>/{{ $product->unit ?: 'pcs' }}</small>
                    </span>
                </a>
            @empty
                <p class="sbox-empty">Tidak ada produk untuk "{{ $term }}". Coba kata lain atau periksa ejaannya.</p>
            @endforelse
        @else
            @if($history)
                <div class="sbox-head">
                    <span>Pencarian terakhir</span>
                    <button type="button" class="sbox-clear" wire:click="clearHistory">Hapus semua</button>
                </div>
                @foreach($history as $past)
                    <div class="sbox-past" wire:key="past-{{ md5($past) }}">
                        <a href="{{ route('products.index', ['q' => $past]) }}" class="sbox-opt" role="option"><x-lucide-history class="ico" />{{ $past }}</a>
                        <button type="button" class="sbox-forget" wire:click="forget({{ \Illuminate\Support\Js::from($past) }})" aria-label="Hapus {{ $past }} dari riwayat"><x-lucide-x class="ico" /></button>
                    </div>
                @endforeach
            @endif
            @if($popular)
                <div class="sbox-head"><span>Pencarian populer</span></div>
                <div class="sbox-chips">
                    @foreach($popular as $hot)
                        <a href="{{ route('products.index', ['q' => $hot]) }}" class="sbox-chip sbox-opt" role="option"><x-lucide-trending-up class="ico" />{{ $hot }}</a>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</div>
