<div class="adm-page adm-detail">
    <a href="{{ route('admin.deposits.index') }}" class="adm-back"><x-lucide-arrow-left width="16" height="16" /> Setoran sales</a>
    <x-admin.page-header :title="'Setoran #'.$deposit->id">{{ $deposit->salesUser->name }}</x-admin.page-header>

    <div class="adm-detail-side">
    <x-ui.card>
        <p style="display:flex;align-items:center;gap:8px"><strong>Sales:</strong> <x-ui.person-avatar :user="$deposit->salesUser" :size="28" />{{ $deposit->salesUser->name }}</p>
        <p><strong>Diharapkan:</strong> Rp {{ number_format($deposit->expected_amount, 0, ',', '.') }}</p>
        <p><strong>Diklaim:</strong> Rp {{ number_format($deposit->claimed_amount, 0, ',', '.') }}</p>
        <p><strong>Status:</strong> <x-ui.tag variant="accent">{{ $deposit->status->label() }}</x-ui.tag></p>
        @if($deposit->resolution_note)
            <p><strong>Resolusi:</strong> {{ $deposit->resolution_note }} ({{ $deposit->resolvedBy?->name }})</p>
        @endif
    </x-ui.card>
    @if($deposit->status->value === 'submitted')
        <x-ui.button variant="primary" wire:click="verify">Verifikasi</x-ui.button>
    @elseif($deposit->status->value === 'disputed')
        <x-ui.card title="Selesaikan Sengketa Cash">
            <form wire:submit="resolveDispute" style="display:flex;flex-direction:column;gap:12px">
                <x-ui.input label="Catatan Resolusi" wire:model="resolutionNote" />
                @error('resolutionNote') <span style="color:var(--color-danger);font-size:12px">{{ $message }}</span> @enderror
                <x-ui.button type="submit" variant="primary">Selesaikan</x-ui.button>
            </form>
        </x-ui.card>
    @endif
    </div>

    <div class="adm-detail-main">
    <x-ui.table :headers="['Nota / Pesanan', 'Toko', 'Nominal']">
        @foreach($deposit->notes as $note)
            <tr>
                <td><a href="{{ route('admin.notes.show', $note) }}">{{ $note->number() }}</a></td>
                <td>{{ $note->buyer?->name }}</td>
                <td>Rp {{ number_format($note->deposit_total, 0, ',', '.') }} <small class="text-muted">(nota Rp {{ number_format($note->total, 0, ',', '.') }})</small></td>
            </tr>
        @endforeach
        @foreach($deposit->investments as $inv)
            <tr>
                <td>Investasi {{ $inv->number() }} ({{ $inv->quantity }} paket)</td>
                <td>{{ $inv->buyer?->name }}</td>
                <td>Rp {{ number_format($inv->amount, 0, ',', '.') }}</td>
            </tr>
        @endforeach
        @foreach($deposit->collections as $collection)
            <tr>
                <td>#{{ $collection->order_id }}</td>
                <td>—</td>
                <td>Rp {{ number_format($collection->amount, 0, ',', '.') }}</td>
            </tr>
        @endforeach
    </x-ui.table>
    </div>
</div>
