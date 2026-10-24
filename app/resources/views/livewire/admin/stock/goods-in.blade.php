<div class="adm-page">
    <x-admin.page-header title="Barang masuk">Catat barang yang diterima gudang {{ $line->label() }}. Stok gudang langsung bertambah.</x-admin.page-header>
    @if($flash)<p class="adm-flash" role="status"><x-lucide-check width="16" height="16" />{{ $flash }}</p>@endif

    <x-admin.panel title="Catat barang masuk">
        <form wire:submit="save" class="att-form">
            <div class="stock-rows">
                @foreach($rows as $i => $row)
                    <div class="stock-row" wire:key="gi-row-{{ $i }}">
                        <div class="field">
                            <label for="gi-product-{{ $i }}">Barang</label>
                            <select id="gi-product-{{ $i }}" class="input" wire:model="rows.{{ $i }}.product_id">
                                <option value="">Pilih barang…</option>
                                @foreach($products as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }}{{ $p->ppu() > 1 ? ' (1 '.($p->unit ?: 'satuan').' = '.$p->ppu().' pcs)' : '' }} — stok {{ $p->qtyLabel((int) $p->stock) }}</option>
                                @endforeach
                            </select>
                            @error("rows.$i.product_id")<p class="text-danger">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="gi-qty-{{ $i }}">Jumlah satuan</label>
                            <input id="gi-qty-{{ $i }}" type="number" min="0" step="1" inputmode="numeric" class="input" wire:model="rows.{{ $i }}.qty" placeholder="lembar / dus">
                        </div>
                        <div class="field">
                            <label for="gi-pcs-{{ $i }}">+ pcs</label>
                            <input id="gi-pcs-{{ $i }}" type="number" min="0" step="1" inputmode="numeric" class="input" wire:model="rows.{{ $i }}.pcs" placeholder="0">
                            @error("rows.$i.qty")<p class="text-danger">{{ $message }}</p>@enderror
                        </div>
                        <button type="button" class="adm-icon-btn adm-icon-btn-sm" wire:click="removeRow({{ $i }})" aria-label="Hapus baris {{ $i + 1 }}"><x-lucide-x width="16" height="16" /></button>
                    </div>
                @endforeach
            </div>
            <div><x-ui.button variant="secondary" class="btn-sm" wire:click="addRow"><x-lucide-plus class="ico" />Tambah baris</x-ui.button></div>
            @error('items')<p class="text-danger">{{ $message }}</p>@enderror
            @foreach($errors->getMessages() as $key => $messages)
                @if(str_starts_with($key, 'items.'))<p class="text-danger">{{ $messages[0] }}</p>@endif
            @endforeach
            <x-ui.input label="Catatan (opsional)" wire:model="note" placeholder="Mis. surat jalan / pemasok" />
            <div class="att-actions"><x-ui.save-button target="rows,note">Simpan barang masuk</x-ui.save-button></div>
        </form>
    </x-admin.panel>

    <x-admin.section title="Riwayat barang masuk">
        <x-admin.list cols="118px minmax(0,2.6fr) 110px 120px minmax(0,1fr)" :headers="['Tanggal', 'Barang', 'Jumlah', 'Nilai', 'Dicatat oleh']" :empty="$history->isEmpty()" empty-message="Belum ada barang masuk di lini ini.">
            @foreach($history as $t)
                <x-admin.list-row wire:key="gi-{{ $t->id }}">
                    <span class="adm-num">{{ $t->created_at->wib()->locale('id')->translatedFormat('j M Y H.i') }}</span>
                    <span class="adm-cell-main adm-cell-wide"><x-ui.item-lines :items="$t->items->map(fn ($i) => [$i->product, $i->quantity])" />@if($t->note)<span class="adm-cell-sub">{{ $t->note }}</span>@endif</span>
                    <span class="adm-num"><x-ui.qty-total :items="$t->items->map(fn ($i) => [$i->product, $i->quantity])" /></span>
                    <span class="adm-num">Rp {{ number_format($t->totalValue(), 0, ',', '.') }}</span>
                    <span class="adm-cell-desk">{{ $t->creator?->name }}</span>
                </x-admin.list-row>
            @endforeach
        </x-admin.list>
        {{ $history->links() }}
    </x-admin.section>
</div>
