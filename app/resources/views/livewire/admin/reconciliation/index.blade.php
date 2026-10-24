@php($rp = fn ($v) => number_format($v, 0, ',', '.'))
<div class="adm-page">
    <x-admin.page-header title="Pencocokan">Barang yang dibawa sales dicocokkan dengan uang yang kembali, lini {{ $line->label() }}. Nilai barang memakai harga saat dicatat.</x-admin.page-header>

    <div class="adm-toolbar recon-toolbar">
        <label class="field">Dari <input type="date" class="input" wire:model.live="from" max="{{ $to }}"></label>
        <label class="field">Sampai <input type="date" class="input" wire:model.live="to" min="{{ $from }}" max="{{ \App\Support\BusinessDay::today()->toDateString() }}"></label>
    </div>

    <x-admin.list cols="minmax(170px,1.6fr) repeat(5, minmax(72px,1fr)) repeat(3, minmax(92px,1fr)) 140px"
        :headers="['Sales', 'Diambil', 'Diretur', 'Terjual', 'Selisih cek', 'Bawaan kini', 'Tunai belum setor', 'Non-tunai ditolak', 'Kewajiban tunai', 'Status']"
        :empty="$rows->isEmpty()" empty-message="Belum ada barang atau nota sales di periode ini.">
        @foreach($rows as $r)
            <x-admin.list-row :href="route('admin.reconciliation.index', ['from' => $from, 'to' => $to, 'sales' => $r['sales']->id])" wire:key="rc-{{ $r['sales']->id }}" :class="$selected?->is($r['sales']) ? 'is-active' : ''">
                <span class="adm-cell-main adm-with-avatar"><x-ui.person-avatar :user="$r['sales']" :size="28" />{{ $r['sales']->name }}</span>
                <span class="adm-num">{{ $rp($r['taken']) }}</span>
                <span class="adm-num">{{ $rp($r['returned']) }}</span>
                <span class="adm-num">{{ $rp($r['sold']) }}</span>
                <span class="adm-num">{{ $rp($r['shortfall']) }}</span>
                <span class="adm-num">{{ $rp($r['carried']) }}</span>
                <span class="adm-num">{{ $rp($r['cashOpen']) }}</span>
                <span class="adm-num">{{ $rp($r['nonCashRejected']) }}</span>
                <span class="adm-num"><b>Rp {{ $rp($r['owed']) }}</b></span>
                <span><x-admin.pill :tone="$r['matched'] ? 'ok' : 'bad'">{{ $r['matched'] ? 'Cocok' : 'Perlu tindak lanjut' }}</x-admin.pill></span>
            </x-admin.list-row>
        @endforeach
    </x-admin.list>

    @if($selected)
        @php($r = $rows->first(fn ($row) => $row['sales']->is($selected)))
        <x-admin.panel :title="'Rincian '.$selected->name" class="adm-phone-sheet">
            <x-slot:actions><x-ui.button variant="secondary" wire:click="show(null)">Tutup</x-ui.button></x-slot:actions>
            @if($r)
                <dl class="note-admin-facts">
                    <dt>Penjualan tunai</dt><dd>Rp {{ $rp($r['cash']) }} — disetor &amp; terverifikasi Rp {{ $rp($r['cashDeposited']) }}, belum Rp {{ $rp($r['cashOpen']) }}</dd>
                    <dt>Transfer/QRIS</dt><dd>terverifikasi Rp {{ $rp($r['nonCashVerified']) }} · menunggu Rp {{ $rp($r['nonCashPending']) }} · ditolak Rp {{ $rp($r['nonCashRejected']) }}</dd>
                    @if($r['investCash'])<dt>Investasi tunai</dt><dd>Rp {{ $rp($r['investCash']) }} — belum disetor &amp; terverifikasi Rp {{ $rp($r['investCashOpen']) }}</dd>@endif
                    <dt>Kewajiban tunai</dt><dd><b>Rp {{ $rp($r['owed']) }}</b> = tunai belum disetor + non-tunai ditolak + selisih cek stok + investasi tunai belum disetor</dd>
                </dl>
            @endif

            <h3 class="adm-subhead">Nota</h3>
            <x-admin.list cols="120px 110px minmax(0,1.5fr) 100px 130px minmax(0,1.5fr)" :headers="['No.', 'Tanggal', 'Toko', 'Bayar', 'Total', 'Status']" :empty="$notes->isEmpty()" empty-message="Tidak ada nota.">
                @foreach($notes as $note)
                    <x-admin.list-row :href="route('admin.notes.show', $note)" wire:key="rcn-{{ $note->id }}">
                        <span class="adm-num"><b>{{ $note->number() }}</b></span>
                        <span class="adm-num">{{ $note->created_at->wib()->format('d/m H.i') }}</span>
                        <span>{{ $note->buyer?->name }}</span>
                        <span>{{ $note->payment_method->label() }}</span>
                        <span class="adm-num">Rp {{ $rp($note->total) }}</span>
                        <span>{{ $note->proof_status ? $note->proof_status->label().($note->proof_note ? ' — '.$note->proof_note : '') : ($note->deposit ? 'Setoran #'.$note->deposit->id.' · '.$note->deposit->status->label() : 'Belum disetor') }}</span>
                    </x-admin.list-row>
                @endforeach
            </x-admin.list>

            <h3 class="adm-subhead">Barang</h3>
            <x-admin.list cols="110px 150px minmax(0,2fr) 130px" :headers="['Tanggal', 'Jenis', 'Barang', 'Nilai']" :empty="$transfers->isEmpty()" empty-message="Tidak ada pengambilan atau retur.">
                @foreach($transfers as $t)
                    <x-admin.list-row wire:key="rct-{{ $t->id }}">
                        <span class="adm-num">{{ $t->created_at->wib()->format('d/m H.i') }}</span>
                        <span><x-admin.pill :tone="$t->kind->tone()">{{ $t->kind->label() }}</x-admin.pill></span>
                        <span class="adm-cell-wide"><x-ui.item-lines :items="$t->items->map(fn ($i) => [$i->product, $i->quantity])" /></span>
                        <span class="adm-num">Rp {{ $rp($t->totalValue()) }}</span>
                    </x-admin.list-row>
                @endforeach
            </x-admin.list>

            <h3 class="adm-subhead">Setoran</h3>
            <x-admin.list cols="110px minmax(0,1fr) 130px 130px" :headers="['Tanggal', 'Status', 'Diharapkan', 'Diklaim']" :empty="$deposits->isEmpty()" empty-message="Tidak ada setoran.">
                @foreach($deposits as $d)
                    <x-admin.list-row :href="route('admin.deposits.show', $d)" wire:key="rcd-{{ $d->id }}">
                        <span class="adm-num">{{ $d->created_at->wib()->format('d/m H.i') }}</span>
                        <span>{{ $d->status->label() }}</span>
                        <span class="adm-num">Rp {{ $rp($d->expected_amount) }}</span>
                        <span class="adm-num">Rp {{ $rp($d->claimed_amount) }}</span>
                    </x-admin.list-row>
                @endforeach
            </x-admin.list>
        </x-admin.panel>
    @endif
</div>
