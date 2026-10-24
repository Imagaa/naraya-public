<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">
    <title>{{ $title ?? 'Admin — Naraya' }}</title>
    @include('partials.favicons')
    {{-- installable as "Naraya Admin" --}}
    <link rel="manifest" href="/admin-manifest.json">
    <link rel="apple-touch-icon" href="/icons/admin-192.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Naraya Admin">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    @vite(['resources/css/admin.css', 'resources/js/app.js', 'resources/js/admin.js', 'resources/js/pinpoint-map.js'])
    @livewireStyles
</head>
<body class="adm-body" data-line="{{ \App\Support\ActiveLine::current()->value }}">
<x-sandbox-ribbon reset />
    @php
        // Groups, links and badges: App\Support\Admin\AdminNav::groups().
        $navGroups = \App\Support\Admin\AdminNav::groups();
        $settings = ['admin.loyalty-settings.edit', 'admin.loyalty-settings.*|admin.warehouse-settings.*|admin.expense-categories.*|admin.cash-book-settings.*|admin.attendance-settings.*', 'Pengaturan', 'settings', 0];
    @endphp

    <div class="adm-shell" x-data="{ sidebarOpen: false }">
        <div class="adm-backdrop" :class="{ 'is-open': sidebarOpen }" x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"></div>

        <aside class="adm-side" :class="{ 'is-open': sidebarOpen }">
            <a href="{{ route('admin.dashboard') }}" class="adm-brand" aria-label="Naraya Admin, ke beranda"><x-brand.logo tone="reversed" class="adm-brand-logo" /><small>Admin · {{ \App\Support\ActiveLine::current()->label() }}</small></a>
            <nav class="adm-nav" aria-label="Menu admin">
                @foreach($navGroups as [$groupLabel, $links])
                    <div class="adm-nav-group">
                        @if($groupLabel)<div class="adm-nav-group-label">{{ $groupLabel }}</div>@endif
                        @foreach($links as $link)
                            @include('layouts.partials.admin-nav-link', ['link' => $link])
                        @endforeach
                    </div>
                @endforeach
                <div class="adm-nav-group adm-nav-foot">
                    @include('layouts.partials.admin-nav-link', ['link' => $settings])
                </div>
            </nav>
        </aside>

        <div class="adm-main">
            <header class="adm-top">
                <button type="button" class="adm-icon-btn adm-menu-btn" @click="sidebarOpen = true" aria-label="Buka menu"><x-lucide-menu width="20" height="20" /></button>
                <button type="button" class="adm-search-trigger" x-data @click="$dispatch('open-search')">
                    <x-lucide-search width="16" height="16" aria-hidden="true" />
                    <span class="adm-search-label">Cari order, toko, produk, sales…</span>
                    <kbd>Ctrl K</kbd>
                </button>
                <div class="adm-top-spacer"></div>
                {{-- lockups on a wide screen, the 32 px line symbols on a phone (shell.css) so the bar never runs off the side --}}
                <x-ui.line-switch :action="route('admin.line')" class="adm-line-wide" />
                <x-ui.line-switch :action="route('admin.line')" symbols class="adm-line-narrow" />
                <button type="button" class="adm-icon-btn adm-search-icon" x-data @click="$dispatch('open-search')" aria-label="Cari"><x-lucide-search width="18" height="18" /></button>
                <livewire:admin.notification-bell />
                <div class="adm-user">
                    <button type="button" class="adm-avatar" popovertarget="adm-user-menu" aria-label="Akun {{ auth()->user()->name }}">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 2)) }}</button>
                    <div id="adm-user-menu" popover class="adm-user-menu">
                        <p>{{ auth()->user()->name }}</p>
                        <form method="POST" action="{{ route('admin.logout') }}" data-push-logout>
                            @csrf
                            <input type="hidden" name="push_endpoint" value="">
                            <button type="submit"><x-lucide-log-out width="16" height="16" /> Keluar</button>
                        </form>
                    </div>
                </div>
            </header>
            <main class="admin-content adm-content">
                {{ $slot }}
            </main>
        </div>
    </div>
    <livewire:admin.global-search />
    @livewireScripts
</body>
</html>
