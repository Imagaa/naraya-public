<div class="adm-page adm-settings">
    <x-admin.settings-tabs />
    <x-admin.page-header title="Point">Berapa pcs mainan Naraya Toys yang terjual di toko untuk mendapat 1 point.</x-admin.page-header>
    @if($saved)<p class="adm-flash" role="status"><x-lucide-check width="16" height="16" />Tersimpan.</p>@endif

    <x-admin.panel title="Perolehan point">
        <form wire:submit="save" class="att-form">
            <x-ui.input label="Pcs mainan per point" type="number" min="1" wire:model="pcsPerPoint">
                <p class="field-hint">Mis. 150 = setiap 150 pcs mainan terjual di toko dapat 1 point.</p>
                <p class="field-hint">Mengubah angka ini menghitung ulang poin semua toko.</p>
            </x-ui.input>
            @error('pcsPerPoint')<p class="text-danger">{{ $message }}</p>@enderror
            <div class="att-actions"><x-ui.save-button>Simpan</x-ui.save-button></div>
        </form>
    </x-admin.panel>
</div>
