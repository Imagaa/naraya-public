<div class="adm-page">
    <x-admin.page-header title="Produk & Stok">
        {{ $counts['all'] }} produk
        <x-slot:actions><x-ui.button variant="primary" tag="a" href="{{ route('admin.products.create') }}">Tambah produk</x-ui.button></x-slot:actions>
    </x-admin.page-header>
    <x-ui.input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama atau kode barang…" />
    <x-admin.filter-pills :options="['all' => ['Semua', $counts['all']], 'out' => ['Stok habis', $counts['out']], 'low' => ['Menipis', $counts['low']]]" :active="$stockFilter" action="setStockFilter" />
    <x-admin.list cols="110px minmax(0,2.2fr) minmax(0,1.1fr) 90px 120px 120px 80px 110px" :headers="['Kode', 'Nama', 'Kategori', 'Ukuran', 'Harga', 'Setoran sales', 'Stok', 'Status']" :empty="$products->isEmpty()" empty-message="Belum ada produk.">
        @foreach($products as $product)
            <x-admin.list-row :href="route('admin.products.edit', $product)">
                <span class="adm-cell-desk adm-num">{{ $product->sku ?: '—' }}</span>
                <span class="adm-cell-main">{{ $product->name }}</span>
                <span class="adm-cell-desk">
                    @if($product->category)
                        <x-ui.tag :color="$product->category->color">{{ $product->category->name }}</x-ui.tag>
                    @else
                        —
                    @endif
                </span>
                <span class="adm-cell-desk">{{ $product->size_value ? rtrim(rtrim(number_format($product->size_value, 2, ',', '.'), '0'), ',').' '.$product->size_unit : '—' }}</span>
                <span class="adm-num">Rp {{ number_format($product->price, 0, ',', '.') }}</span>
                <span class="adm-cell-desk adm-num">{{ $product->deposit_price !== null ? 'Rp '.number_format($product->deposit_price, 0, ',', '.') : '= harga' }}</span>
                <span class="adm-num {{ $product->stock === 0 ? 'is-out' : '' }}"><span class="adm-phone-label">Stok </span>{{ $product->qtyLabel((int) $product->stock) }}</span>
                <span><x-admin.pill :tone="$product->is_active ? 'ok' : 'muted'">{{ $product->is_active ? 'Aktif' : 'Nonaktif' }}</x-admin.pill></span>
            </x-admin.list-row>
        @endforeach
    </x-admin.list>
    {{ $products->links() }}
</div>
