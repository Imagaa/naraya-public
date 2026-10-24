<div class="adm-page adm-detail">
    <a href="{{ route('admin.complaints.index') }}" class="adm-back"><x-lucide-arrow-left width="16" height="16" /> Komplain</a>
    <x-admin.page-header :title="'Komplain #'.$complaint->id">Order #{{ $complaint->order_id }}</x-admin.page-header>

    <div class="adm-detail-main">
    <x-ui.card title="Detail Komplain">
        <p><strong>User:</strong> {{ $complaint->order->buyer->name }}</p>
        <p><strong>Tipe:</strong> {{ $complaint->tipe->label() }}</p>
        <p><strong>Alasan:</strong> {{ $complaint->alasan }}</p>
        @if($complaint->foto_path)
            <p><a href="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($complaint->foto_path) }}" target="_blank">Lihat Foto</a></p>
        @endif
        <p><strong>Status:</strong> <x-ui.tag variant="accent">{{ $complaint->status->label() }}</x-ui.tag></p>
    </x-ui.card>
    </div>

    <div class="adm-detail-side">
    @if($complaint->status->value === 'diajukan' && $complaint->tipe->value === 'komplain_pasca_selesai')
        <x-ui.card title="Putuskan Komplain">
            <div style="display:flex;flex-direction:column;gap:12px">
                <select class="input" wire:model="resolusi">
                    <option value="">-- Pilih Resolusi --</option>
                    <option value="ganti_barang">Ganti Barang</option>
                    <option value="refund">Refund</option>
                    <option value="potongan_order_berikutnya">Potongan Order Berikutnya</option>
                </select>
                @error('resolusi') <span style="color:var(--color-danger);font-size:12px">{{ $message }}</span> @enderror

                <x-ui.input label="Nominal Penyesuaian (Rp, opsional kecuali potongan)" wire:model="nominalPenyesuaian" />
                @error('nominalPenyesuaian') <span style="color:var(--color-danger);font-size:12px">{{ $message }}</span> @enderror

                <x-ui.input label="Catatan Admin" wire:model="catatanAdmin" />
                @error('catatanAdmin') <span style="color:var(--color-danger);font-size:12px">{{ $message }}</span> @enderror

                <div style="display:flex;gap:8px">
                    <x-ui.button variant="primary" wire:click="approve">Setujui</x-ui.button>
                    <x-ui.button variant="secondary" wire:click="reject">Tolak</x-ui.button>
                </div>
            </div>
        </x-ui.card>
    @endif
    </div>
</div>
