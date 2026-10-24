@php
    $links = [
        ['notifikasi', 'Notifikasi', 'bell', $unread ? $unread.' belum dibaca' : null],
        ['absensi', 'Absensi & libur mingguan', 'clock', null],
        ['nota-saya', 'Nota saya', 'receipt', null],
        ['kunjungan', 'Riwayat kunjungan', 'map-pin', null],
        ['riwayat-stok', 'Riwayat stok', 'package', null],
        ['setor', 'Setoran cash', 'wallet', null],
        ['target', 'Target bulanan', 'target', null],
    ];
@endphp
<div>
    <x-sales.page title="Lainnya">
        @if($flash)<p class="absen-chip is-ok" role="status">{{ $flash }}</p>@endif

        <div class="sales-list">
            <x-sales.row as="button" type="button" wire:click="open('akun')">
                <x-slot:lead><x-ui.person-avatar :user="auth()->user()" :size="48" /></x-slot:lead>
                <b>{{ auth()->user()->name }}</b>
                <x-slot:meta>Akun, foto, dan kata sandi</x-slot:meta>
                <x-slot:trail><x-lucide-chevron-right width="16" height="16" /></x-slot:trail>
            </x-sales.row>
        </div>

        <div class="sales-list">
            @foreach($links as [$key, $label, $icon, $meta])
                <x-sales.row as="button" type="button" wire:click="open('{{ $key }}')" wire:key="l-{{ $key }}">
                    <x-slot:lead><span class="sales-row-icon"><x-dynamic-component :component="'lucide-'.$icon" width="16" height="16" /></span></x-slot:lead>
                    <b>{{ $label }}</b>@if($meta) <small class="sales-row-meta">{{ $meta }}</small>@endif
                    <x-slot:trail><x-lucide-chevron-right width="16" height="16" /></x-slot:trail>
                </x-sales.row>
            @endforeach
        </div>

        <x-sales.section title="Aplikasi">
            <x-ui.card>
                <a href="{{ route('sales.app') }}" class="btn btn-primary" data-web-only>Unduh aplikasi Android</a>
                <p class="sales-note" data-web-only>Di aplikasi Android, GPS perjalanan tetap tercatat saat layar mati. iPhone: pakai web ini (Tambah ke Layar Utama).</p>
                <button id="pwa-push" type="button" class="btn btn-primary" hidden>Aktifkan notifikasi</button>
                <p id="pwa-push-state" class="sales-note" role="status"></p>
                <button id="pwa-install" type="button" class="btn btn-secondary" hidden>Pasang aplikasi</button>
                <p id="pwa-ios-guide" class="sales-note" hidden>Di iPhone/iPad: ketuk <b>Bagikan</b> lalu <b>Tambah ke Layar Utama</b>.</p>
            </x-ui.card>
        </x-sales.section>

        <form id="sales-logout" method="POST" action="{{ route('sales.logout') }}" data-push-logout>
            @csrf
            {{-- Filled by logout-cleanup.js with this phone's push endpoint / the Android app's FCM token. --}}
            <input type="hidden" name="push_endpoint" value="">
            <input type="hidden" name="fcm_token" value="">
            <x-ui.button type="submit" variant="secondary" class="btn-block">Keluar</x-ui.button>
        </form>
    </x-sales.page>

    @include('livewire.sales.partials.sheet')
</div>
