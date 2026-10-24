<div class="auth">
    <section class="auth-brand" aria-label="Naraya {{ $portal->label() }}">
        <span class="logo"><x-brand.logo tone="reversed" class="logo-img" /><small>{{ $portal->label() }}</small></span>
        <h2>Portal <em>{{ $portal->label() }}</em> Naraya.</h2>
        <ul class="auth-points">
            @if($portal === \App\Enums\Role::Admin)
                <li><span class="tile"><x-lucide-layout-dashboard class="ico" /></span><span><b>Admin</b>Kelola pesanan, stok, dan setoran</span></li>
            @elseif($portal === \App\Enums\Role::Sales)
                <li><span class="tile"><x-lucide-truck class="ico" /></span><span><b>Sales</b>Kirim pesanan, kunjungi toko, setor</span></li>
            @else
                <li><span class="tile"><x-lucide-warehouse class="ico" /></span><span><b>Gudang</b>Siapkan dan serahkan barang</span></li>
            @endif
        </ul>
    </section>

    <form class="auth-form" wire:submit="authenticate">
        <div class="pg-head">
            <h1>Masuk {{ $portal->label() }}</h1>
            <p>Khusus akun {{ $portal->label() }}. Akun dibuat oleh Admin.</p>
        </div>

        <div class="field">
            <label for="staff-email">Email</label>
            <span class="control"><x-lucide-mail class="ico" /><input id="staff-email" type="email" wire:model="email" placeholder="nama@naraya.id" autocomplete="username" required></span>
            @error('email')<p class="form-error" role="alert">{{ $message }}</p>@enderror
        </div>

        <div class="field" x-data="{ show: false }">
            <label for="staff-password">Kata sandi</label>
            <span class="control">
                <x-lucide-lock class="ico" />
                <input id="staff-password" :type="show ? 'text' : 'password'" type="password" wire:model="password" autocomplete="current-password" required>
                <button type="button" class="pw-toggle" x-on:click="show = !show" :aria-label="show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'" aria-label="Tampilkan kata sandi">
                    <x-lucide-eye class="ico" x-show="!show" />
                    <x-lucide-eye-off class="ico" x-show="show" x-cloak />
                </button>
            </span>
        </div>

        <label class="remember" for="staff-remember"><input type="checkbox" id="staff-remember" wire:model="remember">Ingat saya di perangkat ini</label>

        <button type="submit" class="btn btn-primary btn-block" wire:loading.attr="disabled">Masuk</button>
    </form>
</div>
