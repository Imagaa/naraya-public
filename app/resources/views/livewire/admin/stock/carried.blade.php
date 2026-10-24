<div class="adm-page">
    <x-admin.page-header title="Stok bawaan sales">Barang yang sedang dibawa tiap sales di lini {{ $line->label() }}, dinilai dengan harga saat ini.</x-admin.page-header>
    @if($flash)<p class="adm-flash" role="status"><x-lucide-check width="16" height="16" />{{ $flash }}</p>@endif

    <x-admin.list cols="minmax(0,1.6fr) minmax(0,2.4fr) 120px 140px 150px" :headers="['Sales', 'Barang dibawa', 'Jumlah', 'Nilai', '']" :empty="$rows->isEmpty()" empty-message="Belum ada sales aktif.">
        @foreach($rows as $row)
            @php($s = $row['sales'])
            <x-admin.list-row wire:key="cs-{{ $s->id }}">
                <span class="adm-cell-main adm-with-avatar"><x-ui.person-avatar :user="$s" :size="28" />{{ $s->name }}</span>
                <span class="adm-cell-wide"><x-ui.item-lines :items="$row['items']->map(fn ($q, $id) => [$products[$id] ?? null, $q])" /></span>
                <span class="adm-num">@if($row['items']->isEmpty())0 @else<x-ui.qty-total :items="$row['items']->map(fn ($q, $id) => [$products[$id] ?? null, $q])" />@endif</span>
                <span class="adm-num">Rp {{ number_format($row['value'], 0, ',', '.') }}</span>
                <span class="adm-cell-actions">
                    @if($row['items']->isNotEmpty())
                        <x-ui.button variant="secondary" wire:click="startCheck({{ $s->id }})"><x-lucide-clipboard-check class="ico" />Cek stok</x-ui.button>
                    @endif
                </span>
            </x-admin.list-row>
            @if($checkingId === $s->id)
                <div class="adm-list-extra" wire:key="cs-check-{{ $s->id }}">
                    <form wire:submit="saveCheck" class="att-form">
                        <p class="adm-muted">Hitung barang yang benar-benar dibawa {{ $s->name }}. Kekurangan dicatat sebagai selisih cek stok dan menjadi tanggungan sales.</p>
                        @foreach($counted as $productId => $qty)
                            <div class="stock-check-row" wire:key="cs-{{ $s->id }}-{{ $productId }}">
                                <span>{{ $products[$productId]->name ?? '?' }} <small class="adm-muted">(tercatat {{ $products[$productId]?->qtyLabel($row['items'][$productId] ?? 0) }} — isi dalam pcs)</small></span>
                                <input type="number" min="0" step="1" inputmode="numeric" class="input" wire:model="counted.{{ $productId }}" aria-label="Jumlah dihitung {{ $products[$productId]->name ?? '' }}">
                                @error("items.$productId")<p class="text-danger">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                        <x-ui.input label="Catatan (opsional)" wire:model="checkNote" />
                        <div class="att-actions">
                            <x-ui.button variant="secondary" wire:click="cancelCheck">Batal</x-ui.button>
                            <x-ui.button type="submit" variant="primary">Simpan cek stok</x-ui.button>
                        </div>
                    </form>
                </div>
            @endif
        @endforeach
    </x-admin.list>
</div>
