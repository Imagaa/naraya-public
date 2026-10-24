<div class="adm-page adm-settings" x-data="{ lat: @entangle('lat'), lng: @entangle('lng') }">
    <x-admin.settings-tabs />
    <x-admin.page-header title="Gudang">Titik ini dipakai sebagai origin rute pengantaran sales saat lokasi GPS sales tidak tersedia.</x-admin.page-header>
    @if($saved)<p class="adm-flash" role="status"><x-lucide-check width="16" height="16" />Tersimpan.</p>@endif

    <x-admin.panel title="Lokasi gudang">
        <form wire:submit="save" class="att-form">
            <x-ui.input label="Alamat (opsional, catatan saja)" wire:model="address" />
            @error('address')<p class="text-danger">{{ $message }}</p>@enderror

            <x-map.pinpoint-picker wire-lat="lat" wire-lng="lng" />
            @error('lat')<p class="text-danger">{{ $message }}</p>@enderror

            <div class="att-actions"><x-ui.save-button>Simpan</x-ui.save-button></div>
        </form>
    </x-admin.panel>
</div>
