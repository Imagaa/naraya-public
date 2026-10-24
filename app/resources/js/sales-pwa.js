// Sales PWA glue: service worker lifecycle, update toast, offline banner,
// install button, device push toggle and the app-icon badge.
import './push-toggle';
import './logout-cleanup';

const $ = (selector) => document.querySelector(selector);
const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);

// --- Updates reload by themselves ------------------------------------------
// After a deploy the open page reloads itself (no "Versi baru" button to tap), but only at a safe moment: online, no
// sheet / panel / offline form open (each owns a history entry), not typing, no open dialog, nothing marked
// data-hold-refresh. Until then it waits and tries again. A deploy is spotted two ways: a new service worker
// (CACHE_VERSION bumped), or /aplikasi/versi reporting another build than the one this page was rendered by —
// checked whenever the app comes back to the front and every 5 minutes.
let registration = null;
let stale = false;
const pageBuild = $('meta[name="naraya-build"]')?.content || '';

function safeToReload() {
    const s = history.state || {};
    const field = document.activeElement?.closest?.('input, textarea, select, [contenteditable]');
    return navigator.onLine && !s.salesSheet && !s.storePanel && !s.offlineSheet && !field
        && !document.querySelector('dialog[open], [data-hold-refresh]');
}

function reloadWhenSafe() {
    stale = true;
    if (!safeToReload()) return; // tried again on the next tick, focus change or page show
    if (registration?.waiting && navigator.serviceWorker.controller) {
        registration.waiting.postMessage({ type: 'SKIP_WAITING' }); // controllerchange below reloads
    } else {
        window.location.reload();
    }
}

async function checkBuild() {
    if (!pageBuild || !navigator.onLine) return;
    try {
        const r = await fetch('/aplikasi/versi', { headers: { Accept: 'application/json' }, cache: 'no-store' });
        const { build } = await r.json();
        if (build && build !== pageBuild) reloadWhenSafe();
    } catch (e) { /* offline or busy server: next time */ }
    registration?.update().catch(() => {});
}

if ('serviceWorker' in navigator) {
    const hadController = Boolean(navigator.serviceWorker.controller);
    let reloading = false;

    navigator.serviceWorker.addEventListener('controllerchange', () => {
        // The first install also fires this (clients.claim); only reload when replacing an older worker.
        if (hadController && !reloading) {
            reloading = true;
            window.location.reload();
        }
    });

    navigator.serviceWorker.register('/service-worker.js').then((reg) => {
        registration = reg;
        if (reg.waiting && navigator.serviceWorker.controller) reloadWhenSafe();
        reg.addEventListener('updatefound', () => {
            const worker = reg.installing;
            worker?.addEventListener('statechange', () => {
                if (worker.state === 'installed' && navigator.serviceWorker.controller) reloadWhenSafe();
            });
        });
    });
}

document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') checkBuild(); });
setInterval(checkBuild, 5 * 60 * 1000);
// a stale page waiting for a safe moment: try again often (cheap, no network)
setInterval(() => { if (stale) reloadWhenSafe(); }, 3000);
['popstate', 'focusout', 'sheet-done', 'sheet-close'].forEach((e) => window.addEventListener(e, () => { if (stale) setTimeout(reloadWhenSafe, 300); }));

// --- Offline banner ---------------------------------------------------------
function renderOffline() {
    const bar = $('#sales-offline');
    if (!bar) return;
    bar.hidden = navigator.onLine;
    bar.textContent = 'Sedang offline';
}
window.addEventListener('online', renderOffline);
window.addEventListener('offline', renderOffline);
renderOffline();

// --- App icon badge (Chromium desktop, installed iOS 16.4+; Android shows a dot from the notification itself)
const bell = $('[data-unread]');
function syncBadge() {
    if (!bell || !('setAppBadge' in navigator)) return;
    const unread = Number(bell.dataset.unread) || 0;
    (unread > 0 ? navigator.setAppBadge(unread) : navigator.clearAppBadge()).catch(() => {});
}
if (bell) {
    syncBadge();
    // Livewire morphs the bell in place on each poll, so watching the attribute is enough.
    new MutationObserver(syncBadge).observe(bell, { attributes: true, attributeFilter: ['data-unread'] });
}

// --- Install ------------------------------------------------------------------
let installPrompt = null;
window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    installPrompt = event;
    const button = $('#pwa-install');
    if (button) button.hidden = false;
});
$('#pwa-install')?.addEventListener('click', async (event) => {
    if (!installPrompt) return;
    const button = event.currentTarget;
    installPrompt.prompt();
    await installPrompt.userChoice;
    installPrompt = null;
    button.hidden = true;
});
if (isIos && !standalone) $('#pwa-ios-guide')?.removeAttribute('hidden');

// --- Logout: forget this device -------------------------------------------
// Logout cleanup (push, offline pages, badge) lives in ./logout-cleanup, shared with the admin portal.

