@php($tz = config('app.business_timezone'))
<x-sales.page title="Setoran Cash" :back="$embedded ? null : '1'">
    <x-ui.card title="Cash Belum Disetor">
        <p class="sales-amount">@rupiah($expectedAmount)</p>
        <p class="sales-note">{{ $cashNotes->count() + $heldCollections->count() + $cashInvestments->count() }} transaksi</p>
        @if($cashNotes->isNotEmpty() || $heldCollections->isNotEmpty() || $cashInvestments->isNotEmpty())
            <div class="sales-list">
                @foreach($cashNotes as $note)
                    <x-sales.row wire:key="cn-{{ $note->id }}">
                        <b>{{ $note->number() }} · {{ $note->buyer?->name }}</b>
                        <x-slot:meta>{{ $note->created_at->timezone($tz)->format('d/m H:i') }} · nota @rupiah($note->total)</x-slot:meta>
                        <x-slot:trail>@rupiah($note->deposit_total)</x-slot:trail>
                    </x-sales.row>
                @endforeach
                @foreach($cashInvestments as $inv)
                    <x-sales.row wire:key="ci-{{ $inv->id }}">
                        <b>Investasi {{ $inv->number() }} · {{ $inv->buyer?->name }}</b>
                        <x-slot:meta>{{ $inv->quantity }} paket · {{ $inv->created_at->timezone($tz)->format('d/m H:i') }}</x-slot:meta>
                        <x-slot:trail>@rupiah($inv->amount)</x-slot:trail>
                    </x-sales.row>
                @endforeach
                @foreach($heldCollections as $collection)
                    <x-sales.row wire:key="held-{{ $collection->id }}">
                        <b>Pesanan #{{ $collection->order_id }}</b>
                        <x-slot:meta>{{ $collection->collected_at?->timezone($tz)->format('d/m H:i') }}</x-slot:meta>
                        <x-slot:trail>@rupiah($collection->amount)</x-slot:trail>
                    </x-sales.row>
                @endforeach
            </div>
        @endif
    </x-ui.card>

    @if($cashNotes->isNotEmpty() || $heldCollections->isNotEmpty() || $cashInvestments->isNotEmpty())
        <x-ui.card title="Ajukan Setoran">
            <form wire:submit="submitDeposit" class="sales-form">
                <x-ui.input label="Nominal Disetor" type="number" inputmode="numeric" wire:model="claimedAmount" />
                <x-ui.field-error name="claimedAmount" />
                <x-ui.input label="Catatan (opsional)" wire:model="proofNote" />
                <x-ui.button type="submit" variant="primary">Setor</x-ui.button>
            </form>
        </x-ui.card>
    @endif

    <x-sales.section title="Riwayat Setoran">
        <div class="sales-list">
            @forelse($deposits as $deposit)
                <x-sales.row wire:key="deposit-{{ $deposit->id }}">
                    <b>@rupiah($deposit->claimed_amount)</b>
                    <x-slot:meta>{{ $deposit->created_at->timezone($tz)->format('d M Y') }}</x-slot:meta>
                    <x-slot:trail><x-ui.tag :variant="$deposit->status->variant()">{{ $deposit->status->label() }}</x-ui.tag></x-slot:trail>
                </x-sales.row>
            @empty
                <x-ui.empty-state message="Belum ada riwayat setoran." />
            @endforelse
        </div>
    </x-sales.section>
</x-sales.page>
