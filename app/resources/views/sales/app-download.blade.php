@php($appName = config('naraya.sandbox') ? 'Naraya Coba' : 'Naraya Sales') {{-- the sandbox server serves the Naraya Coba app --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aplikasi {{ $appName }}</title>
    @include('partials.favicons')
    @vite(['resources/css/marketplace.css', 'resources/css/sales.css'])
</head>
<body class="sales-body">
    <main class="sales-main app-dl">
        <div class="sales-page">
            <header class="sales-head"><h1>Aplikasi {{ $appName }}</h1></header>
            <section>
                <x-brand.logo class="app-dl-logo" />
                <p class="sales-note">Untuk HP <b>Android</b>. Isinya sama dengan web sales — akun dan data yang sama — ditambah <b>GPS perjalanan tetap tercatat saat layar mati</b> selama Anda absen.</p>
                @if($release)
                    <a class="btn btn-primary btn-block" href="{{ $release['apkUrl'] }}">Unduh aplikasi (versi {{ $release['versionName'] }})</a>
                    @if($release['notes'])<p class="sales-note">Yang baru: {{ $release['notes'] }}</p>@endif
                @else
                    <p class="sales-note"><b>Aplikasi belum tersedia.</b> Sementara pakai web sales.</p>
                @endif
                <p class="sales-note">iPhone: pakai web sales lewat Safari → Bagikan → <b>Tambah ke Layar Utama</b>.</p>
            </section>
            <section>
                <h2 class="visit-h">Cara memasang</h2>
                <ol class="app-dl-steps">
                    <li>Ketuk <b>Unduh aplikasi</b>, lalu buka file yang terunduh.</li>
                    <li>Bila HP bertanya, izinkan <b>pasang dari sumber ini</b> (Chrome / Files), lalu <b>Pasang</b>.</li>
                    <li>Buka {{ $appName }} dan masuk seperti biasa.</li>
                    <li>Saat absen masuk, izinkan lokasi <b>"Izinkan sepanjang waktu"</b> dan notifikasi — notifikasi "{{ $appName }} merekam lokasi" tampil selama absen.</li>
                    <li>Di HP Xiaomi, Oppo, Vivo, Realme: Setelan → Aplikasi → {{ $appName }} → Baterai → <b>Tanpa batasan</b>, supaya perjalanan tidak terputus.</li>
                </ol>
                <p class="sales-note">Pembaruan: aplikasi memeriksa versi baru setiap dibuka dan menawarkan pembaruan.</p>
            </section>
        </div>
    </main>
</body>
</html>
