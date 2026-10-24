<x-sales.page :title="'Barang di toko · '.$store->name" back="1">
    <section>
        <p class="sales-note">Pertama kali membuka toko ini di aplikasi: catat barang {{ $line->label() }} kita yang <b>masih ada di toko</b> dari sebelumnya. Ini jadi titik awal titip jual; tiap kunjungan berikutnya sisa barang dihitung, yang berkurang menjadi nota. Kosongkan bila tidak ada.</p>
        <div class="stock-search">
            <x-ui.input type="search" wire:model.live.debounce.400ms="search" placeholder="Cari nama, SKU atau barcode…" aria-label="Cari barang" />
            @if($picked)<span class="stock-picked">{{ $picked }} barang diisi</span>@endif
        </div>
        <div class="sales-list">
            @forelse($products as $p)
                <x-sales.row wire:key="open-{{ $p->id }}">
                    <b>{{ $p->name }}</b>
                    <x-slot:meta>@error("items.{$p->id}")<span class="text-danger">{{ $message }}</span>@enderror</x-slot:meta>
                    <x-slot:trail><x-ui.unit-stepper :product="$p" :value="$qty[$p->id] ?? null" wire:model="qty.{{ $p->id }}" /></x-slot:trail>
                </x-sales.row>
            @empty
                <x-ui.empty-state message="Tidak ada barang yang cocok." />
            @endforelse
        </div>
        {{ $products->links('components.mp.pagination') }}
        @error('items')<p class="text-danger" role="alert">{{ $message }}</p>@enderror
        <div class="stock-actions">
            <x-ui.confirm-button action="save" class="btn-block" yes="Ya, simpan"
                message="Simpan barang awal toko ini? Hanya bisa sekali. Barang yang tidak diisi dianggap tidak ada di toko.">Simpan barang awal</x-ui.confirm-button>
        </div>
    </section>
</x-sales.page>
