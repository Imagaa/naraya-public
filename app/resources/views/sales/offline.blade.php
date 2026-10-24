<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#F0B90B">
    <title>Offline — Naraya Sales</title>
    {{-- Self-contained on purpose: the service worker serves this with no network, so it can't rely on the Vite bundle. --}}
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; box-sizing: border-box; font-family: system-ui, sans-serif; background: #F5F6F8; color: #1E2329; text-align: center; }
        .mark { display: block; width: 140px; height: auto; margin: 0 auto 20px; }
        h1 { margin: 0 0 8px; font-size: 20px; }
        p { margin: 0; color: #474D57; font-size: 14px; }
        button { margin-top: 20px; padding: 12px 20px; border: 0; border-radius: 999px; background: #F0B90B; color: #1E2329; font-weight: 700; font-size: 15px; }
    </style>
</head>
<body>
    <main>
        <img class="mark" src="/brand/naraya-horizontal.svg" alt="naraya" width="140" height="38">
        <h1>Sedang offline</h1>
        <p>Halaman ini belum tersimpan di perangkat. Sambungkan internet lalu coba lagi.</p>
        <button type="button" onclick="location.reload()">Coba lagi</button>
    </main>
</body>
</html>
