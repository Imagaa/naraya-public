<div class="page">
    <main class="container pg onboard">
        <section class="panel onboard-card" aria-labelledby="h-newpass">
            <span class="tile"><x-lucide-shield-check class="ico" /></span>
            @php($noun = $pin ? 'PIN' : 'kata sandi')
            <h1 id="h-newpass">Buat {{ $noun }} baru</h1>
            <p>Akun ini dibuat oleh <b>{{ $setterName }}</b> ({{ $setterRole }}) untuk toko Anda. {{ ucfirst($noun) }} sementara yang Anda pakai diketahui oleh pembuat akun.</p>
            <p>Demi keamanan, buat {{ $noun }} baru yang hanya Anda ketahui{{ $pin ? ' (6 angka)' : '' }}. Setelah disimpan, {{ $noun }} sementara tidak berlaku lagi dan semua perangkat lain yang masuk dengan akun ini akan dikeluarkan.</p>

            <form wire:submit="save" class="stack">
                <div class="field">
                    <label for="np-password">{{ ucfirst($noun) }} baru</label>
                    <input id="np-password" class="input" type="password" wire:model="password" autocomplete="new-password" @if($pin) inputmode="numeric" pattern="[0-9]*" maxlength="6" @endif required>
                </div>
                <div class="field">
                    <label for="np-confirm">Ulangi {{ $noun }} baru</label>
                    <input id="np-confirm" class="input" type="password" wire:model="password_confirmation" autocomplete="new-password" @if($pin) inputmode="numeric" pattern="[0-9]*" maxlength="6" @endif required>
                </div>
                @error('password')<p class="form-error" role="alert">{{ $message }}</p>@enderror
                <button type="submit" class="btn btn-primary">Simpan {{ $noun }}</button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-secondary btn-sm">Keluar</button>
            </form>
        </section>
    </main>
</div>
