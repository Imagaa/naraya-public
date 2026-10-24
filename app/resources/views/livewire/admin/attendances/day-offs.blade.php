<div class="adm-page">
    <x-admin.page-header title="Libur sales">
        Libur mingguan yang diajukan sales sendiri (1x per minggu Senin–Minggu, paling lambat H-1, langsung berlaku).
        <x-slot:actions>
            <a class="btn btn-secondary" href="{{ route('admin.attendances.index') }}">Absensi harian</a>
            <a class="btn btn-secondary" href="{{ route('admin.attendances.recap') }}">Rekap</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="adm-toolbar recon-toolbar">
        <select class="input" wire:model.live="salesId" aria-label="Sales">
            <option value="">Semua sales</option>
            @foreach($salesPeople as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
        </select>
        <label class="field">Minggu dari tanggal <input type="date" class="input" wire:model.live="week"></label>
    </div>

    <x-admin.list cols="170px minmax(0,1.4fr) minmax(0,1.6fr) 140px 150px" :headers="['Tanggal libur', 'Sales', 'Keperluan', 'Diajukan', '']" :empty="$dayOffs->isEmpty()" empty-message="Belum ada libur di filter ini.">
        @foreach($dayOffs as $off)
            <x-admin.list-row wire:key="do-{{ $off->id }}">
                <span class="adm-cell-main">{{ $off->date->locale('id')->translatedFormat('D, j M Y') }}</span>
                <span class="adm-with-avatar"><x-ui.person-avatar :user="$off->salesUser" :size="26" />{{ $off->salesUser?->name }}</span>
                <span>
                    {{ $off->note ?? '—' }}
                    @if($off->cancelled_at)<span class="adm-cell-sub">Dibatalkan {{ $off->canceller?->name }}{{ $off->cancel_reason ? ': '.$off->cancel_reason : '' }}</span>@endif
                </span>
                <span class="adm-num">{{ $off->created_at->wib()->format('d/m H.i') }}</span>
                <span>
                    @if($off->cancelled_at)
                        <x-admin.pill tone="muted">Dibatalkan</x-admin.pill>
                    @elseif($cancellingId === $off->id)
                        <x-ui.button variant="secondary" wire:click="stopCancel">Tutup</x-ui.button>
                    @else
                        <x-ui.button variant="secondary" wire:click="startCancel({{ $off->id }})">Batalkan</x-ui.button>
                    @endif
                </span>
            </x-admin.list-row>
            @if($cancellingId === $off->id)
                <form wire:submit="cancel" class="att-form adm-list-extra">
                    <x-ui.input label="Alasan pembatalan (dikirim ke riwayat sales)" wire:model="cancelReason" placeholder="Mis. ada stok opname hari itu" />
                    @error('cancelReason')<p class="text-danger">{{ $message }}</p>@enderror
                    <div class="att-actions"><x-ui.save-button target="cancelReason">Batalkan libur</x-ui.save-button></div>
                </form>
            @endif
        @endforeach
    </x-admin.list>
    {{ $dayOffs->links() }}
</div>
