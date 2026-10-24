@php($titles = ['masuk' => 'Absen masuk · pilih stok', 'ambil' => 'Tambah barang dari gudang', 'retur' => 'Retur ke gudang'])
<x-sales.page :title="$titles[$mode]" back="1">
    @if($step === 'pilih')
        <section>
            <p class="sales-note">
                @if($mode === 'masuk')
                    Barang {{ $line->label() }} yang Anda bawa hari ini dari gudang. Absen masuk wajib membawa barang. Lalu foto absen.
                @elseif($mode === 'ambil')
                    Isi jumlah lembar yang Anda ambil. Tidak bisa melebihi stok gudang.
                @else
                    Isi jumlah yang Anda kembalikan ke gudang. Sisa pcs (retur toko, lembar terbuka) boleh diisi di kolom pcs.
                @endif
            </p>
            @if($limits === [])
                <x-ui.empty-state :message="$mode === 'retur' ? 'Tidak ada barang untuk diretur.' : 'Stok gudang lini ini sedang kosong.'" />
            @else
                <div class="stock-search">
                    <x-ui.input type="search" wire:model.live.debounce.400ms="search" placeholder="Cari nama, SKU atau barcode…" aria-label="Cari barang" />
                    @if($pickedCount)<span class="stock-picked">{{ $pickedCount }} barang dipilih</span>@endif
                </div>
                <div class="sales-list">
                    @forelse($choices as $p)
                        @php($max = $limits[$p->id])
                        <x-sales.row wire:key="pick-{{ $mode }}-{{ $p->id }}">
                            <b>{{ $p->name }}</b>
                            <x-slot:meta>{{ $mode === 'retur' ? 'Dibawa' : 'Stok gudang' }}: {{ $p->qtyLabel($max) }}@error("qty.{$p->id}")<span class="text-danger"> · {{ $message }}</span>@enderror</x-slot:meta>
                            <x-slot:trail>
                                @if($mode === 'retur')
                                    <x-ui.unit-qty :product="$p" :max="$max" wire:model="qty.{{ $p->id }}" />
                                @else
                                    <x-ui.unit-stepper :product="$p" :max="$max" :value="$qty[$p->id] ?? null" wire:model="qty.{{ $p->id }}" />
                                @endif
                            </x-slot:trail>
                        </x-sales.row>
                    @empty
                        <x-ui.empty-state message="Tidak ada barang yang cocok dengan pencarian." />
                    @endforelse
                </div>
                {{ $choices->links('components.mp.pagination') }}
            @endif
            @error('qty')<p class="text-danger" role="alert">{{ $message }}</p>@enderror
            @if($mode !== 'masuk')<x-ui.input label="Catatan (opsional)" wire:model="note" />@endif
            <div class="stock-actions">
                <x-ui.button variant="primary" wire:click="review" :disabled="$limits === []">{{ $mode === 'masuk' ? 'Lanjut ke foto absen' : 'Lanjut' }}</x-ui.button>
            </div>
        </section>
    @else
        <section>
            @if($chosen)
                <p class="sales-note">{{ $mode === 'retur' ? 'Dikembalikan' : 'Dibawa' }}:</p>
                <ul class="stock-review">
                    @foreach($chosen as $id => $q)
                        <li>{{ ($chosenProducts[$id]->name ?? '?').' · '.($chosenProducts[$id]?->qtyLabel($q) ?? $q) }}</li>
                    @endforeach
                </ul>
            @endif
            @if($note)<p class="sales-note">Catatan: {{ $note }}</p>@endif
            <div class="stock-actions">
                <x-ui.button variant="secondary" wire:click="back">Ubah barang</x-ui.button>
                @if($step === 'konfirmasi')
                    <x-ui.button variant="primary" wire:click="confirm" wire:loading.attr="disabled">{{ $mode === 'ambil' ? 'Konfirmasi ambil' : 'Konfirmasi retur' }}</x-ui.button>
                @endif
            </div>
        </section>
        @if($step === 'foto')
            <section>
                <x-sales.absen-camera :cfg="$cfg" />
            </section>
        @endif
    @endif
</x-sales.page>
