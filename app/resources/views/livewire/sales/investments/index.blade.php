@php($tz = config('app.business_timezone'))
<x-sales.page title="Investasi Toko" back="1" class="inv-scope">
    @if($flash)<p class="absen-chip is-ok" role="status">{{ $flash }}</p>@endif

    <x-ui.card title="Catat pembelian tunai">
        <p class="sales-note">Toko membeli paket investasi dan membayar tunai kepada Anda. Uangnya ikut disetor bersama setoran {{ $cashLine }}.</p>
        <form wire:submit="save" class="sales-form">
            <div class="field">
                <label for="inv-store">Toko</label>
                <select id="inv-store" class="input" wire:model.live="storeId">
                    <option value="">Pilih toko…</option>
                    @foreach($stores as $s)<option value="{{ $s->user_id }}">{{ $s->user?->name }}</option>@endforeach
                </select>
                <x-ui.field-error name="storeId" />
            </div>
            <div class="field">
                <span id="inv-qty">Jumlah paket</span>
                <div class="step" role="group" aria-labelledby="inv-qty">
                    <button type="button" wire:click="changeQty(-1)" aria-label="Kurangi" @disabled($qty <= 1)>−</button>
                    <span class="num" aria-live="polite">{{ $qty }}</span>
                    <button type="button" wire:click="changeQty(1)" aria-label="Tambah">+</button>
                </div>
            </div>
            <p class="sales-note">Diterima tunai <b>@rupiah($amount)</b> · pendapatan toko @rupiah($monthly) per bulan, {{ $terms && $terms['first'] ? $terms['months'].' kali mulai bulan ke-2 setelah paket aktif (pembelian pertama toko ini)' : 'mulai sebulan setelah paket aktif' }}.</p>
            <x-ui.checkbox label="Uang tunainya sudah saya terima dari toko" wire:model="received" />
            <x-ui.field-error name="received" />
            <x-ui.field-error name="quantity" />
            <x-ui.button type="submit" variant="primary">Simpan</x-ui.button>
        </form>
    </x-ui.card>

    <x-sales.section title="Riwayat">
        <div class="sales-list">
            @forelse($purchases as $p)
                <x-sales.row wire:key="ip-{{ $p->id }}">
                    <b>{{ $p->number() }} · {{ $p->buyer?->name }}</b>
                    <x-slot:meta>{{ $p->quantity }} paket · {{ $p->created_at->timezone($tz)->format('d/m/Y H:i') }} · {{ $p->deposit ? 'Disetor ('.$p->deposit->status->label().')' : 'Belum disetor' }}</x-slot:meta>
                    <x-slot:trail>@rupiah($p->amount)</x-slot:trail>
                </x-sales.row>
            @empty
                <x-ui.empty-state message="Belum ada pembelian investasi lewat Anda." />
            @endforelse
        </div>
    </x-sales.section>
</x-sales.page>
