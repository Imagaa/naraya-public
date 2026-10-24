<x-sales.page :title="'Riwayat stok · '.$line->label()" back="1">
    <div class="sales-list">
        @forelse($history as $t)
            <x-sales.row wire:key="hist-{{ $t->id }}">
                <b>{{ $t->kind->label() }}</b>
                <x-slot:meta>{{ $t->created_at->wib()->format('d/m/Y H:i') }}<x-ui.item-lines :items="$t->items->map(fn ($i) => [$i->product, $i->quantity])" class="item-lines-sm" /></x-slot:meta>
            </x-sales.row>
        @empty
            <x-ui.empty-state message="Belum ada riwayat stok." />
        @endforelse
    </div>
</x-sales.page>
