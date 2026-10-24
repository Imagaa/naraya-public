<x-sales.page :title="'Nota '.$note->number()" back="1">
    @php($rp = fn ($v) => number_format($v, 0, ',', '.'))
    {{-- the receipt, in the same thermal-slip look as the order receipt (resources/views/receipts/show.blade.php); this is what prints --}}
    <section class="slip-wrap">
        <div class="slip">
            <x-brand.logo :line="$note->line->brand()" tone="black" class="slip-logo" />
            <p class="slip-c slip-big">{{ config('receipt.name') }}</p>
            <p class="slip-c">Telp {{ config('receipt.phone') }}</p>
            <hr class="slip-rule2">
            <p class="slip-c"><b>NOTA PENJUALAN</b></p>
            <dl class="slip-rows">
                <dt>No</dt><dd>{{ $note->number() }}</dd>
                <dt>Tanggal</dt><dd>{{ $note->created_at->wib()->format('d/m/Y H:i') }}</dd>
                <dt>Sales</dt><dd>{{ $note->salesUser?->name }}</dd>
            </dl>
            <hr class="slip-rule">
            <p><b>{{ $note->buyer?->name }}</b></p>
            <hr class="slip-rule">
            @foreach($note->items as $item)
                <p class="slip-item">{{ $item->product_name }}</p>
                <dl class="slip-rows"><dt>{{ $item->qtyLabel() }} × {{ $rp($item->price) }}</dt><dd>{{ $rp($item->subtotal) }}</dd></dl>
            @endforeach
            <hr class="slip-rule2">
            <dl class="slip-rows">
                <dt><b>TOTAL</b></dt><dd><b>Rp {{ $rp($note->total) }}</b></dd>
                <dt>Bayar</dt><dd>{{ $note->payment_method->label() }}</dd>
            </dl>
            @if($note->payment_method->value === 'cash' || $note->proof_status?->value === 'verified')
                <p class="slip-c"><span class="slip-stamp">LUNAS</span></p>
            @endif
            <hr class="slip-rule">
            <p class="slip-c">{{ config('receipt.footer') }}</p>
        </div>
    </section>

    {{-- the same items, roomier, for reading on the phone --}}
    <x-sales.section title="Rincian">
        <ul class="note-lines">
            @foreach($note->items as $item)
                <li>
                    <span><b>{{ $item->product_name }}</b><small>{{ $item->qtyLabel() }} × Rp {{ $rp($item->price) }}</small></span>
                    <b class="num">Rp {{ $rp($item->subtotal) }}</b>
                </li>
            @endforeach
        </ul>
        <p class="note-total">Total <b>Rp {{ $rp($note->total) }}</b></p>
        <p class="note-meta">
            Dibayar: <b>{{ $note->payment_method->label() }}</b>
            @if($note->proof_status) · <x-ui.tag :variant="$note->proof_status->salesVariant()">{{ $note->proof_status->label() }}</x-ui.tag>@endif
        </p>
        @if($note->proof_status?->value === 'rejected' && $note->proof_note)
            <p class="text-danger">Bukti ditolak admin: {{ $note->proof_note }}</p>
        @endif
    </x-sales.section>

    {{-- @js() stays on this plain div: inside an <x-ui.button> attribute it would not be compiled. --}}
    <div class="stock-actions note-actions" x-data="{ text: @js($share), title: @js('Nota '.$note->number()) }">
        <x-ui.button variant="primary" x-on:click="navigator.share ? navigator.share({ title, text }) : window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank')"><x-lucide-share-2 class="ico" />Bagikan</x-ui.button>
        <x-ui.button variant="secondary" x-on:click="window.print()"><x-lucide-printer class="ico" />Cetak</x-ui.button>
    </div>
</x-sales.page>
