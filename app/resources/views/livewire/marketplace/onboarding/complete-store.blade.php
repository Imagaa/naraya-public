<div class="page">
    <main class="container pg onboard">
        <section class="panel onboard-card" aria-labelledby="h-store">
            <span class="tile"><x-lucide-store class="ico" /></span>
            <h1 id="h-store">Lengkapi data toko</h1>
            <p>Sales kami butuh foto depan dan titik lokasi toko Anda untuk mengantar pesanan. Langkah ini wajib sebelum mulai berbelanja.</p>

            <form wire:submit="save" class="stack">
                <div class="field">
                    <label for="cs-photo">Foto depan warung</label>
                    <x-ui.file-picker id="cs-photo" capture="environment" wire:model="storePhoto" :file="$storePhoto" :current="$currentPhotoUrl" label="Ambil atau pilih foto" hint="Maks. 5 MB" />
                    <span class="hint">Bagian depan toko, papan nama atau etalase terlihat jelas.</span>
                    @error('storePhoto')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div x-data="{ lat: @entangle('lat'), lng: @entangle('lng') }" class="stack">
                    <button type="button" class="btn btn-secondary btn-sm" x-on:click="navigator.geolocation?.getCurrentPosition(p => window.dispatchEvent(new CustomEvent('pinpoint-locate', { detail: { lat: p.coords.latitude, lng: p.coords.longitude } })))"><x-lucide-locate-fixed class="ico" />Gunakan lokasi saya</button>
                    <x-map.pinpoint-picker wire-lat="lat" wire-lng="lng" />
                    @error('lat')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="storePhoto,save">Simpan dan mulai belanja</button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-secondary btn-sm">Keluar</button>
            </form>
        </section>
    </main>
</div>
