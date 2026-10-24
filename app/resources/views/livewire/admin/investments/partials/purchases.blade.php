<x-admin.list cols="100px minmax(0,1.4fr) 70px 130px minmax(0,1.5fr) 150px 170px" :headers="['Pembelian', 'Toko', 'Paket', 'Nilai', 'Dibayar', 'Status', '']" :empty="$rows->isEmpty()" :empty-message="$empty">
    @foreach($rows as $p)
        <x-admin.list-row wire:key="ip-{{ $p->id }}">
            <span class="adm-num"><b>{{ $p->number() }}</b></span>
            <span class="adm-cell-main">{{ $p->buyer?->name }}<span class="adm-cell-sub">dari {{ ['naraya' => 'Naraya', 'toys' => 'Naraya Toys', 'sales' => 'sales'][$p->source] ?? $p->source }}</span></span>
            <span class="adm-num">{{ $p->quantity }}</span>
            <span class="adm-num">{{ $rp($p->amount) }}</span>
            <span>
                {{ $p->created_at->wib()->format('d/m H.i') }} · {{ $p->methodLabel() }}{{ $p->salesUser ? ' ('.$p->salesUser->name.')' : '' }}
                @if($p->proofUrl()) · <a href="{{ $p->proofUrl() }}" target="_blank" rel="noopener">bukti</a>@endif
                @if($p->partner_ref)<span class="adm-cell-sub">Ref. mitra {{ $p->partner_ref }}</span>@endif
                @if($p->reject_reason)<span class="adm-cell-sub">Ditolak: {{ $p->reject_reason }}</span>@endif
            </span>
            <span><x-admin.pill :tone="$ptone[$p->status] ?? 'muted'">{{ $p->status === 'dibayar' ? 'Belum disalurkan' : $p->statusLabel() }}</x-admin.pill>
                @if($p->status === 'aktif')<span class="adm-cell-sub">bulan {{ $p->months_paid }}/{{ $p->months }}</span>@endif</span>
            <span class="adm-cell-actions">
                @if($p->status === 'menunggu_verifikasi')
                    <x-ui.button variant="primary" wire:click="verify({{ $p->id }})">Verifikasi</x-ui.button>
                    <x-admin.row-menu :id="'ip-menu-'.$p->id">
                        <button type="button" class="adm-menu-item is-danger" role="menuitem" wire:click="startAct('reject', {{ $p->id }})"><x-lucide-x />Tolak bukti</button>
                    </x-admin.row-menu>
                @elseif($p->status === 'dibayar')
                    <x-ui.button variant="primary" wire:click="startAct('activate', {{ $p->id }})">Tandai disalurkan</x-ui.button>
                @endif
            </span>
        </x-admin.list-row>
        @if($actingId === $p->id && in_array($acting, ['activate', 'reject'], true))
            <form wire:submit="{{ $acting }}" class="att-form adm-list-extra">
                @if($acting === 'activate')
                    <x-ui.input label="Nomor referensi di platform mitra (opsional)" wire:model="partnerRef" />
                    <p class="adm-muted inv-adm-hint">Paket aktif hari ini; pendapatan pertama masuk dompet toko sebulan lagi.</p>
                @else
                    <x-ui.input label="Alasan penolakan (terlihat oleh toko)" wire:model="reason" placeholder="Mis. nominal di bukti kurang" />
                    @error('reason')<p class="text-danger">{{ $message }}</p>@enderror
                @endif
                @error('purchase')<p class="text-danger">{{ $message }}</p>@enderror
                <div class="att-actions">
                    <x-ui.button variant="secondary" wire:click="stopAct">Batal</x-ui.button>
                    <x-ui.button type="submit" variant="primary">{{ $acting === 'activate' ? 'Tandai disalurkan' : 'Tolak bukti' }}</x-ui.button>
                </div>
            </form>
        @endif
    @endforeach
</x-admin.list>
