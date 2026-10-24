<div class="auth auth-register">
    <section class="auth-brand" aria-label="Naraya">
        <a href="{{ url('/') }}" class="logo" aria-label="Naraya, ke beranda"><x-brand.logo tone="reversed" class="logo-img" /></a>
        <h2>Daftarkan toko Anda untuk grosir, mainan anak, dan <em>investasi</em> Naraya.</h2>
        <ul class="auth-points">
            <li><span class="tile"><x-lucide-clipboard-check class="ico" /></span><span><b>Ditinjau tim kami</b>Biasanya kurang dari 1 hari kerja</span></li>
            <li><span class="tile"><x-lucide-truck class="ico" /></span><span><b>Grosir &amp; mainan</b>Diantar sales area Anda</span></li>
            <li><span class="tile"><x-lucide-trending-up class="ico" /></span><span><b>Investasi Naraya</b>Pendapatan masuk tiap bulan</span></li>
        </ul>
    </section>

    @if($submitted)
        <section class="auth-form auth-done" aria-labelledby="h-done">
            <span class="tile"><x-lucide-circle-check class="ico" /></span>
            <h1 id="h-done">Terima kasih</h1>
            <p>Pendaftaran diterima. Kami akan meninjau data toko Anda dan mengabari lewat WhatsApp/email.</p>
            <a href="{{ url('/') }}" class="btn btn-primary btn-block">Kembali ke Beranda</a>
        </section>
    @else
        <form class="auth-form" wire:submit="register">
            <a href="{{ url('/') }}" class="auth-back"><x-lucide-chevron-left class="ico ico-sm" />Kembali ke Beranda</a>
            <div class="pg-head">
                <h1>Daftar toko baru</h1>
                <p>Semua isian wajib diisi agar sales kami bisa mengantar pesanan Anda.</p>
            </div>

            <fieldset class="reg-section">
                <legend>Data toko</legend>
                <div class="field">
                    <label for="reg-store">Nama toko</label>
                    <input id="reg-store" class="input" type="text" wire:model="storeName" autocomplete="organization" required>
                    @error('storeName')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="reg-owner">Nama pemilik</label>
                    <input id="reg-owner" class="input" type="text" wire:model="ownerName" autocomplete="name" required>
                    @error('ownerName')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="reg-phone">Nomor HP/WhatsApp</label>
                    <input id="reg-phone" class="input" type="tel" inputmode="tel" wire:model="phone" placeholder="0812xxxxxxxx" autocomplete="tel" required>
                    @error('phone')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </fieldset>

            <fieldset class="reg-section">
                <legend>Akun</legend>
                <div class="line-switch auth-method" role="group" aria-label="Masuk dengan">
                    <button type="button" wire:click="$set('method', 'phone')" aria-pressed="{{ $method === 'phone' ? 'true' : 'false' }}">Nomor HP + PIN</button>
                    <button type="button" wire:click="$set('method', 'email')" aria-pressed="{{ $method === 'email' ? 'true' : 'false' }}">Email + kata sandi</button>
                </div>
                @if($method === 'phone')
                    <p class="hint" wire:key="reg-by-phone">Anda masuk dengan nomor HP di atas dan PIN 6 angka.</p>
                    <div class="field" wire:key="reg-pin">
                        <label for="reg-pin">PIN (6 angka)</label>
                        <input id="reg-pin" class="input" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="6" wire:model="pin" autocomplete="new-password" required>
                        @error('pin')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="field" wire:key="reg-pin2">
                        <label for="reg-pin2">Ulangi PIN</label>
                        <input id="reg-pin2" class="input" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="6" wire:model="pin_confirmation" autocomplete="new-password" required>
                    </div>
                @else
                    <div class="field" wire:key="reg-email">
                        <label for="reg-email">Email</label>
                        <input id="reg-email" class="input" type="email" wire:model="email" placeholder="nama@toko.com" autocomplete="email" required>
                        @error('email')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="field" wire:key="reg-password">
                        <label for="reg-password">Kata sandi</label>
                        <input id="reg-password" class="input" type="password" wire:model="password" autocomplete="new-password" required>
                        <span class="hint">Minimal 8 karakter.</span>
                        @error('password')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="field" wire:key="reg-password2">
                        <label for="reg-password2">Ulangi kata sandi</label>
                        <input id="reg-password2" class="input" type="password" wire:model="password_confirmation" autocomplete="new-password" required>
                    </div>
                @endif
            </fieldset>

            <fieldset class="reg-section">
                <legend>Alamat &amp; lokasi toko</legend>
                <button type="button" class="btn btn-secondary btn-sm" x-data x-on:click="navigator.geolocation?.getCurrentPosition(p => window.dispatchEvent(new CustomEvent('pinpoint-locate', { detail: { lat: p.coords.latitude, lng: p.coords.longitude } })))"><x-lucide-locate-fixed class="ico" />Gunakan lokasi saya</button>
                <x-mp.address-form-fields />
            </fieldset>

            <fieldset class="reg-section">
                <legend>Foto depan warung</legend>
                <div class="field">
                    <x-ui.file-picker id="reg-photo" capture="environment" wire:model="storePhoto" :file="$storePhoto" label="Ambil atau pilih foto" hint="Maks. 5 MB" aria-label="Foto depan warung" />
                    <span class="hint">Foto bagian depan toko, papan nama atau etalase terlihat jelas. Dipakai sebagai foto profil toko.</span>
                    @error('storePhoto')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </fieldset>

            <button type="submit" class="btn btn-primary btn-block" wire:loading.attr="disabled" wire:target="register,storePhoto">Daftar</button>
            <p class="auth-alt">Sudah punya akun? <a href="{{ route('login') }}">Masuk</a></p>
        </form>
    @endif
</div>
