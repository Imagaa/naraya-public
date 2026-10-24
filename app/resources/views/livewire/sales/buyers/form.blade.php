<x-sales.page title="Toko Baru" back="1">
    @if($savedId)
        <x-ui.card title="Toko tersimpan">
            <div class="store-account">
                <p class="store-account-state"><b>{{ $name }}</b> sudah tercatat dan bisa langsung dibuatkan nota. Akun untuk masuk ke Naraya bisa dibuat sekarang atau nanti dari halaman toko.</p>
                <div class="store-account-actions">
                    <button type="button" class="btn btn-primary" x-data x-on:click="$dispatch('sheet-open', { sheet: 'toko', for: {{ $savedId }}, akun: true })">Buatkan akun sekarang</button>
                    <button type="button" class="btn btn-secondary" x-data x-on:click="$dispatch('sheet-done', { message: 'Toko {{ e(addslashes($name)) }} tersimpan.' })">Nanti saja</button>
                </div>
            </div>
        </x-ui.card>
    @else
        <x-ui.card>
            <form wire:submit="save" class="sales-form">
                <x-ui.input label="Nama Toko" wire:model="name" />
                <x-ui.field-error name="name" />
                <x-ui.input label="Nama Pemilik" wire:model="ownerName" />
                <x-ui.field-error name="ownerName" />
                <x-ui.input label="Nomor HP/WhatsApp" wire:model="phone" inputmode="tel" />
                <x-ui.field-error name="phone" />

                {{-- Sales are standing at the store: start the pin on the device's GPS position. --}}
                <div x-data="{ lat: @entangle('lat'), lng: @entangle('lng'), locate() { navigator.geolocation?.getCurrentPosition(p => window.dispatchEvent(new CustomEvent('pinpoint-locate', { detail: { lat: p.coords.latitude, lng: p.coords.longitude } }))) } }" x-init="if (! lat) locate()">
                    <x-ui.button type="button" variant="secondary" x-on:click="locate()">Gunakan lokasi saya</x-ui.button>
                    <x-map.pinpoint-picker wire-lat="lat" wire-lng="lng" />
                </div>
                <x-ui.field-error name="lat" />

                <div class="field" x-data="addressFromPin('address')" x-on:pinpoint-moved.window="moved($event.detail.lat, $event.detail.lng)">
                    <label for="store-address">Alamat Toko</label>
                    <textarea id="store-address" class="input" rows="2" wire:model="address" x-on:input="touched = $event.target.value !== ''" placeholder="Terisi otomatis dari titik lokasi"></textarea>
                    <small class="store-account-hint" x-show="busy" x-cloak>Mencari alamat dari titik lokasi…</small>
                </div>
                <x-ui.field-error name="address" />

                <div class="field">
                    <label for="store-photo">Foto Depan Warung</label>
                    <x-ui.file-picker id="store-photo" capture="environment" wire:model="storePhoto" :file="$storePhoto" label="Ambil foto toko" hint="Foto langsung bagian depan toko. Maks. 5 MB." />
                </div>
                <x-ui.field-error name="storePhoto" />

                <p class="sales-note">Akun untuk masuk (nomor HP + PIN atau email) bisa dibuat setelah toko tersimpan, sekarang atau nanti.</p>
                <x-ui.save-button target="name,ownerName,phone,address,lat,lng" :changed="$storePhoto !== null">Simpan</x-ui.save-button>
            </form>
        </x-ui.card>
    @endif
</x-sales.page>
