<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Sales — Naraya' }}</title>
    <link rel="manifest" href="/manifest.json">
    @include('partials.favicons')
    <meta name="theme-color" content="#F0B90B">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">
    <meta name="naraya-build" content="{{ \App\Support\BuildId::current() }}">
    {{-- Same design system as the marketplace; sales.css only adds sales-specific pieces. --}}
    {{-- No map bundle here: only the "Toko baru" sheet loads a map, on demand (resources/js/sales.js). --}}
    @vite(['resources/css/marketplace.css', 'resources/css/sales.css', 'resources/js/app.js', 'resources/js/sales.js', 'resources/js/delivery-photo.js', 'resources/js/sales-pwa.js'])
    @livewireStyles
</head>
@php($shift = auth()->check() ? \App\Support\Sales\Shift::open(auth()->user()) : null)
{{-- data-shift: checked in, so resources/js/sales.js records GPS until check-out; data-device-url: where the Android app sends its FCM token --}}
<body class="sales-body" data-line="{{ \App\Support\ActiveLine::current()->value }}" @if($shift) data-shift="{{ $shift->id }}" data-track-url="{{ route('sales.track.store') }}" @endif @auth data-device-url="{{ route('sales.device.store') }}" @endauth>
<x-sandbox-ribbon />
    <div id="sales-offline" class="sales-offline" role="status" hidden></div>
    <header class="hdr">
        <div class="hdr-main sales-hdr">
            <a href="{{ route('sales.home') }}" class="logo" aria-label="Naraya Sales, ke beranda"><x-brand.logo class="logo-img" /></a>
            <x-ui.line-switch :action="route('sales.line')" :symbols="true" class="sales-line-switch" />
            <span id="sales-gps" class="sales-gps" role="status" hidden></span>
            <livewire:sales.notification-bell />
        </div>
    </header>

    <main class="sales-main">
        {{ $slot }}
    </main>

    {{-- Three pages: Kerja, Rute and Lainnya; everything else is a sheet over them. --}}
    <nav class="tabbar sales-tabbar" aria-label="Menu utama">
        <a href="{{ route('sales.home') }}" @if(request()->routeIs('sales.home')) aria-current="page" @endif><span class="sales-tab-ico"><x-lucide-briefcase class="ico" /></span>Kerja</a>
        <a href="{{ route('sales.routes.index') }}" @if(request()->routeIs('sales.routes.*')) aria-current="page" @endif><span class="sales-tab-ico"><x-lucide-route class="ico" /></span>Rute</a>
        <a href="{{ route('sales.more') }}" @if(request()->routeIs('sales.more')) aria-current="page" @endif><span class="sales-tab-ico"><x-lucide-menu class="ico" /></span>Lainnya</a>
    </nav>

    @livewireScripts
</body>
</html>
