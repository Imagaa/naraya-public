<div class="auth">
    <section class="auth-brand" aria-label="Naraya">
        <a href="{{ url('/') }}" class="logo" aria-label="Naraya, ke beranda"><x-brand.logo tone="reversed" class="logo-img" /></a>
        <h2>Grosir, mainan anak, dan <em>investasi</em> untuk warung Anda.</h2>
        <div class="auth-lines" aria-label="Lini Naraya">
            {{-- --w = the lockup's width/height (its viewBox), so on a phone all three share one row at one height --}}
            @foreach(['grosir' => 6.36, 'toys' => 5.77, 'investasi' => 7.62] as $line => $ratio)
                <x-brand.logo :line="$line" tone="reversed" class="auth-line-logo" style="--w: {{ $ratio }}" />
            @endforeach
        </div>
        <ul class="auth-points">
            <li><span class="tile"><x-lucide-truck class="ico" /></span><span><b>Grosir &amp; mainan</b>Diantar sales area Anda</span></li>
            <li><span class="tile"><x-lucide-trending-up class="ico" /></span><span><b>Investasi Naraya</b>Pendapatan masuk tiap bulan</span></li>
            <li><span class="tile"><x-lucide-gift class="ico" /></span><span><b>Poin tiap belanja</b>Tukar dengan hadiah</span></li>
        </ul>
    </section>

    <form class="auth-form" wire:submit="authenticate">
        <a href="{{ url('/') }}" class="auth-back"><x-lucide-chevron-left class="ico ico-sm" />Kembali ke Beranda</a>
        <div class="pg-head">
            <h1>Masuk ke akun toko Anda</h1>
            <p>Untuk pemilik toko dan warung yang berbelanja di Naraya.</p>
        </div>

        <div class="line-switch auth-method" role="group" aria-label="Masuk dengan">
            <button type="button" wire:click="$set('method', 'phone')" aria-pressed="{{ $method === 'phone' ? 'true' : 'false' }}">Nomor HP</button>
            <button type="button" wire:click="$set('method', 'email')" aria-pressed="{{ $method === 'email' ? 'true' : 'false' }}">Email</button>
        </div>

        @if($method === 'phone')
            <div class="field" wire:key="by-phone">
                <label for="login-phone">Nomor HP</label>
                <span class="control"><x-lucide-phone class="ico" /><input id="login-phone" type="tel" inputmode="tel" wire:model="phone" placeholder="0812xxxxxxxx" autocomplete="username" required></span>
                @error('phone')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div class="field" wire:key="by-pin">
                <label for="login-pin">PIN (6 angka)</label>
                <span class="control"><x-lucide-lock class="ico" /><input id="login-pin" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="6" wire:model="pin" autocomplete="current-password" required></span>
                @error('pin')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        @else
            <div class="field" wire:key="by-email">
                <label for="login-email">Email</label>
                <span class="control"><x-lucide-mail class="ico" /><input id="login-email" type="email" wire:model="email" placeholder="nama@toko.com" autocomplete="username" required></span>
                @error('email')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div class="field" x-data="{ show: false }" wire:key="by-password">
                <label for="login-password">Kata sandi</label>
                <span class="control">
                    <x-lucide-lock class="ico" />
                    <input id="login-password" :type="show ? 'text' : 'password'" type="password" wire:model="password" autocomplete="current-password" required>
                    <button type="button" class="pw-toggle" x-on:click="show = !show" :aria-label="show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'" aria-label="Tampilkan kata sandi">
                        <x-lucide-eye class="ico" x-show="!show" />
                        <x-lucide-eye-off class="ico" x-show="show" x-cloak />
                    </button>
                </span>
                @error('password')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        @endif

        <label class="remember" for="login-remember"><input type="checkbox" id="login-remember" wire:model="remember">Ingat saya di perangkat ini</label>

        <button type="submit" class="btn btn-primary btn-block" wire:loading.attr="disabled">Masuk</button>

        <p class="auth-alt">Belum punya akun? <a href="{{ route('register') }}">Daftar sekarang</a></p>
    </form>
</div>
