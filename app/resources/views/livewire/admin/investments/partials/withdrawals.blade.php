<x-admin.list cols="minmax(0,1.4fr) 130px minmax(0,1.6fr) 120px 140px 170px" :headers="['Toko', 'Nominal', 'Rekening', 'Diajukan', 'Status', '']" :empty="$rows->isEmpty()" :empty-message="$empty">
    @foreach($rows as $w)
        <x-admin.list-row wire:key="iw-{{ $w->id }}">
            <span class="adm-cell-main">{{ $w->buyer?->name }}</span>
            <span class="adm-num"><b>{{ $rp($w->amount) }}</b></span>
            <span>{{ $w->bank_name }} {{ $w->account_number }}<span class="adm-cell-sub">a.n. {{ $w->account_name }}</span></span>
            <span class="adm-num">{{ $w->created_at->wib()->format('d/m H.i') }}</span>
            <span><x-admin.pill :tone="$wtone[$w->status] ?? 'muted'">{{ $w->statusLabel() }}</x-admin.pill>
                @if($w->proofUrl())<a class="adm-cell-sub" href="{{ $w->proofUrl() }}" target="_blank" rel="noopener">bukti transfer</a>@endif
                @if($w->reject_reason)<span class="adm-cell-sub">{{ $w->reject_reason }}</span>@endif</span>
            <span class="adm-cell-actions">
                @if($w->status === 'menunggu')
                    <x-ui.button variant="primary" wire:click="startAct('transfer', {{ $w->id }})">Sudah ditransfer</x-ui.button>
                    <x-admin.row-menu :id="'iw-menu-'.$w->id">
                        <button type="button" class="adm-menu-item is-danger" role="menuitem" wire:click="startAct('rejectWithdrawal', {{ $w->id }})"><x-lucide-x />Tolak penarikan</button>
                    </x-admin.row-menu>
                @endif
            </span>
        </x-admin.list-row>
        @if($actingId === $w->id && in_array($acting, ['transfer', 'rejectWithdrawal'], true))
            <form wire:submit="{{ $acting }}" class="att-form adm-list-extra">
                @if($acting === 'transfer')
                    <x-ui.file-picker wire:model="transferProof" :file="$transferProof" label="Pilih foto bukti transfer" hint="Screenshot atau foto struk" />
                    @error('transferProof')<p class="text-danger">{{ $message }}</p>@enderror
                @else
                    <x-ui.input label="Alasan penolakan (dana kembali ke dompet toko)" wire:model="reason" />
                    @error('reason')<p class="text-danger">{{ $message }}</p>@enderror
                @endif
                @error('withdrawal')<p class="text-danger">{{ $message }}</p>@enderror
                <div class="att-actions">
                    <x-ui.button variant="secondary" wire:click="stopAct">Batal</x-ui.button>
                    <x-ui.button type="submit" variant="primary">{{ $acting === 'transfer' ? 'Simpan' : 'Tolak penarikan' }}</x-ui.button>
                </div>
            </form>
        @endif
    @endforeach
</x-admin.list>
