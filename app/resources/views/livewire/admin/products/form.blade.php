<div class="adm-page adm-detail" x-data="{ showCategoryDialog: false }" x-on:category-created.window="showCategoryDialog = false">
    <a href="{{ route('admin.products.index') }}" class="adm-back"><x-lucide-arrow-left width="16" height="16" /> Produk & Stok</a>
    <x-admin.page-header :title="$product?->exists ? $product->name : 'Tambah produk'" />

    <div class="adm-detail-main">
    <x-ui.card>
        <form wire:submit="save" style="display:flex;flex-direction:column;gap:12px">
            <x-ui.input label="Nama" wire:model="name" />
            @error('name') <span class="text-danger" style="font-size:12px">{{ $message }}</span> @enderror

            <div class="adm-barcode-row" style="display:flex;gap:8px;align-items:flex-end">
                <div style="flex:1">
                    <x-ui.input label="Barcode (EAN, opsional)" wire:model="barcode" inputmode="numeric" placeholder="Scan atau ketik barcode" />
                </div>
                <x-ui.button type="button" variant="secondary" wire:click="lookupBarcode">Cari dari Barcode</x-ui.button>
            </div>
            @error('barcode') <span class="text-danger" style="font-size:12px">{{ $message }}</span> @enderror
            <x-ui.input label="Kode SKU (internal)" wire:model="sku" placeholder="Mis. NRY-MIE-001" />
            @error('sku') <span class="text-danger" style="font-size:12px">{{ $message }}</span> @enderror
            @if($barcodeLookupMessage)
                <p class="text-muted" style="font-size:12px;margin:0">{{ $barcodeLookupMessage }}</p>
            @endif

            <div class="admin-category-picker">
                <div class="field">
                    <label>Kategori</label>
                    <select class="input" wire:model="categoryId">
                        <option value="">— Pilih kategori —</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-ui.button type="button" variant="secondary" x-on:click="showCategoryDialog = true">+</x-ui.button>
            </div>
            @error('categoryId') <span class="text-danger" style="font-size:12px">{{ $message }}</span> @enderror

            <div class="field">
                <label>Ukuran (opsional)</label>
                <div class="admin-size-input">
                    <input type="number" step="0.01" min="0" class="input" placeholder="mis. 600" wire:model="sizeValue">
                    <select class="input" wire:model="sizeUnit">
                        <option value="">Satuan</option>
                        @foreach(\App\Livewire\Admin\Products\Form::SIZE_UNITS as $u)
                            <option value="{{ $u }}">{{ $u }}</option>
                        @endforeach
                    </select>
                </div>
                @error('sizeValue') <span class="text-danger" style="font-size:12px">{{ $message }}</span> @enderror
            </div>

            <x-admin.currency-input label="Harga" wire-model="price" :value="$price" />
            @error('price') <span class="text-danger" style="font-size:12px">{{ $message }}</span> @enderror

            <x-admin.currency-input label="Harga setoran sales (per satuan, opsional)" wire-model="depositPrice" :value="$depositPrice" />
            <p class="text-muted" style="font-size:12px;margin:-6px 0 0">Yang disetor sales ke admin untuk tiap satuan terjual; selisih dengan Harga jadi bagian sales. Kosong = sama dengan Harga.</p>
            @error('depositPrice') <span class="text-danger" style="font-size:12px">{{ $message }}</span> @enderror

            <x-admin.currency-input label="Harga Diskon (opsional)" wire-model="discountPrice" :value="$discountPrice" />
            @error('discountPrice') <span class="text-danger" style="font-size:12px">{{ $message }}</span> @enderror
            <x-ui.input label="Promo berakhir (opsional, hanya dipakai jika ada Harga Diskon)" type="datetime-local" wire:model="discountEndsAt" />
            @error('discountEndsAt') <span class="text-danger" style="font-size:12px">{{ $message }}</span> @enderror

            <x-admin.currency-input label="Harga Grosir (opsional)" wire-model="wholesalePrice" :value="$wholesalePrice" />

            @if(!$product?->exists)
                <x-ui.input label="Stok Awal (pcs)" type="number" wire:model="stock" />
            @endif
            <x-ui.input label="Stok Minimum dalam pcs (opsional)" type="number" wire:model="minStock" />
            <x-ui.input label="Satuan Jual (opsional, mis. pack, pcs, box)" wire:model="unit" />
            <x-ui.input label="Isi per satuan (opsional, mis. 40 untuk karton isi 40 pcs)" type="number" min="1" wire:model="piecesPerUnit" />
            @error('piecesPerUnit') <span class="text-danger" style="font-size:12px">{{ $message }}</span> @enderror
            <x-ui.input label="Deskripsi" wire:model="description" />

            <div class="field">
                <label>Foto Produk</label>
                <x-ui.file-picker wire:model="photo" :file="$photo" :current="$product?->photo_path ? \Illuminate\Support\Facades\Storage::disk('s3')->url($product->photo_path) : null" hint="Foto utama di katalog" />
                @error('photo') <span class="text-danger" style="font-size:12px">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>Foto tambahan (maks. {{ \App\Livewire\Admin\Products\Form::MAX_EXTRA_PHOTOS }})</label>
                @if($product?->exists && $product->photos->isNotEmpty())
                    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:8px">
                        @foreach($product->photos as $extra)
                            <div wire:key="extra-{{ $extra->id }}" style="display:flex;flex-direction:column;align-items:center;gap:4px">
                                <img src="{{ $extra->url() }}" alt="Foto tambahan {{ $loop->iteration }}" style="width:72px;height:72px;object-fit:cover;border-radius:var(--radius-sm)">
                                <div style="display:flex;gap:4px">
                                    <button type="button" class="btn btn-secondary btn-icon" wire:click="movePhoto({{ $extra->id }}, -1)" @disabled($loop->first) aria-label="Geser ke depan">&larr;</button>
                                    <button type="button" class="btn btn-secondary btn-icon" wire:click="movePhoto({{ $extra->id }}, 1)" @disabled($loop->last) aria-label="Geser ke belakang">&rarr;</button>
                                    <button type="button" class="btn btn-danger btn-icon" wire:click="removePhoto({{ $extra->id }})" aria-label="Hapus foto">&times;</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
                @if($extraPhotos)
                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px">
                        @foreach($extraPhotos as $pending)
                            <img src="{{ $pending->temporaryUrl() }}" alt="Pratinjau foto tambahan" style="width:72px;height:72px;object-fit:cover;border-radius:var(--radius-sm)">
                        @endforeach
                    </div>
                @endif
                <x-ui.file-picker wire:model="extraPhotos" :file="$extraPhotos" multiple label="Pilih foto tambahan" hint="Bisa pilih beberapa sekaligus" />
                @error('extraPhotos') <span class="text-danger" style="font-size:12px">{{ $message }}</span> @enderror
                @error('extraPhotos.*') <span class="text-danger" style="font-size:12px">{{ $message }}</span> @enderror
            </div>

            <x-ui.save-button target="name,sku,barcode,categoryId,sizeValue,sizeUnit,price,depositPrice,discountPrice,wholesalePrice,stock,minStock,unit,description,isActive,piecesPerUnit,discountEndsAt" :changed="$photo !== null || $extraPhotos !== []">Simpan</x-ui.save-button>
        </form>
    </x-ui.card>
    </div>

    @if($product?->exists)
        <div class="adm-detail-side">
        <x-ui.card title="Stok gudang">
            <p style="margin:0 0 8px">Sekarang: <b>{{ $product->qtyLabel($stock) }}</b> ({{ number_format($stock, 0, ',', '.') }} pcs)</p>
            @if(session('stock_ok'))<p class="text-success" role="status" style="margin:0 0 8px">{{ session('stock_ok') }}</p>@endif
            <form wire:submit="correctStock" style="display:flex;flex-direction:column;gap:12px">
                <x-ui.input label="Stok hasil hitung (pcs)" type="number" min="0" wire:model="countedStock" />
                @error('countedStock') <span class="text-danger" style="font-size:12px">{{ $message }}</span> @enderror
                <x-ui.input label="Alasan" wire:model="stockNote" placeholder="Mis. stok opname, barang rusak, salah input" />
                @error('stockNote') <span class="text-danger" style="font-size:12px">{{ $message }}</span> @enderror
                <x-ui.button type="submit" variant="secondary">Sesuaikan stok</x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card title="Hapus produk">
            <p class="text-muted" style="margin:0 0 8px;font-size:13px">Hanya untuk produk yang belum pernah dipakai di pesanan, nota atau stok sales. Yang sudah dipakai cukup dimatikan.</p>
            @error('destroy') <p class="text-danger" role="alert" style="font-size:13px">{{ $message }}</p> @enderror
            <x-ui.confirm-button variant="danger" action="destroy" yes="Ya, hapus" message="Hapus produk {{ $product->name }}? Tidak bisa dibatalkan.">Hapus produk</x-ui.confirm-button>
        </x-ui.card>
        </div>
    @endif

    <div
        class="dialog-backdrop" x-show="showCategoryDialog" x-cloak x-on:click.self="showCategoryDialog = false"
        x-data="{
            name: '',
            color: '{{ \App\Livewire\Admin\Products\Form::COLOR_PALETTE[0] }}',
            icon: '{{ \App\Livewire\Admin\Products\Form::ICON_OPTIONS[0] }}',
            pickColor(c) { this.color = c; $wire.set('newCategoryColor', c, false); },
            pickIcon(i) { this.icon = i; $wire.set('newCategoryIcon', i, false); },
        }"
        x-init="$watch('name', v => $wire.set('newCategoryName', v, false))"
    >
        <div class="dialog">
            <div class="dialog-title">Buat Kategori Baru</div>
            <div class="dialog-body">
                <div style="display:flex;flex-direction:column;gap:12px">
                    <x-ui.input label="Nama Kategori" x-model="name" />
                    @error('newCategoryName') <span class="text-danger" style="font-size:12px">{{ $message }}</span> @enderror

                    <div class="field">
                        <label>Warna Tag</label>
                        <div class="admin-swatch-grid">
                            @foreach(\App\Livewire\Admin\Products\Form::COLOR_PALETTE as $hex)
                                <button
                                    type="button" class="admin-swatch" style="background:{{ $hex }}"
                                    :class="color === '{{ $hex }}' ? 'is-active' : ''"
                                    x-on:click="pickColor('{{ $hex }}')"
                                    aria-label="{{ $hex }}"
                                ></button>
                            @endforeach
                        </div>
                    </div>
                    @error('newCategoryColor') <span class="text-danger" style="font-size:12px">{{ $message }}</span> @enderror

                    <div class="field">
                        <label>Ikon</label>
                        <div class="admin-icon-grid">
                            @foreach(\App\Livewire\Admin\Products\Form::ICON_OPTIONS as $iconName)
                                <button
                                    type="button" class="admin-icon-option"
                                    :class="icon === '{{ $iconName }}' ? 'is-active' : ''"
                                    x-on:click="pickIcon('{{ $iconName }}')"
                                    aria-label="{{ $iconName }}"
                                ><x-dynamic-component :component="'lucide-'.$iconName" width="18" height="18" /></button>
                            @endforeach
                        </div>
                    </div>
                    @error('newCategoryIcon') <span class="text-danger" style="font-size:12px">{{ $message }}</span> @enderror

                    <div class="field">
                        <label>Pratinjau</label>
                        <span class="tag" :style="'background:color-mix(in srgb, ' + color + ' 18%, white); color:color-mix(in srgb, ' + color + ' 60%, black)'" x-text="name || 'Pratinjau'"></span>
                    </div>
                </div>
            </div>
            <div class="dialog-actions">
                <x-ui.button type="button" variant="secondary" x-on:click="showCategoryDialog = false">Batal</x-ui.button>
                <x-ui.button type="button" variant="primary" wire:click="createCategory">Buat Kategori</x-ui.button>
            </div>
        </div>
    </div>
</div>
