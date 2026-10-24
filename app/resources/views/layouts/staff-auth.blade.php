<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title ?? 'Masuk Staf — Naraya' }}</title>
    {{-- Same look as the buyer login; the staff portals behind it keep their own styles. --}}
    @vite(['resources/css/marketplace.css', 'resources/js/app.js', 'resources/js/app-update.js'])
    @livewireStyles
</head>
<body class="mp" data-line="{{ \App\Support\ActiveLine::current()->value }}">
<x-sandbox-ribbon logins />
    {{ $slot }}
    @livewireScripts
</body>
</html>
