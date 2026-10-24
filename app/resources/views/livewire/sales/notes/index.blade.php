<x-sales.page :title="'Nota saya · '.$line->label()" back="1">

    <div class="sales-list">
        @forelse($notes as $note)
            <x-sales.row as="button" type="button" x-data x-on:click="$dispatch('sheet-open', { sheet: 'nota-detail', for: {{ $note->id }} })" wire:key="note-{{ $note->id }}">
                <b>{{ $note->buyer?->name }}</b>
                <x-slot:meta>{{ $note->number() }} · {{ $note->created_at->wib()->format('d/m/Y H:i') }} · {{ $note->payment_method->label() }}</x-slot:meta>
                <x-slot:trail>
                    <span class="note-row-trail">
                        <b>Rp {{ number_format($note->total, 0, ',', '.') }}</b>
                        @if($note->proof_status)<x-ui.tag :variant="$note->proof_status->salesVariant()">{{ $note->proof_status->label() }}</x-ui.tag>@endif
                    </span>
                </x-slot:trail>
            </x-sales.row>
        @empty
            <x-ui.empty-state message="Belum ada nota di lini ini." />
        @endforelse
    </div>
</x-sales.page>
