@php
    $user = auth()->user();
    $initials = collect(explode(' ', $user->name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $points = number_format($profile?->loyalty_points ?? 0, 0, ',', '.');
    $progress = app(\App\Support\Loyalty\StorePoints::class)->progress($user->id);
    $pin = $user->usesPin();
    $panels = [
        'profil' => ['user', 'Profil', $pin ? 'Nama, nomor telepon' : 'Nama, email, nomor telepon'],
        'alamat' => ['map-pin', 'Alamat', 'Tujuan pengiriman'],
        'keamanan' => ['lock', 'Keamanan', $pin ? 'Ganti PIN' : 'Ganti kata sandi'],
    ];
@endphp
<div class="page">
    @if($section)
        {{-- Phones get the task bar (back to the overview + section title) on a section page. --}}
        <x-mp.header active="account" :title="$panels[$section][1]" :back="route('account')" />
    @else
        <x-mp.header active="account" />
    @endif

    <main class="container pg">
        @if($statusMessage)
            <p class="note ok" role="status"><x-lucide-circle-check class="ico" /><span>{{ $statusMessage }}</span></p>
        @endif

        {{-- Master/detail: on phones the overview shows only the card and menu, and a
             section page shows only that section; from 1024px both columns show. --}}
        <div @class(['two', 'acct', 'is-detail' => $section])>
            <aside class="stack">
                <section class="panel me" aria-labelledby="h-me">
                    <div class="me-top">
                        @if($profile?->storePhotoUrl())
                            <img class="me-av me-photo" src="{{ $profile->storePhotoUrl() }}" alt="Foto toko {{ $user->name }}">
                        @else
                            <span class="me-av">{{ $initials }}</span>
                        @endif
                        <span><h1 id="h-me">{{ $user->name }}</h1><p>{{ $user->loginLabel() }} · pelanggan sejak {{ $memberSince }}</p></span>
                    </div>
                    <div class="stat-grid num">
                        <a class="stat" href="{{ route('rewards.index') }}"><b>{{ $points }}</b><span>Poin</span><small>{{ $progress['pcs'] }} / {{ $progress['per'] }} pcs menuju poin berikutnya</small></a>
                        <div class="stat"><b>@rupiah($profile?->credit_balance ?? 0)</b><span>Saldo kredit</span></div>
                        <a class="stat" href="{{ route('orders.index') }}"><b>{{ $activeOrders }}</b><span>Pesanan aktif</span></a>
                    </div>
                    @if($profile?->salesUser)
                        <div class="divider"></div>
                        <x-mp.sales-contact />
                    @endif
                </section>

                <ul class="menu">
                    @foreach($panels as $key => [$icon, $label, $hint])
                        <li>
                            <a href="{{ route('account', $key) }}" @if($tab === $key) aria-current="page" @endif>
                                <span class="tile"><x-dynamic-component :component="'lucide-'.$icon" class="ico" /></span>
                                <span><b>{{ $label }}</b><small>{{ $hint }}</small></span>
                                <em class="num">{{ $key === 'alamat' ? $addresses->count() : '' }}</em>
                                <x-lucide-chevron-right class="ico ico-sm chev" />
                            </a>
                        </li>
                    @endforeach
                    <li>
                        <a href="{{ route('orders.index') }}">
                            <span class="tile"><x-lucide-package class="ico" /></span>
                            <span><b>Pesanan</b><small>Lacak dan pesan lagi</small></span>
                            <em class="num">{{ $orderCount }}</em>
                            <x-lucide-chevron-right class="ico ico-sm chev" />
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('rewards.index') }}">
                            <span class="tile"><x-lucide-gift class="ico" /></span>
                            <span><b>Tukar Hadiah</b><small class="num">{{ $points }} poin</small></span>
                            <em></em>
                            <x-lucide-chevron-right class="ico ico-sm chev" />
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('investments.index') }}">
                            <span class="tile"><x-lucide-trending-up class="ico" /></span>
                            <span><b>Investasi</b><small>Paket, pendapatan dan dompet</small></span>
                            <em></em>
                            <x-lucide-chevron-right class="ico ico-sm chev" />
                        </a>
                    </li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="out">
                                <span class="tile"><x-lucide-log-out class="ico" /></span>
                                <span><b>Keluar</b></span>
                                <em></em>
                                <span></span>
                            </button>
                        </form>
                    </li>
                </ul>
            </aside>

            <div class="stack acct-panels">
                {{-- Panels always render and toggle with the hidden attribute
                     (same single-root reason as checkout). --}}
                <section class="panel" aria-labelledby="h-profil" @if($tab !== 'profil') hidden @endif>
                    <div class="panel-head"><h2 id="h-profil">Profil</h2></div>
                    @if(! $editingProfile)
                        <dl class="kv">
                            <div><dt>Nama</dt><dd>{{ $user->name }}</dd></div>
                            <div><dt>{{ $pin ? 'Masuk dengan' : 'Email' }}</dt><dd>{{ $user->loginLabel() }}</dd></div>
                            <div><dt>Telepon</dt><dd>{{ $profile?->phone ?: '-' }}</dd></div>
                        </dl>
                        <div class="form-actions">
                            <x-ui.button type="button" variant="secondary" wire:click="showEditProfile"><x-lucide-pencil class="ico ico-sm" />Ubah Profil</x-ui.button>
                        </div>
                    @else
                        <form wire:submit="saveProfile" class="addr-form">
                            <x-ui.input label="Nama" wire:model="name" />
                            @error('name')<p class="form-error">{{ $message }}</p>@enderror
                            <x-ui.input label="Telepon" wire:model="phone" />
                            @error('phone')<p class="form-error">{{ $message }}</p>@enderror
                            <div class="form-actions">
                                <x-ui.button type="button" variant="secondary" wire:click="cancelEditProfile">Batal</x-ui.button>
                                <x-ui.save-button target="name,phone">Simpan Profil</x-ui.save-button>
                            </div>
                        </form>
                    @endif
                </section>

                <section class="panel" aria-labelledby="h-alamat" @if($tab !== 'alamat') hidden @endif>
                    <div class="panel-head"><h2 id="h-alamat">Alamat pengiriman</h2></div>
                    @if(! $addingAddress)
                        <div class="addr-cards">
                            @forelse($addresses as $addr)
                                <article @class(['addr-card', 'main' => $addr->is_default]) wire:key="addr-{{ $addr->id }}">
                                    <div class="addr">
                                        <span @class(['tile', 'plain' => ! $addr->is_default])><x-dynamic-component :component="'lucide-'.($addr->is_default ? 'store' : 'map-pin')" class="ico" /></span>
                                        <div>
                                            <p class="opt-title">{{ $addr->label ?: 'Alamat' }}@if($addr->is_default)<x-mp.status tone="brand">Utama</x-mp.status>@endif</p>
                                            <p class="opt-desc">{{ $addr->toFullAddress() }}</p>
                                            @if($addr->lat && $addr->lng)
                                                <p class="pin-note"><x-lucide-map class="ico ico-sm" />Titik peta tersimpan</p>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="addr-actions">
                                        @if(! $addr->is_default)
                                            <button type="button" class="btn btn-ghost btn-sm" wire:click="setDefaultAddress({{ $addr->id }})"><x-lucide-star class="ico ico-sm" />Jadikan Utama</button>
                                        @endif
                                        <button type="button" class="btn btn-ghost btn-sm" wire:click="editAddress({{ $addr->id }})"><x-lucide-pencil class="ico ico-sm" />Ubah</button>
                                        <button type="button" class="btn btn-ghost btn-sm danger" wire:click="deleteAddress({{ $addr->id }})" wire:confirm="Hapus alamat ini?"><x-lucide-trash-2 class="ico ico-sm" />Hapus</button>
                                    </div>
                                </article>
                            @empty
                                <x-mp.empty icon="map-pin" title="Belum ada alamat tersimpan." text="Tambahkan alamat toko supaya checkout lebih cepat." />
                            @endforelse
                            <button type="button" class="addr-new" wire:click="showAddAddressForm"><x-lucide-plus class="ico" />Tambah alamat baru</button>
                        </div>
                    @else
                        <x-mp.address-form-fields>
                            <div class="form-actions">
                                <x-ui.button type="button" variant="secondary" wire:click="cancelAddressForm">Batal</x-ui.button>
                                <x-ui.save-button type="button" target="newLabel,newProvinceId,newCityId,newDistrictId,newVillageId,newDetail,newPostalCode,newLat,newLng" wire:click="saveAddress">Simpan Alamat</x-ui.save-button>
                            </div>
                        </x-mp.address-form-fields>
                    @endif
                </section>

                <section class="panel" aria-labelledby="h-keamanan" @if($tab !== 'keamanan') hidden @endif>
                    @php($noun = $pin ? 'PIN' : 'Kata Sandi')
                    <div class="panel-head"><h2 id="h-keamanan">Ubah {{ $noun }}</h2></div>
                    <form wire:submit="updatePassword" class="addr-form">
                        <x-ui.input type="password" :label="$noun.' Saat Ini'" wire:model="currentPassword" autocomplete="current-password" :inputmode="$pin ? 'numeric' : null" />
                        @error('currentPassword')<p class="form-error">{{ $message }}</p>@enderror
                        <x-ui.input type="password" :label="$noun.' Baru'" wire:model="newPassword" autocomplete="new-password" :inputmode="$pin ? 'numeric' : null" :maxlength="$pin ? 6 : null" />
                        @error('newPassword')<p class="form-error">{{ $message }}</p>@enderror
                        <x-ui.input type="password" :label="'Ulangi '.$noun.' Baru'" wire:model="newPassword_confirmation" autocomplete="new-password" :inputmode="$pin ? 'numeric' : null" :maxlength="$pin ? 6 : null" />
                        <x-ui.save-button target="currentPassword,newPassword,newPassword_confirmation" class="btn-block">Ubah {{ $noun }}</x-ui.save-button>
                    </form>
                </section>
            </div>
        </div>
    </main>

</div>
