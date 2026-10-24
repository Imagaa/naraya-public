<x-sales.page :title="'Nota baru · '.$line->label()" back="1">
    @if($step === 'pilih')
        <x-sales.section title="Toko">
            <div class="field">
                <label for="note-store">Toko</label>
                <select id="note-store" class="input" wire:model="buyerId">
                    <option value="">Pilih toko…</option>
                    @foreach($stores as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                </select>
                @error('buyerId')<p class="text-danger" role="alert">{{ $message }}</p>@enderror
            </div>
        </x-sales.section>

        <x-sales.section title="Barang">
            @if($carried->isEmpty())
                <x-ui.empty-state message="Anda belum membawa barang dari lini ini. Tambah barang dulu dari kartu Stok bawaan." />
            @else
                <div class="sales-list">
                    @foreach($carried as $id => $max)
                        <x-sales.row wire:key="note-item-{{ $id }}">
                            <b>{{ $products[$id]->name ?? '?' }}</b>
                            <x-slot:meta>Rp {{ number_format($products[$id]->price ?? 0, 0, ',', '.') }}/{{ $products[$id]->unit ?: 'pcs' }} · dibawa {{ $products[$id]->qtyLabel($max) }}@error("qty.$id")<span class="text-danger"> · {{ $message }}</span>@enderror</x-slot:meta>
                            <x-slot:trail><x-ui.unit-stepper :product="$products[$id]" :max="$max" :value="$qty[$id] ?? null" wire:model="qty.{{ $id }}" /></x-slot:trail>
                        </x-sales.row>
                    @endforeach
                </div>
            @endif
            @error('qty')<p class="text-danger" role="alert">{{ $message }}</p>@enderror
        </x-sales.section>

        <x-sales.section title="Pembayaran">
            <fieldset class="check-group" x-data>
                <legend>Dibayar dengan</legend>
                @foreach($methods as $m)
                    <x-ui.checkbox chip type="radio" :label="$m->label()" name="note-method" value="{{ $m->value }}" wire:model.live="method" />
                @endforeach
            </fieldset>
            @if($method !== 'cash')
                <x-ui.camera-photo wire-model="proof" label="Foto bukti {{ \App\Enums\NotePaymentMethod::from($method)->label() }}" />
                @error('proof')<p class="text-danger" role="alert">{{ $message }}</p>@enderror
                <p class="sales-note">Transfer ke <b>{{ $transferTo }}</b>. Admin akan memeriksa bukti ini.</p>
            @else
                <p class="sales-note">Uang tunai Anda setorkan ke admin lewat menu Setoran.</p>
            @endif
            <div class="stock-actions">
                <x-ui.button variant="primary" wire:click="review" :disabled="$carried->isEmpty()">Lanjut</x-ui.button>
            </div>
        </x-sales.section>
    @else
        <x-sales.section title="Periksa nota">
            <p class="sales-note">Toko <b>{{ $store?->name }}</b> · {{ \App\Enums\NotePaymentMethod::from($method)->label() }}</p>
            <div class="sales-list">
                @foreach($chosen as $id => $q)
                    <x-sales.row wire:key="note-review-{{ $id }}">
                        <b>{{ $products[$id]->name ?? '?' }}</b>
                        <x-slot:meta>{{ $products[$id]->qtyLabel($q) }} × Rp {{ number_format($products[$id]->price ?? 0, 0, ',', '.') }}/{{ $products[$id]->unit ?: 'pcs' }}</x-slot:meta>
                        <x-slot:trail><b>Rp {{ number_format($products[$id]->valueOf($q), 0, ',', '.') }}</b></x-slot:trail>
                    </x-sales.row>
                @endforeach
            </div>
            <p class="note-total">Total <b>Rp {{ number_format($total, 0, ',', '.') }}</b></p>
            <div class="stock-actions">
                <x-ui.button variant="secondary" wire:click="back">Ubah</x-ui.button>
                <x-ui.button variant="primary" wire:click="save" wire:loading.attr="disabled">Simpan nota</x-ui.button>
            </div>
        </x-sales.section>
    @endif
</x-sales.page>
