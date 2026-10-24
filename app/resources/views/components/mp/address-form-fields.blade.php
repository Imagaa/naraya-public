{{--
    Shared by Checkout's "add new address" step and the account page's
    address book form — same fields (newLabel..newPostalCode on the host
    Livewire component), same cascading province/city/district/village
    picker. The action buttons differ per caller, so they're passed as
    the slot.
--}}
<div
    class="addr-form"
    x-data="{
        provinceId: @entangle('newProvinceId'),
        provinceName: @entangle('newProvinceName'),
        cityId: @entangle('newCityId'),
        cityName: @entangle('newCityName'),
        districtId: @entangle('newDistrictId'),
        districtName: @entangle('newDistrictName'),
        villageId: @entangle('newVillageId'),
        villageName: @entangle('newVillageName'),
        lat: @entangle('newLat'),
        lng: @entangle('newLng'),
        provinces: [], cities: [], districts: [], villages: [],
        base: 'https://www.emsifa.com/api-wilayah-indonesia/api/',
        /**
         * Free OSM geocoder — only used to recenter the pinpoint map onto
         * the selected village so the user isn't hunting for it on a map
         * of all Indonesia; it never sets the actual saved pin. Usage
         * policy caps this at ~1 req/sec, comfortably true here since it
         * only fires once per village selection.
         */
        async geocodeVillage() {
            const query = [this.villageName, this.districtName, this.cityName, this.provinceName, 'Indonesia']
                .filter(Boolean).join(', ');
            try {
                const res = await fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(query));
                const results = await res.json();
                if (results[0]) {
                    this.$dispatch('address-village-resolved', { lat: parseFloat(results[0].lat), lng: parseFloat(results[0].lon) });
                }
            } catch (e) {
                console.error('Gagal mencari titik area', e);
            }
        },
        async init() {
            await this.fetchList('provinces', this.base + 'provinces.json');
            if (this.provinceId) {
                await this.fetchList('cities', this.base + 'regencies/' + this.provinceId + '.json');
            }
            if (this.cityId) {
                await this.fetchList('districts', this.base + 'districts/' + this.cityId + '.json');
            }
            if (this.districtId) {
                await this.fetchList('villages', this.base + 'villages/' + this.districtId + '.json');
            }
        },
        async fetchList(key, url) {
            try {
                const res = await fetch(url);
                this[key] = await res.json();
            } catch (e) {
                console.error('Gagal memuat data wilayah', e);
            }
        },
        /* input+datalist instead of a plain select — the browser's
           native combobox filters the suggestion list to what's typed
           instead of showing everything at once, no custom dropdown
           needed. Matching the typed text against the fetched list (by
           name) is how we recover the id the API endpoints need. */
        onProvinceInput() {
            this.cityId = ''; this.cityName = ''; this.cities = [];
            this.districtId = ''; this.districtName = ''; this.districts = [];
            this.villageId = ''; this.villageName = ''; this.villages = [];
            const p = this.provinces.find(x => x.name === this.provinceName);
            this.provinceId = p ? p.id : '';
            if (this.provinceId) {
                this.fetchList('cities', this.base + 'regencies/' + this.provinceId + '.json');
            }
        },
        onCityInput() {
            this.districtId = ''; this.districtName = ''; this.districts = [];
            this.villageId = ''; this.villageName = ''; this.villages = [];
            const c = this.cities.find(x => x.name === this.cityName);
            this.cityId = c ? c.id : '';
            if (this.cityId) {
                this.fetchList('districts', this.base + 'districts/' + this.cityId + '.json');
            }
        },
        onDistrictInput() {
            this.villageId = ''; this.villageName = ''; this.villages = [];
            const d = this.districts.find(x => x.name === this.districtName);
            this.districtId = d ? d.id : '';
            if (this.districtId) {
                this.fetchList('villages', this.base + 'villages/' + this.districtId + '.json');
            }
        },
        onVillageInput() {
            const v = this.villages.find(x => x.name === this.villageName);
            this.villageId = v ? v.id : '';
            if (this.villageId) {
                this.geocodeVillage();
            }
        },
    }"
    x-init="init()"
>
    <x-ui.input label="Label alamat (opsional, mis. Rumah, Toko)" wire:model="newLabel" />

    <div class="field">
        <label>Provinsi</label>
        <input class="input" type="text" list="dl-province" autocomplete="off" x-model="provinceName" @input="onProvinceInput()" placeholder="Ketik untuk mencari...">
        <datalist id="dl-province">
            <template x-for="p in provinces" :key="p.id">
                <option :value="p.name"></option>
            </template>
        </datalist>
    </div>
    @error('newProvinceId')<p class=\"form-error\">{{ $message }}</p>@enderror

    <div class="field">
        <label>Kota/Kabupaten</label>
        <input class="input" type="text" list="dl-city" autocomplete="off" x-model="cityName" @input="onCityInput()" :disabled="!provinceId" placeholder="Ketik untuk mencari...">
        <datalist id="dl-city">
            <template x-for="c in cities" :key="c.id">
                <option :value="c.name"></option>
            </template>
        </datalist>
    </div>
    @error('newCityId')<p class=\"form-error\">{{ $message }}</p>@enderror

    <div class="field">
        <label>Kecamatan</label>
        <input class="input" type="text" list="dl-district" autocomplete="off" x-model="districtName" @input="onDistrictInput()" :disabled="!cityId" placeholder="Ketik untuk mencari...">
        <datalist id="dl-district">
            <template x-for="d in districts" :key="d.id">
                <option :value="d.name"></option>
            </template>
        </datalist>
    </div>
    @error('newDistrictId')<p class=\"form-error\">{{ $message }}</p>@enderror

    <div class="field">
        <label>Kelurahan/Desa</label>
        <input class="input" type="text" list="dl-village" autocomplete="off" x-model="villageName" @input="onVillageInput()" :disabled="!districtId" placeholder="Ketik untuk mencari...">
        <datalist id="dl-village">
            <template x-for="v in villages" :key="v.id">
                <option :value="v.name"></option>
            </template>
        </datalist>
    </div>
    @error('newVillageId')<p class=\"form-error\">{{ $message }}</p>@enderror

    <x-map.pinpoint-picker wire-lat="newLat" wire-lng="newLng" />
    @error('newLat')<p class=\"form-error\">{{ $message }}</p>@enderror

    <x-ui.input label="Detail Alamat (jalan, no. rumah, RT/RW)" wire:model="newDetail" />
    @error('newDetail')<p class=\"form-error\">{{ $message }}</p>@enderror
    <x-ui.input label="Kode Pos (opsional)" wire:model="newPostalCode" />

    {{ $slot }}
</div>
