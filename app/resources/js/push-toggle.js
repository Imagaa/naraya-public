// Device push toggle (#pwa-push button + #pwa-push-state text), shared by the
// sales "Lainnya" page and the admin Notifikasi page. Needs a registered
// service worker and the vapid-public-key / csrf-token metas.
const $ = (selector) => document.querySelector(selector);
const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);

// --- Device notifications ---------------------------------------------------
const pushButton = $('#pwa-push');
const pushState = $('#pwa-push-state');

function base64UrlToBytes(value) {
    const padded = (value + '='.repeat((4 - (value.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
}

async function renderPush() {
    if (!pushButton) return;
    const nativePush = window.Capacitor?.isNativePlatform?.() && window.Capacitor.Plugins?.PushNotifications;
    if (nativePush) {
        // the Android app: notifications come through FCM, set up by resources/js/sales.js
        pushButton.hidden = true;
        const { receive } = await nativePush.checkPermissions();
        pushState.textContent = receive === 'granted'
            ? 'Notifikasi aplikasi aktif.'
            : 'Notifikasi aplikasi mati. Izinkan lewat Pengaturan HP → Aplikasi → Naraya Sales → Notifikasi.';
        return;
    }
    const supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

    if (!supported) {
        pushButton.hidden = true;
        pushState.textContent = isIos && !standalone
            ? 'Pasang aplikasi dulu agar notifikasi bisa aktif.'
            : 'Perangkat ini tidak mendukung notifikasi.';
        return;
    }
    if (Notification.permission === 'denied') {
        pushButton.hidden = true;
        pushState.textContent = 'Notifikasi diblokir. Izinkan lewat pengaturan situs di browser, lalu buka halaman ini lagi.';
        return;
    }

    const registration = await navigator.serviceWorker.ready;
    const subscription = await registration.pushManager.getSubscription();
    pushButton.hidden = Boolean(subscription);
    pushState.textContent = subscription ? 'Notifikasi perangkat aktif.' : '';
}

pushButton?.addEventListener('click', async () => {
    // The permission prompt must come straight from this tap.
    if ((await Notification.requestPermission()) !== 'granted') {
        renderPush();
        return;
    }
    try {
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: base64UrlToBytes($('meta[name="vapid-public-key"]').content),
        });
        const body = subscription.toJSON();
        body.content_encoding = (PushManager.supportedContentEncodings || ['aesgcm'])[0];

        const response = await fetch('/push-subscriptions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify(body),
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
    } catch (error) {
        pushState.textContent = 'Gagal mengaktifkan notifikasi. Coba lagi.';
        return;
    }
    renderPush();
});
renderPush();
