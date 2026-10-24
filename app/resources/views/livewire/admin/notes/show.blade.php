<div class="adm-page">
    <x-admin.page-header :title="'Nota '.$note->number()">
        {{ $note->line->label() }} · {{ $note->created_at->wib()->locale('id')->translatedFormat('l, j F Y H.i') }}
        <x-slot:actions><a class="btn btn-secondary" href="{{ route('admin.notes.index') }}">Semua nota</a></x-slot:actions>
    </x-admin.page-header>

    <div class="note-admin-grid">
        <x-admin.panel title="Barang">
            <x-admin.list cols="minmax(0,2fr) 70px 110px 130px" :headers="['Barang', 'Jml', 'Harga', 'Subtotal']">
                @foreach($note->items as $item)
                    <x-admin.list-row wire:key="ni-{{ $item->id }}">
                        <span class="adm-cell-main">{{ $item->product_name }}</span>
                        <span class="adm-num">{{ $item->qtyLabel() }}</span>
                        <span class="adm-num">{{ number_format($item->price, 0, ',', '.') }}</span>
                        <span class="adm-num">{{ number_format($item->subtotal, 0, ',', '.') }}</span>
                    </x-admin.list-row>
                @endforeach
            </x-admin.list>
            <p class="note-admin-total">Total <b>Rp {{ number_format($note->total, 0, ',', '.') }}</b></p>
        </x-admin.panel>

        <x-admin.panel title="Pembayaran">
            <dl class="note-admin-facts">
                <dt>Toko</dt><dd><a href="{{ route('admin.buyers.edit', $note->buyer) }}">{{ $note->buyer?->name }}</a></dd>
                <dt>Sales</dt><dd>{{ $note->salesUser?->name }}</dd>
                <dt>Dibayar</dt><dd>{{ $note->payment_method->label() }}</dd>
                @if($note->payment_method->value === 'cash')
                    <dt>Setoran</dt><dd>{{ $note->deposit ? 'Setoran #'.$note->deposit->id.' · '.$note->deposit->status->label() : 'Belum disetor' }}</dd>
                @else
                    <dt>Bukti</dt><dd><x-admin.pill :tone="$note->proof_status->tone()">{{ $note->proof_status->label() }}</x-admin.pill></dd>
                    @if($note->reviewer)<dt>Diperiksa</dt><dd>{{ $note->reviewer->name }} · {{ $note->proof_reviewed_at->wib()->locale('id')->translatedFormat('j M H.i') }}</dd>@endif
                    @if($note->proof_note)<dt>Alasan</dt><dd>{{ $note->proof_note }}</dd>@endif
                @endif
            </dl>

            @if($note->proofUrl())
                <a href="{{ $note->proofUrl() }}" target="_blank" rel="noopener" class="note-proof"><img src="{{ $note->proofUrl() }}" alt="Bukti {{ $note->payment_method->label() }}" loading="lazy"></a>
            @endif

            @if($note->proof_status?->value === 'pending')
                <div class="att-actions">
                    <x-ui.button variant="primary" wire:click="verify"><x-lucide-check class="ico" />Verifikasi</x-ui.button>
                </div>
                <form wire:submit="reject" class="att-form note-reject">
                    <x-ui.input label="Tolak bukti — alasan" wire:model="reason" placeholder="Mis. nominal di bukti tidak sesuai" />
                    @error('reason')<p class="text-danger">{{ $message }}</p>@enderror
                    @error('proof')<p class="text-danger">{{ $message }}</p>@enderror
                    <div class="att-actions"><x-ui.save-button target="reason" variant="secondary">Tolak bukti</x-ui.save-button></div>
                </form>
            @endif
        </x-admin.panel>
    </div>
</div>
