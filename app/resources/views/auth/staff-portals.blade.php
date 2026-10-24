<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Portal Staf — Naraya</title>
    @include('partials.favicons')
    @vite(['resources/css/marketplace.css'])
</head>
<body class="mp">
    <div class="auth">
        <section class="auth-brand" aria-label="Naraya Staf">
            <span class="logo"><x-brand.logo tone="reversed" class="logo-img" /><small>Staf</small></span>
            <h2>Pilih <em>portal</em> Anda.</h2>
        </section>

        <div class="auth-form">
            <div class="pg-head">
                <h1>Portal Staf</h1>
                <p>Setiap portal punya pintu masuknya sendiri. Anda bisa masuk ke beberapa portal sekaligus di browser yang sama.</p>
            </div>

            @php($portals = [
                [\App\Enums\Role::Admin, 'layout-dashboard', 'Kelola pesanan, stok, dan setoran'],
                [\App\Enums\Role::Sales, 'truck', 'Kirim pesanan, kunjungi toko, setor'],
            ])
            <ul class="portal-list">
                @foreach($portals as [$role, $icon, $text])
                    <li>
                        <a href="{{ request()->getScheme() }}://{{ $role->portalDomain() }}/login" class="portal-link">
                            <span class="tile"><x-dynamic-component :component="'lucide-'.$icon" class="ico" /></span>
                            <span><b>{{ $role->label() }}</b>{{ $text }}</span>
                            <x-lucide-chevron-right class="ico" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</body>
</html>
