<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Naraya' }}</title>
    @include('partials.favicons')
    @vite(['resources/css/app.css'])
</head>
<body style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--color-surface)">
    <div style="text-align:center;max-width:420px;padding:0 20px">
        <img src="/brand/naraya-horizontal.svg" alt="naraya" width="140" height="38" style="display:block;margin:0 auto 24px">
        <div style="font-family:var(--font-heading);font-size:64px;color:var(--color-accent-700);line-height:1">{{ $code }}</div>
        <h1 style="font-size:20px;margin:12px 0 8px">{{ $heading }}</h1>
        <p style="font-size:14px;opacity:0.75;margin:0 0 20px">{{ $message }}</p>
        <x-ui.button variant="primary" tag="a" href="{{ url('/') }}">Kembali ke Beranda</x-ui.button>
    </div>
</body>
</html>
