<div @class(['page', 'has-cart-bar' => $cartCount > 0])>
    <x-mp.header :search="$search" active="products" />

    <main class="container">
        <div class="list-head">
            <h1>{{ $search !== '' ? 'Hasil untuk "'.$search.'"' : ($category ?: 'Semua Produk') }}</h1>
            <p class="muted num">{{ $products->total() }} produk</p>
        </div>

        <x-mp.hscroll class="cat-chips" label="kategori">
            <button type="button" wire:click="$set('category', '')" @class(['cat-chip', 'is-active' => $category === ''])>Semua</button>
            @foreach($categories as $cat)
                <button type="button" wire:click="$set('category', {{ \Illuminate\Support\Js::from($cat->name) }})" @class(['cat-chip', 'is-active' => $category === $cat->name]) style="--c: {{ $cat->color ?? '#F0B90B' }}" wire:key="chip-{{ $cat->id }}">
                    <x-dynamic-component :component="'lucide-'.($cat->icon ?: 'package')" class="ico" />{{ $cat->name }}
                </button>
            @endforeach
        </x-mp.hscroll>

        <div class="list-tools">
            <label class="list-sort">
                <span>Urutkan</span>
                <select wire:model.live="sort" aria-label="Urutkan produk">
                    @foreach($sorts as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="list-toggle"><input type="checkbox" wire:model.live="available">Tersedia saja</label>
        </div>

        @if($search !== '')
            <div class="active-filters">
                <button type="button" class="filter-chip" wire:click="$set('q', '')" aria-label="Hapus pencarian">"{{ $search }}"<x-lucide-x class="ico" /></button>
            </div>
        @endif

        @if($products->isEmpty())
            @php($emptyTitle = $search !== '' ? "Tidak ada produk untuk '{$search}'." : 'Tidak ada produk yang cocok.')
            <section class="panel">
                <x-mp.empty icon="search-x" :title="$emptyTitle" text="Coba kategori lain atau hapus filter.">
                    <button type="button" class="btn btn-secondary" wire:click="clearFilters">Hapus filter</button>
                </x-mp.empty>
            </section>
        @else
            <div class="grid">
                @foreach($products as $product)
                    <x-mp.product-card :product="$product" :qty="$cartQty[$product->id] ?? 0" :alerted="in_array($product->id, $alertIds)" wire:key="list-{{ $product->id }}" />
                @endforeach
            </div>

            {{ $products->links('components.mp.pagination') }}
        @endif
    </main>

    <x-mp.cart-bar :count="$cartCount" :total="$cartTotal" />
    <x-mp.footer />
</div>
