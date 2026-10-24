{{--
    The sandbox (config naraya.sandbox): a ribbon on every page so made-up data is never taken for real, the test
    logins on the login pages, and the admin's reset button. Renders nothing on production.
--}}
@props(['logins' => false, 'reset' => false])
@if(config('naraya.sandbox'))
    <div class="sandbox-ribbon" role="note">
        <b>MODE COBA</b> · data uji, bukan data asli.
        @if($reset)
            <span x-data>
                <button type="button" x-on:click="$refs.d.showModal()">Reset data coba</button>
                <dialog x-ref="d" class="confirm-dialog" x-on:click.self="$el.close()">
                    <p>Hapus semua data coba dan mulai lagi dari data awal? Semua akun coba ikut keluar.</p>
                    <form method="POST" action="{{ url('/coba/reset') }}" class="confirm-actions">
                        @csrf
                        <button type="button" class="btn btn-secondary" x-on:click="$refs.d.close()">Batal</button>
                        <button type="submit" class="btn btn-primary">Ya, reset</button>
                    </form>
                </dialog>
            </span>
        @endif
    </div>
    @if(session('status') && $logins)<p class="sandbox-logins" role="status">{{ session('status') }}</p>@endif
    @if($logins)
        <div class="sandbox-logins">
            <span><b>Akun coba</b> (kata sandi <code>{{ \Database\Seeders\SandboxSeeder::PASSWORD }}</code>):</span>
            @foreach(\Database\Seeders\SandboxSeeder::ACCOUNTS as [$name, $email, $role])
                <span>{{ $email }} — {{ $name }}</span>
            @endforeach
        </div>
    @endif
@endif
