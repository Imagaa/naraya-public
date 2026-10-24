<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#16181D">
    <title>{{ $title ?? 'Naraya' }}</title>
    @include('partials.favicons')
    @vite(array_values(array_filter([
        'resources/css/marketplace.css', 'resources/js/app.js', 'resources/js/marketplace.js',
        ($maps ?? false) ? 'resources/js/pinpoint-map.js' : null,
    ])))
    @livewireStyles
</head>
<body class="mp" data-line="{{ \App\Support\ActiveLine::current()->value }}">
<x-sandbox-ribbon />
    {{ $slot }}
    @livewireScripts
</body>
</html>
