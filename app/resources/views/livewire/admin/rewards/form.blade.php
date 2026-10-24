<div class="adm-page adm-detail">
    <a href="{{ route('admin.rewards.index') }}" class="adm-back"><x-lucide-arrow-left width="16" height="16" /> Hadiah</a>
    <x-admin.page-header :title="$reward?->exists ? $reward->name : 'Tambah hadiah'" />

    <div class="adm-detail-main">
    <x-ui.card>
        <form wire:submit="save" style="display:flex;flex-direction:column;gap:12px">
            <x-ui.input label="Nama" wire:model="name" />
            @error('name') <span style="color:var(--color-danger);font-size:12px">{{ $message }}</span> @enderror
            <x-ui.input label="Point Dibutuhkan" type="number" wire:model="pointCost" />
            @error('pointCost') <span style="color:var(--color-danger);font-size:12px">{{ $message }}</span> @enderror
            <x-ui.input label="Stok" type="number" wire:model="stock" />
            @error('stock') <span style="color:var(--color-danger);font-size:12px">{{ $message }}</span> @enderror

            <div class="field">
                <label>Foto (opsional)</label>
                <x-ui.file-picker wire:model="foto" :file="$foto" hint="Foto hadiah di katalog" />
            </div>
            @error('foto') <span style="color:var(--color-danger);font-size:12px">{{ $message }}</span> @enderror

            <x-ui.save-button target="name,pointCost,stock,isActive" :changed="$foto !== null">Simpan</x-ui.save-button>
        </form>
    </x-ui.card>
    </div>
</div>
