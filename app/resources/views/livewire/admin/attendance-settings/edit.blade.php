<div class="adm-page adm-settings">
    <x-admin.settings-tabs />
    <x-admin.page-header title="Absensi">Hari kerja, lokasi absen, hari libur dan akurasi GPS. Sejak 2026-10-12 sales boleh absen kapan saja: jam di jadwal hanya menandai hari kerja, tidak ada status terlambat.</x-admin.page-header>
    <x-admin.filter-pills :options="['jadwal' => ['Jadwal', null], 'lokasi' => ['Lokasi', null], 'libur' => ['Hari libur', null], 'aturan' => ['Aturan', null]]" :active="$tab" action="setTab" />
    @if($flash)<p class="adm-flash" role="status"><x-lucide-check width="16" height="16" />{{ $flash }}</p>@endif

    @if($tab === 'jadwal')
        <x-admin.panel title="Jadwal umum">
            <form wire:submit="saveGeneral" class="att-form" x-data>
                <div class="att-week">
                    @foreach($days as $d => $name)
                        <div class="att-day" wire:key="umum-{{ $d }}">
                            <span class="att-day-name">{{ $name }}</span>
                            <x-ui.checkbox label="Libur" wire:model="umum.{{ $d }}.libur" />
                            <input type="time" class="input" wire:model="umum.{{ $d }}.masuk" aria-label="Jam masuk {{ $name }}" x-bind:disabled="$wire.umum[{{ $d }}].libur">
                            <input type="time" class="input" wire:model="umum.{{ $d }}.pulang" aria-label="Jam pulang {{ $name }}" x-bind:disabled="$wire.umum[{{ $d }}].libur">
                            @error("umum.$d")<p class="text-danger">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
                <div class="att-actions"><x-ui.save-button target="umum">Simpan jadwal umum</x-ui.save-button></div>
            </form>
        </x-admin.panel>

        <x-admin.panel title="Jadwal khusus per sales">
            <x-slot:actions><x-ui.button variant="secondary" wire:click="editOverride"><x-lucide-plus class="ico" />Tambah jadwal khusus</x-ui.button></x-slot:actions>
            <p class="adm-muted">Sales dengan jadwal khusus memakai jadwalnya sendiri untuk semua hari, bukan jadwal umum.</p>
            @forelse($overrides as $o)
                <div class="att-row" wire:key="ovr-{{ $o->id }}">
                    <span>{{ $o->name }}</span>
                    <span class="adm-cell-actions">
                        <x-ui.button variant="secondary" wire:click="editOverride({{ $o->id }})"><x-lucide-pencil class="ico" />Ubah</x-ui.button>
                        <x-admin.row-menu :id="'ovr-menu-'.$o->id">
                            <button type="button" class="adm-menu-item is-danger" role="menuitem" wire:click="deleteOverride({{ $o->id }})" wire:confirm="Hapus jadwal khusus {{ $o->name }}? Sales ini kembali ikut jadwal umum."><x-lucide-trash-2 />Hapus jadwal khusus</button>
                        </x-admin.row-menu>
                    </span>
                </div>
            @empty
                <p class="adm-muted">Belum ada. Semua sales ikut jadwal umum.</p>
            @endforelse

            @if($editingOverride)
                <form wire:submit="saveOverride" class="att-form att-subform" x-data wire:key="ovr-form">
                    <div class="field">
                        <label for="ovr-sales">Sales</label>
                        <select id="ovr-sales" class="input" wire:model="overrideUserId">
                            <option value="">Pilih sales…</option>
                            @foreach($sales as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                        </select>
                        @error('overrideUserId')<p class="text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div class="att-week">
                        @foreach($days as $d => $name)
                            <div class="att-day" wire:key="khusus-{{ $d }}">
                                <span class="att-day-name">{{ $name }}</span>
                                <x-ui.checkbox label="Libur" wire:model="khusus.{{ $d }}.libur" />
                                <input type="time" class="input" wire:model="khusus.{{ $d }}.masuk" aria-label="Jam masuk {{ $name }}" x-bind:disabled="$wire.khusus[{{ $d }}].libur">
                                <input type="time" class="input" wire:model="khusus.{{ $d }}.pulang" aria-label="Jam pulang {{ $name }}" x-bind:disabled="$wire.khusus[{{ $d }}].libur">
                                @error("khusus.$d")<p class="text-danger">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </div>
                    <div class="att-actions">
                        <x-ui.button variant="secondary" wire:click="$set('editingOverride', false)">Batal</x-ui.button>
                        <x-ui.save-button target="overrideUserId,khusus">Simpan jadwal khusus</x-ui.save-button>
                    </div>
                </form>
            @endif
        </x-admin.panel>
    @endif

    @if($tab === 'lokasi')
        @if($withoutLocation->isNotEmpty())
            <div class="adm-alert"><p>Belum punya lokasi absen (tidak bisa absen): {{ $withoutLocation->join(', ') }}</p></div>
        @endif
        <x-admin.panel title="Lokasi absen">
            <x-slot:actions><x-ui.button variant="secondary" wire:click="newLocation"><x-lucide-plus class="ico" />Tambah lokasi</x-ui.button></x-slot:actions>
            @forelse($locations as $l)
                <div class="att-row" wire:key="loc-{{ $l->id }}">
                    <span>
                        <b>{{ $l->nama }}</b>
                        @unless($l->is_active)<x-admin.pill>Nonaktif</x-admin.pill>@endunless
                        <span class="adm-cell-sub">Radius {{ $l->radius_m }} m · {{ $l->users->isEmpty() ? 'Semua sales' : $l->users->pluck('name')->join(', ') }}</span>
                    </span>
                    <span class="adm-cell-actions">
                        <x-ui.button variant="secondary" wire:click="editLocation({{ $l->id }})"><x-lucide-pencil class="ico" />Ubah</x-ui.button>
                        <x-admin.row-menu :id="'loc-menu-'.$l->id">
                            <button type="button" class="adm-menu-item" role="menuitem" wire:click="toggleLocation({{ $l->id }})"><x-lucide-power />{{ $l->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                            <button type="button" class="adm-menu-item is-danger" role="menuitem" wire:click="deleteLocation({{ $l->id }})" wire:confirm="Hapus lokasi {{ $l->nama }}?"><x-lucide-trash-2 />Hapus lokasi</button>
                        </x-admin.row-menu>
                    </span>
                </div>
            @empty
                <p class="adm-muted">Belum ada lokasi. Sales tidak bisa absen sampai ada minimal satu lokasi.</p>
            @endforelse

            @if($editingLocation)
                <form wire:submit="saveLocation" class="att-form att-subform" wire:key="loc-form-{{ $formKey }}"
                      x-data="{ lat: @entangle('lat'), lng: @entangle('lng') }"
                      x-init="$nextTick(() => $dispatch('pinpoint-radius', {{ $radius }}))">
                    <h3 class="att-subtitle">{{ $locationId ? 'Ubah lokasi' : 'Lokasi baru' }}</h3>
                    <x-ui.input label="Nama lokasi" wire:model="locNama" placeholder="Mis. Kantor Pusat" />
                    @error('locNama')<p class="text-danger">{{ $message }}</p>@enderror

                    <x-map.pinpoint-picker wire-lat="lat" wire-lng="lng" />
                    @error('lat')<p class="text-danger">{{ $message }}</p>@enderror
                    <div><x-ui.button variant="secondary" class="btn-sm" x-on:click="navigator.geolocation?.getCurrentPosition((p) => $dispatch('pinpoint-locate', { lat: p.coords.latitude, lng: p.coords.longitude }))"><x-lucide-locate-fixed class="ico" />Pakai lokasi saya</x-ui.button></div>

                    <x-ui.input label="Radius (meter)" type="number" min="1" max="1000" step="1" inputmode="numeric" wire:model="radius" x-on:input="$dispatch('pinpoint-radius', +$event.target.value)">
                        <p class="field-hint">1–1000 m. Akurasi GPS HP biasanya 5–20 m, jadi radius yang sangat kecil bisa membuat sales sulit absen.</p>
                    </x-ui.input>
                    @error('radius')<p class="text-danger">{{ $message }}</p>@enderror

                    <fieldset class="check-group">
                        <legend>Berlaku untuk <span class="adm-muted">(kosongkan = semua sales)</span></legend>
                        @foreach($sales as $s)
                            <x-ui.checkbox chip :label="$s->name" value="{{ $s->id }}" wire:model="locSalesIds" wire:key="loc-sales-{{ $s->id }}" />
                        @endforeach
                    </fieldset>

                    <x-ui.checkbox switch label="Lokasi aktif" wire:model="locActive" />

                    <div class="att-actions">
                        <x-ui.button variant="secondary" wire:click="$set('editingLocation', false)">Batal</x-ui.button>
                        <x-ui.save-button target="locNama,lat,lng,radius,locSalesIds,locActive">Simpan lokasi</x-ui.save-button>
                    </div>
                </form>
            @endif
        </x-admin.panel>
    @endif

    @if($tab === 'libur')
        <x-admin.panel title="Hari libur">
            <p class="adm-muted">Libur mingguan diatur di Jadwal (centang Libur). Di sini untuk tanggal satuan: tanggal merah, cuti bersama.</p>
            <form wire:submit="addHoliday" class="att-holiday-form">
                <x-ui.input label="Tanggal" type="date" wire:model="holidayDate" />
                <x-ui.input label="Nama libur" wire:model="holidayName" placeholder="Mis. Maulid Nabi" />
                <x-ui.save-button target="holidayDate,holidayName"><x-lucide-plus class="ico" />Tambah</x-ui.save-button>
            </form>
            @error('holidayDate')<p class="text-danger">{{ $message }}</p>@enderror
            @error('holidayName')<p class="text-danger">{{ $message }}</p>@enderror
            @foreach($holidays as $h)
                <div class="att-row" wire:key="hol-{{ $h->id }}">
                    <span><b class="adm-num">{{ $h->tanggal->locale('id')->translatedFormat('D, j M Y') }}</b> · {{ $h->nama }}</span>
                    <button type="button" class="adm-icon-btn adm-icon-btn-sm" wire:click="deleteHoliday({{ $h->id }})" wire:confirm="Hapus libur {{ $h->nama }}?" aria-label="Hapus libur {{ $h->nama }}"><x-lucide-trash-2 width="16" height="16" /></button>
                </div>
            @endforeach
        </x-admin.panel>
    @endif

    @if($tab === 'aturan')
        <x-admin.panel title="Aturan">
            <form wire:submit="saveRules" class="att-form">
                <x-ui.input label="Akurasi GPS maksimal (meter)" type="number" min="10" max="1000" wire:model="maxAkurasi">
                    <p class="field-hint">Absen dengan akurasi GPS lebih buruk dari batas ini ditolak.</p>
                </x-ui.input>
                @error('maxAkurasi')<p class="text-danger">{{ $message }}</p>@enderror
                <div class="att-actions"><x-ui.save-button target="maxAkurasi">Simpan aturan</x-ui.save-button></div>
            </form>
        </x-admin.panel>
    @endif
</div>
