<div class="adm-page adm-detail">
    <a href="{{ route('admin.buyers.index') }}" class="adm-back"><x-lucide-arrow-left width="16" height="16" /> Toko</a>
    <x-admin.page-header :title="$buyer?->exists ? $buyer->name : 'Tambah toko'">{{ $buyer?->exists ? ($buyer->buyerProfile?->owner_name ? 'Pemilik '.$buyer->buyerProfile->owner_name : '') : '' }}</x-admin.page-header>

    <div class="adm-detail-main">

    @if($claim)
        <x-ui.card title="Permintaan akun">
            <div style="display:flex;flex-direction:column;gap:12px">
                <p class="text-muted" style="margin:0;font-size:13px">Pemilik mendaftar di website dengan nomor HP toko ini, yang sudah didaftarkan sales <b>{{ $buyer->buyerProfile->salesUser?->name }}</b>. Data toko tetap dari sales; yang diminta hanya akun untuk masuk: <b>{{ $buyer->loginLabel() }}</b>. Pastikan dengan sales bahwa ini memang pemiliknya.</p>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <x-ui.button type="button" variant="primary" wire:click="approve">Setujui akun</x-ui.button>
                    <x-ui.confirm-button variant="secondary" action="reject" yes="Ya, tolak" message="Tolak permintaan akun ini? Toko tetap ada, tanpa akun.">Tolak</x-ui.confirm-button>
                </div>
            </div>
        </x-ui.card>
    @elseif($pending)
        <x-ui.card title="Persetujuan pendaftaran">
            <div style="display:flex;flex-direction:column;gap:12px">
                <p class="text-muted" style="margin:0;font-size:13px">Toko ini mendaftar sendiri. Periksa foto, alamat dan titik lokasinya sebelum menyetujui.</p>
                @if($storePhotoUrl)
                    <img src="{{ $storePhotoUrl }}" alt="Foto depan {{ $buyer->name }}" style="width:100%;max-height:240px;object-fit:cover;border-radius:8px">
                @endif
                <div style="font-size:14px;line-height:1.6">
                    <b>{{ $buyer->name }}</b> · pemilik {{ $buyer->buyerProfile->owner_name }}<br>
                    {{ $buyer->buyerProfile->phone }} · masuk dengan {{ $buyer->loginLabel() ?? '—' }}<br>
                    {{ $buyer->buyerProfile->address }}<br>
                    Area: {{ $buyer->buyerProfile->area?->name ?? 'tidak terdeteksi' }}
                    @if($lat && $lng)
                        · <a href="https://www.google.com/maps?q={{ $lat }},{{ $lng }}" target="_blank" rel="noopener">lihat titik di peta</a>
                    @endif
                </div>

                <div class="field">
                    <label>Sales penanggung jawab</label>
                    <select class="input" wire:model="approveSalesId">
                        <option value="">{{ $areaSales ? 'Otomatis: '.$areaSales->name.' (sales area)' : 'Belum ada sales aktif di area ini' }}</option>
                        @foreach($salesUsers as $sales)
                            <option value="{{ $sales->id }}">{{ $sales->name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-ui.button type="button" variant="primary" wire:click="approve">Setujui</x-ui.button>

                <div class="field">
                    <label>Alasan penolakan</label>
                    <textarea class="input" rows="2" wire:model="rejectionReason" placeholder="Mis. lokasi di luar jangkauan pengiriman"></textarea>
                </div>
                @error('rejectionReason') <span style="color:var(--color-danger);font-size:12px">{{ $message }}</span> @enderror
                <x-ui.button type="button" variant="secondary" wire:click="reject">Tolak</x-ui.button>
            </div>
        </x-ui.card>
    @endif

        <x-ui.card>
            <form wire:submit="save" style="display:flex;flex-direction:column;gap:12px">
                <x-ui.input label="Nama Toko" wire:model="name" />
                @error('name') <span style="color:var(--color-danger);font-size:12px">{{ $message }}</span> @enderror
                <x-ui.input label="Nama Pemilik" wire:model="ownerName" />
                @error('ownerName') <span style="color:var(--color-danger);font-size:12px">{{ $message }}</span> @enderror
                <x-ui.input label="Nomor HP/WhatsApp" wire:model="phone" inputmode="tel" />
                @error('phone') <span style="color:var(--color-danger);font-size:12px">{{ $message }}</span> @enderror
                <x-ui.input label="Alamat Toko" wire:model="address" />
                @error('address') <span style="color:var(--color-danger);font-size:12px">{{ $message }}</span> @enderror

                <div class="field">
                    <label>Foto Depan Warung</label>
                    <x-ui.file-picker wire:model="storePhoto" :file="$storePhoto" :current="$storePhotoUrl" hint="Bagian depan toko, papan nama/etalase terlihat jelas. Maks. 5 MB." />
                </div>
                @error('storePhoto') <span style="color:var(--color-danger);font-size:12px">{{ $message }}</span> @enderror

                <div x-data="{ lat: @entangle('lat'), lng: @entangle('lng') }">
                    <x-map.pinpoint-picker wire-lat="lat" wire-lng="lng" />
                </div>
                @error('lat') <span style="color:var(--color-danger);font-size:12px">{{ $message }}</span> @enderror

                <div class="field">
                    <label>Sales Penanggung Jawab</label>
                    <select class="input" wire:model="salesUserId">
                        <option value="">{{ $buyer?->exists ? '— Belum ditentukan —' : 'Otomatis: sales area' }}</option>
                        @foreach($salesUsers as $sales)
                            <option value="{{ $sales->id }}">{{ $sales->name }}</option>
                        @endforeach
                    </select>
                </div>

                @unless($buyer?->exists)
                    <p class="text-muted" style="margin:0;font-size:13px">Akun untuk masuk (nomor HP + PIN atau email) dibuat setelah toko tersimpan, di panel Akun toko.</p>
                @endunless

                <x-ui.save-button target="name,ownerName,phone,address,salesUserId,isActive,lat,lng" :changed="$storePhoto !== null">Simpan</x-ui.save-button>
            </form>
        </x-ui.card>
    </div>

    @if($buyer?->exists)
        <div class="adm-detail-side">
            <livewire:store-account :store-id="$buyer->id" :open="request()->boolean('akun')" />

            <x-ui.card title="Stok di toko">
                @include('livewire.admin.buyers.partials.stock', ['stock' => \App\Support\Sales\Consignments::valuation($buyer->id)])
            </x-ui.card>

        </div>

        <div class="adm-span">
            <x-ui.card title="Sales terkait">
                <x-admin.list cols="minmax(0,1.6fr) 130px 120px 150px" :headers="['Sales', 'Peran', 'Kunjungan 90 hari', 'Terakhir']" :empty="$relatedSales->isEmpty()" empty-message="Belum ada sales yang ditugaskan atau berkunjung.">
                    @foreach($relatedSales as $r)
                        <x-admin.list-row :href="route('admin.sales.edit', $r['user'])" wire:key="rs-{{ $r['user']->id }}">
                            <span class="adm-cell-main adm-with-avatar"><x-ui.person-avatar :user="$r['user']" :size="26" />{{ $r['user']->name }}</span>
                            <span><x-admin.pill :tone="$r['assigned'] ? 'ok' : 'muted'">{{ $r['assigned'] ? 'Ditugaskan' : 'Pernah berkunjung' }}</x-admin.pill></span>
                            <span class="adm-num">{{ $r['visits'] }} kunjungan</span>
                            <span class="adm-num">{{ $r['last'] ? $r['last']->wib()->locale('id')->translatedFormat('j M Y H.i') : '—' }}</span>
                        </x-admin.list-row>
                    @endforeach
                </x-admin.list>
            </x-ui.card>
            <x-ui.card title="Riwayat nota">
                <x-admin.list cols="120px 70px 120px minmax(0,1fr) 120px" :headers="['No.', 'Lini', 'Tanggal', 'Sales', 'Total']" :empty="$notes->isEmpty()" empty-message="Belum ada nota untuk toko ini.">
                    @foreach($notes as $note)
                        <x-admin.list-row :href="route('admin.notes.show', $note)" wire:key="bn-{{ $note->id }}">
                            <span class="adm-num"><b>{{ $note->number() }}</b></span>
                            <span>{{ $note->line->label() }}</span>
                            <span class="adm-num">{{ $note->created_at->wib()->format('d/m/Y') }}</span>
                            <span>{{ $note->salesUser?->name }}</span>
                            <span class="adm-num">Rp {{ number_format($note->total, 0, ',', '.') }}</span>
                        </x-admin.list-row>
                    @endforeach
                </x-admin.list>
            </x-ui.card>
        </div>
    @endif
</div>
