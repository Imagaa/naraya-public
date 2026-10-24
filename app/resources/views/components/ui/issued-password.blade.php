{{-- A temporary password or PIN, shown once to the Admin/Sales who issued it, with a WhatsApp hand-over. --}}
@props(['name', 'login', 'password', 'pin' => false, 'phone' => null, 'doneUrl' => null])

@php
    $what = $pin ? 'PIN' : 'Kata sandi';
    $message = "Halo {$name}, akun toko Anda di Naraya sudah dibuat.\n"
        ."Masuk: https://".config('app.domain')."/login\n"
        .($pin ? "Nomor HP: {$login}\n" : "Email: {$login}\n")
        ."{$what} sementara: {$password}\n"
        ."Anda akan diminta membuat ".($pin ? 'PIN' : 'kata sandi')." baru saat pertama kali masuk.";
@endphp

<x-ui.card :title="$what.' sementara untuk '.$name">
    <div style="display:flex;flex-direction:column;gap:12px" x-data="{ copied: false }">
        <p style="margin:0;font-size:14px">Masuk dengan {{ $pin ? 'nomor HP' : 'email' }} <b>{{ $login }}</b></p>
        <code style="font-size:22px;letter-spacing:.08em;padding:12px;border-radius:8px;background:#F5F6F8;text-align:center;user-select:all">{{ $password }}</code>
        <p class="text-muted" style="margin:0;font-size:13px">{{ $what }} ini hanya ditampilkan sekali. Pemilik toko wajib menggantinya saat pertama kali masuk, sehingga pembuat akun tidak bisa memakai akun ini lagi.</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <x-ui.button type="button" variant="secondary" x-on:click="navigator.clipboard.writeText('{{ $password }}'); copied = true">
                <span x-text="copied ? 'Tersalin' : 'Salin {{ $pin ? 'PIN' : 'kata sandi' }}'">Salin {{ $pin ? 'PIN' : 'kata sandi' }}</span>
            </x-ui.button>
            @if($phone)
                <a class="btn btn-primary" href="https://wa.me/{{ $phone }}?text={{ rawurlencode($message) }}" target="_blank" rel="noopener">Kirim via WhatsApp</a>
            @endif
            @if($doneUrl)
                <a class="btn btn-secondary" href="{{ $doneUrl }}">Selesai</a>
            @endif
        </div>
    </div>
</x-ui.card>
