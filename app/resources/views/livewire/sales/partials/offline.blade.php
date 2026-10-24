{{--
    Field work with no signal: the outbox card and the light offline
    forms, all in Alpine (resources/js/sales-offline.js) — no server round-trip. wire:ignore: Livewire never morphs it.
--}}
<div id="offline-desk" wire:ignore x-data="salesOffline" x-cloak x-show="on || items.length || form || flash"
     data-data-url="{{ route('sales.offline.data') }}" data-sync-url="{{ route('sales.offline.sync') }}">
    <template x-if="flash"><p class="absen-chip" role="status" x-text="flash"></p></template>

    <section class="work-card offline-card" x-show="on || items.length" aria-labelledby="h-offline">
        <div class="work-head">
            <h2 id="h-offline" x-text="on ? 'Mode offline' : 'Catatan offline'"></h2>
            <span class="work-times" x-show="items.length"><b x-text="items.length"></b> menunggu</span>
        </div>
        <p class="sales-note" x-show="on">Kunjungan, nota, stok awal, toko tutup dan toko baru disimpan di HP, lalu terkirim sendiri saat ada sinyal.</p>
        <ul class="offline-items" x-show="items.length">
            <template x-for="i in items" :key="i.uuid">
                <li :class="i.error && 'is-bad'">
                    <span><b x-text="i.label"></b><small x-show="i.error" class="text-danger" x-text="'Ditolak: ' + i.error"></small></span>
                    <button type="button" class="btn btn-secondary btn-sm" x-show="i.error" x-on:click="drop(i.uuid)">Hapus</button>
                </li>
            </template>
        </ul>
        <div class="work-actions">
            <button type="button" class="btn btn-primary" x-show="online && items.some(i => ! i.error)" x-on:click="sendNow()">Kirim sekarang</button>
        </div>
    </section>

    {{-- the open form --}}
    <div class="sales-sheet" role="dialog" aria-modal="true" x-show="form" x-transition.opacity.duration.100ms>
        <template x-if="form">
            <div class="sales-page">
                <header class="sales-head">
                    <button type="button" class="sales-back" aria-label="Tutup" x-on:click="close()"><x-lucide-arrow-left class="ico" /></button>
                    <h1 x-text="{ toko: store?.name, awal: 'Barang di toko', titip: 'Kunjungan', nota: 'Nota baru', tutup: 'Toko tutup', 'toko-baru': 'Toko baru' }[form] + (store && form !== 'toko' ? ' · ' + store.name : '') + ' (offline)'"></h1>
                </header>

                {{-- store: what can be done here offline --}}
                <template x-if="form === 'toko'">
                    <section>
                        <p class="sales-note" x-show="store.unsynced">Toko ini belum terkirim ke server. Kunjungannya bisa dibuat setelah terkirim.</p>
                        <template x-if="! store.unsynced">
                            <div class="work-actions">
                                <button type="button" class="btn btn-primary" x-show="! store.opened" x-on:click="open('awal', store.id)">Catat barang di toko</button>
                                <button type="button" class="btn btn-primary" x-show="store.opened" x-on:click="open('titip', store.id)">Kunjungan titip jual</button>
                                <button type="button" class="btn btn-secondary" x-show="store.opened" x-on:click="open('nota', store.id)">Nota jual langsung</button>
                                <button type="button" class="btn btn-secondary" x-show="store.stop" x-on:click="open('tutup', store.id)">Toko tutup</button>
                            </div>
                        </template>
                        <ul class="store-stock" x-show="Object.keys(store.at).length">
                            <template x-for="[id, q] in Object.entries(store.at)" :key="id"><li><span x-text="product(id).name"></span><b x-text="label(id, q)"></b></li></template>
                        </ul>
                    </section>
                </template>

                {{-- first visit: what of ours is already at the store --}}
                <template x-if="form === 'awal'">
                    <section>
                        <p class="sales-note">Catat barang <span x-text="snap.line.label"></span> kita yang masih ada di toko dari sebelumnya (boleh kosong).</p>
                        <input type="search" class="input" x-model="fields.search" placeholder="Cari barang…" aria-label="Cari barang">
                        <div class="sales-list">
                            <template x-for="id in catalog()" :key="id">
                                <div class="sales-row"><span class="sales-row-main"><b x-text="product(id).name"></b></span>
                                    <span class="sales-row-trail">@include('livewire.sales.partials.offline-qty', ['group' => 'awal'])</span></div>
                            </template>
                        </div>
                    </section>
                </template>

                {{-- titip jual: hitung sisa (dibawa kembali) → titip → bayar --}}
                <template x-if="form === 'titip'">
                    <section>
                        <ol class="visit-steps" aria-label="Langkah kunjungan">
                            <li :class="step === 'hitung' && 'is-now'">Hitung sisa</li><li :class="step === 'titip' && 'is-now'">Titip</li><li :class="step === 'bayar' && 'is-now'">Bayar</li>
                        </ol>
                        <div x-show="step === 'hitung'">
                            <p class="sales-note" x-show="! Object.keys(store.at).length">Belum ada barang titipan di toko ini. Lanjut untuk menitipkan barang.</p>
                            <template x-if="Object.keys(store.at).length">
                                <div>
                                    <p class="visit-old"><span>Titipan kunjungan lalu <b x-show="store.last" x-text="'· ' + when(store.last)"></b></span></p>
                                    <h2 class="visit-h">Barang yang ada di toko</h2>
                                    <ol class="visit-count">
                                        <template x-for="[id, had] in Object.entries(store.at)" :key="id">
                                            <li>
                                                <b x-text="product(id).name"></b>
                                                <span class="visit-had" x-text="'Dititip ' + label(id, had) + (product(id).ppu > 1 ? ' = ' + had + ' pcs' : '')"></span>
                                                <label class="visit-field"><span>Sisa di toko</span>@include('livewire.sales.partials.offline-qty', ['group' => 'remaining'])</label>
                                                <p class="visit-pay"><span>Terjual <b x-text="(had - pcs('remaining', id)) + ' pcs'"></b></span><span>Setoran <b x-text="rp(value(id, Math.max(0, had - pcs('remaining', id))))"></b></span></p>
                                            </li>
                                        </template>
                                    </ol>
                                    <p class="note-total">Total setoran <b x-text="rp(total(sold()))"></b></p>
                                </div>
                            </template>
                        </div>
                        <div x-show="step === 'titip'">
                            <p class="sales-note">Titipkan barang dari bawaan Anda (boleh kosong).</p>
                            <template x-for="id in carriedIds()" :key="id">
                                <div class="sales-row"><span class="sales-row-main"><b x-text="product(id).name"></b><small class="sales-row-meta" x-text="'Dibawa ' + label(id, snap.carried[id])"></small></span>
                                    <span class="sales-row-trail">@include('livewire.sales.partials.offline-qty', ['group' => 'titip'])</span></div>
                            </template>
                        </div>
                        <div x-show="step === 'bayar'">
                            <ul class="stock-review">
                                <template x-for="[id, q] in Object.entries(sold())" :key="id"><li x-text="'Terjual ' + product(id).name + ' · ' + label(id, q) + ' · ' + rp(value(id, q))"></li></template>
                                <li x-show="! Object.keys(sold()).length">Tidak ada yang terjual sejak kunjungan terakhir.</li>
                                <template x-for="[id, q] in Object.entries(store.at)" :key="'r' + id"><li x-show="pcs('remaining', id) > 0" x-text="'Dibawa kembali ' + product(id).name + ' · ' + label(id, pcs('remaining', id))"></li></template>
                                <template x-for="[id, q] in Object.entries(picked('titip'))" :key="'t' + id"><li x-text="'Titip ' + product(id).name + ' · ' + label(id, q)"></li></template>
                            </ul>
                            <template x-if="Object.keys(sold()).length">
                                <div>
                                    <p class="note-total">Dibayar toko <b x-text="rp(total(sold()))"></b></p>
                                    @include('livewire.sales.partials.offline-pay')
                                </div>
                            </template>
                        </div>
                    </section>
                </template>

                {{-- nota jual langsung --}}
                <template x-if="form === 'nota'">
                    <section>
                        <p class="sales-note" x-show="! carriedIds().length">Anda tidak membawa barang lini ini.</p>
                        <template x-for="id in carriedIds()" :key="id">
                            <div class="sales-row"><span class="sales-row-main"><b x-text="product(id).name"></b><small class="sales-row-meta" x-text="rp(product(id).price) + '/' + product(id).unit + ' · dibawa ' + label(id, snap.carried[id])"></small></span>
                                <span class="sales-row-trail">@include('livewire.sales.partials.offline-qty', ['group' => 'items'])</span></div>
                        </template>
                        <p class="note-total">Total <b x-text="rp(total(picked('items')))"></b></p>
                        @include('livewire.sales.partials.offline-pay')
                    </section>
                </template>

                {{-- toko tutup --}}
                <template x-if="form === 'tutup'">
                    <section>
                        <x-ui.offline-photo label="Foto toko yang tutup" />
                        <div class="field"><label for="off-note">Catatan (opsional)</label><input id="off-note" class="input" x-model="fields.note" placeholder="Mis. buka lagi jam 4 sore"></div>
                    </section>
                </template>

                {{-- toko baru --}}
                <template x-if="form === 'toko-baru'">
                    <section>
                        <div class="field"><label for="off-name">Nama toko</label><input id="off-name" class="input" x-model="fields.name"></div>
                        <div class="field"><label for="off-owner">Nama pemilik</label><input id="off-owner" class="input" x-model="fields.owner"></div>
                        <div class="field"><label for="off-phone">Nomor HP</label><input id="off-phone" class="input" type="tel" inputmode="tel" x-model="fields.phone" placeholder="0812xxxxxxxx"></div>
                        <div class="field">
                            <label>Titik toko</label>
                            <p class="sales-note" x-text="pin === 'mencari' ? 'Mencari titik GPS…' : (pin?.lat ? pin.lat.toFixed(5) + ', ' + pin.lng.toFixed(5) : 'Titik belum terbaca — nyalakan GPS.')"></p>
                            <button type="button" class="btn btn-secondary" x-on:click="locate()" :disabled="pin === 'mencari'">Ambil titik</button>
                        </div>
                        <div class="field"><label for="off-address">Alamat</label><textarea id="off-address" class="input" rows="2" x-model="fields.address" placeholder="Tanpa sinyal alamat tidak terisi otomatis — ketik singkat"></textarea></div>
                        <x-ui.offline-photo label="Foto depan warung" />
                    </section>
                </template>

                <section x-show="form !== 'toko'">
                    <p class="text-danger" role="alert" x-show="error" x-text="error"></p>
                    <div class="stock-actions">
                        <button type="button" class="btn btn-secondary" x-show="form === 'titip' && step !== 'hitung'" x-on:click="step = step === 'bayar' ? 'titip' : 'hitung'">Kembali</button>
                        <button type="button" class="btn btn-primary" x-show="form === 'titip' && step !== 'bayar'" x-on:click="next()">Lanjut</button>
                        <button type="button" class="btn btn-primary" x-show="form !== 'titip' || step === 'bayar'" x-on:click="save()" :disabled="busy">Simpan di HP</button>
                    </div>
                </section>
            </div>
        </template>
    </div>
</div>
