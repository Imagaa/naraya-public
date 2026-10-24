<div class="adm-page adm-detail">
    <a href="{{ route('admin.redemptions.index') }}" class="adm-back"><x-lucide-arrow-left width="16" height="16" /> Penukaran</a>
    <x-admin.page-header :title="'Penukaran #'.$redemption->id">{{ $redemption->reward->name }}</x-admin.page-header>

    <div class="adm-detail-main">
    <x-ui.card title="Detail Penukaran">
        <p><strong>User:</strong> {{ $redemption->user->name }}</p>
        <p><strong>Hadiah:</strong> {{ $redemption->reward->name }}</p>
        <p><strong>Point:</strong> {{ $redemption->point_cost }}</p>
        <p><strong>Status:</strong> <x-ui.tag variant="accent">{{ $redemption->status->label() }}</x-ui.tag></p>
        @if($redemption->bukti_path)
            <p><strong>Bukti Serah Terima:</strong> <a href="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($redemption->bukti_path) }}" target="_blank">Lihat Foto</a></p>
        @endif
        @error('approve') <span style="color:var(--color-danger);font-size:12px">{{ $message }}</span> @enderror
    </x-ui.card>
    </div>

    <div class="adm-detail-side">
    @if($redemption->status->value === 'diajukan')
        <x-ui.card title="Putuskan Penukaran">
            <div style="display:flex;flex-direction:column;gap:12px">
                <x-ui.input label="Alasan Penolakan (wajib bila ditolak)" wire:model="alasanPenolakan" />
                @error('alasanPenolakan') <span style="color:var(--color-danger);font-size:12px">{{ $message }}</span> @enderror
                <div style="display:flex;gap:8px">
                    <x-ui.button variant="primary" wire:click="approve">Setujui</x-ui.button>
                    <x-ui.button variant="secondary" wire:click="reject">Tolak</x-ui.button>
                </div>
            </div>
        </x-ui.card>
    @endif

    @if($redemption->status->value === 'disetujui')
        <x-ui.card title="Serahkan Hadiah">
            <div style="display:flex;flex-direction:column;gap:12px">
                <div class="field">
                    <label>Foto Bukti Serah Terima</label>
                    <x-ui.file-picker wire:model="buktiFoto" :file="$buktiFoto" hint="Foto saat hadiah diterima toko" />
                </div>
                @error('buktiFoto') <span style="color:var(--color-danger);font-size:12px">{{ $message }}</span> @enderror
                <x-ui.button variant="primary" wire:click="markDelivered">Tandai Sudah Diserahkan</x-ui.button>
            </div>
        </x-ui.card>

        <x-ui.card title="Batalkan Penukaran">
            <div style="display:flex;flex-direction:column;gap:12px">
                <x-ui.input label="Alasan Pembatalan" wire:model="alasanPenolakan" />
                @error('alasanPenolakan') <span style="color:var(--color-danger);font-size:12px">{{ $message }}</span> @enderror
                <x-ui.button variant="secondary" wire:click="cancel">Batalkan (Kembalikan Point & Stok)</x-ui.button>
            </div>
        </x-ui.card>
    @endif
    </div>
</div>
