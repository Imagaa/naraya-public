@php($rp = fn ($v) => 'Rp '.number_format($v, 0, ',', '.'))
<x-sales.page :title="'Kunjungan · '.$store->name" back="1">
    <ol class="visit-steps" aria-label="Langkah kunjungan">
        @foreach(['hitung' => 'Hitung sisa', 'titip' => 'Titip', 'bayar' => 'Bayar'] as $key => $name)
            <li @class(['is-now' => $step === $key]) @if($step === $key) aria-current="step" @endif>{{ $name }}</li>
        @endforeach
    </ol>

    @if($step === 'hitung')
        <section>
            @if($before->isEmpty())
                <p class="sales-note">Belum ada barang titipan di toko ini. Lanjut untuk menitipkan barang.</p>
            @else
                {{-- what was left last time; the count of what is still there gives the setoran per item as it is typed --}}
                <div class="visit-old">
                    <span>Titipan kunjungan lalu @if($lastVisit)· <b>{{ $lastVisit->wib()->translatedFormat('j M Y, H:i') }}</b>@endif</span>
                    <span>{{ $before->count() }} barang · {{ number_format($before->sum(), 0, ',', '.') }} pcs · {{ $rp($before->sum(fn ($q, $id) => $products[$id]->valueOf($q))) }}</span>
                </div>
                <p class="sales-note">Hitung <b>sisa di toko</b> (dalam pcs). Sisa dibawa kembali (retur, diserahkan ke gudang saat absen pulang); yang berkurang terjual dan disetor toko.</p>
                <div x-data="{ left: @js($before->map(fn ($had, $id) => (int) ($remaining[$id] ?? $had))), had: @js($before), price: @js($before->map(fn ($q, $id) => $products[$id]->price / $products[$id]->ppu())),
                        pay(id) { return Math.round(Math.max(0, this.had[id] - (parseInt(this.left[id]) || 0)) * this.price[id]) },
                        rp(v) { return 'Rp ' + v.toLocaleString('id-ID') } }">
                    <h2 class="visit-h">Barang yang ada di toko</h2>
                    <ol class="visit-count">
                        @foreach($before as $id => $had)
                            <li wire:key="v-{{ $id }}">
                                <b>{{ $products[$id]->name }}</b>
                                <span class="visit-had">Dititip {{ $products[$id]->qtyLabel($had) }}@if($products[$id]->ppu() > 1) = {{ $had }} pcs @endif</span>
                                <label class="visit-field"><span>Sisa di toko</span>
                                    <span class="unit-step"><x-ui.qty-stepper :label="$products[$id]->name" :max="$had" wire:model="remaining.{{ $id }}" x-model.number="left[{{ $id }}]" /><small>pcs</small></span>
                                </label>
                                @error("remaining.$id")<p class="text-danger">{{ $message }}</p>@enderror
                                <p class="visit-pay"><span>Terjual <b x-text="Math.max(0, had[{{ $id }}] - (parseInt(left[{{ $id }}]) || 0)) + ' pcs'"></b></span><span>Setoran <b x-text="rp(pay({{ $id }}))"></b></span></p>
                            </li>
                        @endforeach
                    </ol>
                    <p class="note-total">Total setoran <b x-text="rp(Object.keys(had).reduce((s, id) => s + pay(id), 0))"></b></p>
                </div>
            @endif
            <div class="stock-actions"><x-ui.button variant="primary" wire:click="next">Lanjut</x-ui.button></div>
        </section>
    @elseif($step === 'titip')
        <section>
            <p class="sales-note">Titipkan barang dari bawaan Anda, dalam lembar (boleh kosong).</p>
            @if($carried->isEmpty())
                <x-ui.empty-state message="Anda tidak membawa barang lini ini." />
            @else
                <div class="sales-list">
                    @foreach($carried as $id => $max)
                        <x-sales.row wire:key="t-{{ $id }}">
                            <b>{{ $products[$id]->name }}</b>
                            <x-slot:meta>Dibawa {{ $products[$id]->qtyLabel($max) }}@error("titip.$id")<span class="text-danger"> · {{ $message }}</span>@enderror</x-slot:meta>
                            <x-slot:trail><x-ui.unit-stepper :product="$products[$id]" :max="$max" :value="$titip[$id] ?? null" wire:model="titip.{{ $id }}" /></x-slot:trail>
                        </x-sales.row>
                    @endforeach
                </div>
            @endif
            <div class="stock-actions">
                <x-ui.button variant="secondary" wire:click="back">Kembali</x-ui.button>
                <x-ui.button variant="primary" wire:click="next">Lanjut</x-ui.button>
            </div>
        </section>
    @else
        <section>
            <h2 class="visit-h">Terjual</h2>
            @if($sold->isEmpty())
                <p class="sales-note">Tidak ada yang terjual sejak kunjungan terakhir.</p>
            @else
                <ul class="visit-sum">
                    @foreach($sold as $id => $q)<li wire:key="s-{{ $id }}"><span>{{ $products[$id]->name }} · {{ $products[$id]->qtyLabel($q) }}</span><b>{{ $rp($products[$id]->valueOf($q)) }}</b></li>@endforeach
                </ul>
                <p class="note-total">Dibayar toko <b>{{ $rp($total) }}</b></p>
            @endif

            @if($returns->isNotEmpty())
                <h2 class="visit-h">Dibawa kembali (retur)</h2>
                <ul class="visit-sum">
                    @foreach($returns as $id => $q)<li wire:key="r-{{ $id }}"><span>{{ $products[$id]->name }}</span><b>{{ $products[$id]->qtyLabel($q) }}</b></li>@endforeach
                </ul>
            @endif

            <h2 class="visit-h">Dititip hari ini</h2>
            @if($left->isEmpty())
                <p class="sales-note">Tidak ada barang yang dititip.</p>
            @else
                <ul class="visit-sum">
                    @foreach($left as $id => $q)<li wire:key="l-{{ $id }}"><span>{{ $products[$id]->name }}</span><b>{{ $products[$id]->qtyLabel($q) }}</b></li>@endforeach
                </ul>
            @endif

            @if($sold->isNotEmpty())
                <fieldset class="check-group">
                    <legend>Dibayar dengan</legend>
                    @foreach($methods as $m)
                        <x-ui.checkbox chip type="radio" :label="$m->label()" name="visit-method" value="{{ $m->value }}" wire:model.live="method" />
                    @endforeach
                </fieldset>
                @if($method !== 'cash')
                    <p class="sales-note">Transfer ke <b>{{ $transferTo }}</b>.</p>
                    <x-ui.camera-photo wire-model="proof" label="Foto bukti {{ \App\Enums\NotePaymentMethod::from($method)->label() }}" />
                @endif
                @error('proof')<p class="text-danger" role="alert">{{ $message }}</p>@enderror
            @endif
            @foreach($errors->getMessages() as $key => $messages)
                @if(! str_starts_with($key, 'proof'))<p class="text-danger" role="alert">{{ $messages[0] }}</p>@endif
            @endforeach
            <div class="stock-actions">
                <x-ui.button variant="secondary" wire:click="back">Kembali</x-ui.button>
                <x-ui.button variant="primary" wire:click="save" wire:loading.attr="disabled">{{ $sold->isNotEmpty() ? 'Simpan & buat nota' : 'Simpan kunjungan' }}</x-ui.button>
            </div>
        </section>
    @endif
</x-sales.page>
