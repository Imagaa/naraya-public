<x-sales.page title="Absen pulang" back="1">
    <ol class="visit-steps" aria-label="Langkah absen pulang">
        @foreach(['setor' => 'Setoran', 'barang' => 'Barang', 'foto' => 'Foto'] as $key => $name)
            <li @class(['is-now' => $step === $key]) @if($step === $key) aria-current="step" @endif>{{ $name }}</li>
        @endforeach
    </ol>

    @if($step === 'setor')
        <section>
            @if($cash > 0)
                <p class="sales-note">Sebelum pulang, setorkan semua cash yang Anda bawa: <b>Rp {{ number_format($cash, 0, ',', '.') }}</b>.</p>
            @else
                <p class="sales-note">Semua cash sudah disetor.</p>
            @endif
            @error('cash')<p class="text-danger" role="alert">{{ $message }}</p>@enderror
        </section>
        @if($cash > 0)<livewire:sales.deposits.index :embedded="true" :key="'checkout-deposit'" />@endif
        <section class="stock-actions"><x-ui.button variant="primary" wire:click="next" :disabled="$cash > 0">Lanjut</x-ui.button></section>
    @elseif($step === 'barang')
        @if($recap)
            <section>
                <h2 class="visit-h">Rekap hari ini</h2>
                <x-sales.shift-recap :recap="$recap" />
            </section>
        @endif
        <section>
            @if($goods->isEmpty())
                <p class="sales-note">Anda tidak membawa barang. Lanjut ke foto.</p>
            @else
                <p class="sales-note">Semua barang di bawah ini dikembalikan ke gudang saat absen pulang terkirim. Hitung dan serahkan ke admin gudang.</p>
                @foreach($goods as $g)
                    @if($g['carried']->isNotEmpty())
                        <h2 class="visit-h">Sisa bawaan · {{ $g['line']->label() }}</h2>
                        <ul class="store-stock">
                            @foreach($g['carried'] as $id => $q)<li wire:key="c-{{ $g['line']->value }}-{{ $id }}"><span>{{ $products[$id]->name ?? '?' }}</span><b>{{ $products[$id]?->qtyLabel($q) }}</b></li>@endforeach
                        </ul>
                    @endif
                    @if($g['returns']->isNotEmpty())
                        <h2 class="visit-h">Barang retur dari toko · {{ $g['line']->label() }}</h2>
                        <ul class="store-stock">
                            @foreach($g['returns'] as $id => $q)<li wire:key="r-{{ $g['line']->value }}-{{ $id }}"><span>{{ $products[$id]->name ?? '?' }}</span><b>{{ $products[$id]?->qtyLabel($q) }}</b></li>@endforeach
                        </ul>
                    @endif
                @endforeach
            @endif
            <div class="stock-actions">
                <x-ui.button variant="secondary" wire:click="back">Kembali</x-ui.button>
                <x-ui.button variant="primary" wire:click="next">Lanjut ke foto</x-ui.button>
            </div>
        </section>
    @else
        <section>
            <x-sales.absen-camera :cfg="$cfg" />
            <div class="stock-actions"><x-ui.button variant="secondary" wire:click="back">Kembali</x-ui.button></div>
        </section>
    @endif
</x-sales.page>
