{{-- Pasang aplikasi admin: steps for this device (Admin\Install::deviceFor), then switch on notifications. --}}
<div class="adm-page adm-install"
     x-data="{ standalone: window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true, prompt: null, installed: false }"
     x-init="window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); prompt = e; }); window.addEventListener('appinstalled', () => { installed = true; prompt = null; })">
    <x-admin.page-header title="Pasang aplikasi admin">Naraya Admin di layar utama HP, dengan notifikasi pesanan, setoran, sales dan investasi.</x-admin.page-header>

    {{-- already opened from the home screen: only the notifications are left --}}
    <x-admin.panel title="Aplikasi sudah terpasang" x-show="standalone || installed" x-cloak>
        <p>Langkah terakhir: izinkan notifikasi supaya kabar penting masuk ke HP ini.</p>
    </x-admin.panel>

    <div x-show="! standalone && ! installed">
        @if($device === 'android')
            <x-admin.panel title="Android">
                <div x-show="prompt" x-cloak>
                    <x-ui.button variant="primary" class="btn-block" x-on:click="prompt.prompt(); prompt.userChoice.then(() => prompt = null)">Pasang aplikasi</x-ui.button>
                </div>
                <ol class="adm-steps" x-show="! prompt">
                    <li>Buka halaman ini di <b>Chrome</b>.</li>
                    <li>Ketuk menu <b>⋮</b> di pojok kanan atas.</li>
                    <li>Pilih <b>Instal aplikasi</b> (atau <b>Tambahkan ke layar utama</b>), lalu <b>Instal</b>.</li>
                    <li>Buka <b>Naraya Admin</b> dari ikon di layar utama.</li>
                </ol>
            </x-admin.panel>
        @elseif($device === 'ios-safari')
            <x-admin.panel title="iPhone / iPad">
                <ol class="adm-steps">
                    <li>Ketuk tombol <b>Bagikan</b> <x-lucide-share class="ico" /> di bawah layar Safari.</li>
                    <li>Gulir lalu pilih <b>Tambahkan ke Layar Utama</b> <x-lucide-square-plus class="ico" />, lalu <b>Tambah</b>.</li>
                    <li>Tutup Safari dan buka <b>Naraya Admin</b> dari ikon di layar utama.</li>
                    <li>Di aplikasi itu, buka halaman ini lagi dan aktifkan notifikasi.</li>
                </ol>
                <p class="adm-muted">Notifikasi di iPhone butuh iOS 16.4 atau lebih baru dan hanya jalan dari aplikasi di layar utama.</p>
            </x-admin.panel>
        @elseif($device === 'ios-other')
            <x-admin.panel title="Buka di Safari">
                <p>Di iPhone, aplikasi hanya bisa dipasang lewat <b>Safari</b>. Salin alamat ini lalu buka di Safari:</p>
                <p class="adm-install-address">{{ $address }}</p>
                <x-ui.button variant="primary" class="btn-block" x-data="{ done: false }" x-on:click="navigator.clipboard?.writeText('https://{{ $address }}').then(() => done = true)"><span x-text="done ? 'Alamat disalin' : 'Salin alamat'">Salin alamat</span></x-ui.button>
            </x-admin.panel>
        @else
            <x-admin.panel title="Pasang di HP">
                <p>Buka alamat ini di HP (Chrome di Android, Safari di iPhone), lalu ikuti langkah yang muncul:</p>
                <p class="adm-install-address">{{ $address }}</p>
            </x-admin.panel>
        @endif
    </div>

    <x-admin.panel title="Notifikasi di HP ini">
        <p id="pwa-push-state"></p>
        <button type="button" id="pwa-push" class="btn btn-primary" hidden>Aktifkan notifikasi di perangkat ini</button>
    </x-admin.panel>
</div>
