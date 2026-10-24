// Sales app: two pages with sheets over them, kept light for
// low-end phones — the map code is only downloaded when a sheet with a map opens.
import { registerAddressFromPin } from './address-from-pin';
import './sales-offline';
import './app-update'; // the app's own updater, also on the login page
import { storeMatcher } from './store-match';

document.addEventListener('alpine:init', () => {
    registerAddressFromPin(Alpine);

    // An open sheet owns one history entry, so the phone's Back button closes it instead of leaving the page.
    Alpine.data('salesSheet', () => ({
        init() {
            document.body.classList.add('sheet-open');
            if (!history.state?.salesSheet) history.pushState({ salesSheet: true }, '');
            this.onPop = () => { this.popped = true; this.$wire.close(); };
            window.addEventListener('popstate', this.onPop);
        },
        destroy() {
            document.body.classList.remove('sheet-open');
            window.removeEventListener('popstate', this.onPop);
            if (!this.popped && history.state?.salesSheet) history.back();
        },
    }));

    // Store search on Kerja: an autocomplete over the sales person's
    // own stores, filtered right here on the phone (no round trip, works offline). Fuzzy (store-match.js): every typed
    // word must match the name, owner or address, typos forgiven, best matches first; ↑/↓ + Enter pick a row, Esc
    // closes. A row opens the store sheet (data-offline too).
    const esc = (t) => (t || '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    Alpine.data('storeSearch', (stores) => ({
        q: '',
        open: false,
        active: -1,
        init() { this.all = stores.map((s) => ({ ...s, hay: [s.name, s.owner, s.sub].join(' ') })); },
        get matcher() { return storeMatcher(this.q); },
        get results() {
            const m = this.matcher;
            if (m.empty) return [];
            return this.all.map((s) => ({ s, score: m.score(s.hay) })).filter((r) => r.score > 0)
                .sort((x, y) => y.score - x.score || x.s.name.localeCompare(y.s.name)).slice(0, 8).map((r) => r.s);
        },
        /** The text with every matching word in bold (escaped first: store names come from buyers). */
        mark(text) {
            const m = this.matcher;
            return (text || '').split(/([\p{L}\p{N}]+)/u).map((part, i) => (i % 2 && !m.empty && m.hit(part) ? '<b>' + esc(part) + '</b>' : esc(part))).join('');
        },
        move(dir) {
            const n = this.results.length;
            if (!n) return;
            this.open = true;
            this.active = (this.active + dir + n) % n;
            this.$nextTick(() => this.$root.querySelector('.ssearch-opt.is-active')?.scrollIntoView({ block: 'nearest' }));
        },
        enter() { this.$root.querySelectorAll('.ssearch-opt')[Math.max(this.active, 0)]?.click(); },
        choose(s) { this.close(); this.q = ''; this.$wire.open('toko', s.id); },
        close() { this.open = false; this.active = -1; this.$refs.input?.blur(); },
    }));

    // "Semua toko saya": a full-screen list over Kerja, rendered with the page (opens offline). It owns one history
    // entry, so the phone's Back button closes it; a store sheet opened from it closes back onto it.
    Alpine.data('storePanel', () => ({
        open: false,
        init() {
            this.onPop = () => { this.open = !!history.state?.storePanel; };
            window.addEventListener('popstate', this.onPop);
        },
        destroy() { window.removeEventListener('popstate', this.onPop); },
        show() { history.pushState({ storePanel: true }, ''); this.open = true; },
        hide() { if (history.state?.storePanel) history.back(); else this.open = false; },
    }));

    // Atur jadwal (App\Livewire\Sales\Schedule): the week's ticks stay here until one Simpan sends all seven days.
    const DAY_NAMES = { 1: 'Senin', 2: 'Selasa', 3: 'Rabu', 4: 'Kamis', 5: 'Jumat', 6: 'Sabtu', 7: 'Minggu' };
    const SHORT_DAYS = { 1: 'Sen', 2: 'Sel', 3: 'Rab', 4: 'Kam', 5: 'Jum', 6: 'Sab', 7: 'Min' };
    Alpine.data('storeSchedule', (days, stores, today) => ({
        day: today,
        q: '',
        unscheduledOnly: false,
        saving: false,
        init() {
            this.picked = Object.fromEntries(Object.keys(DAY_NAMES).map((d) => [d, (days[d] || []).map(Number)]));
            this.saved = JSON.stringify(this.picked);
        },
        get dayName() { return DAY_NAMES[this.day]; },
        get dirty() { return JSON.stringify(this.picked) !== this.saved; },
        get shown() {
            const m = storeMatcher(this.q);
            return stores.filter((s) => (!this.unscheduledOnly || !this.otherDays(s.id).length && !this.picked[this.day].includes(s.id))
                && (m.empty || m.score(s.name + ' ' + s.sub) > 0));
        },
        otherDays(id) { return Object.keys(DAY_NAMES).filter((d) => Number(d) !== this.day && this.picked[d].includes(id)); },
        others(id) { const d = this.otherDays(id); return d.length ? 'Juga hari ' + d.map((x) => SHORT_DAYS[x]).join(', ') : ''; },
        toggle(id) {
            const list = this.picked[this.day];
            this.picked[this.day] = list.includes(id) ? list.filter((x) => x !== id) : [...list, id];
        },
        async save() {
            this.saving = true;
            try { await this.$wire.save(this.picked); } finally { this.saving = false; }
        },
    }));

    // The Rute page map — same name and arguments as the full bundle's, loaded on demand.
    Alpine.data('routePlannerMap', (points, path) => ({
        api: null,
        marked: null,
        async init() {
            const { routeMap } = await import('./sales-map');
            this.api = routeMap(this.$refs.mapEl, points, path);
            if (this.marked) this.api.mark(this.marked);
        },
        destroy() {
            this.api?.map.remove();
        },
        /** Ticked stores, from x-effect on $wire.selected: only pins change. */
        mark(ids) {
            this.marked = [...(ids ?? [])];
            this.api?.mark(this.marked);
        },
    }));

    // Same name and interface as the full map bundle's, so <x-map.pinpoint-picker> works unchanged.
    Alpine.data('pinpointPicker', (initialLat, initialLng, wireLatModel, wireLngModel) => ({
        lat: initialLat,
        lng: initialLng,
        api: null,
        pending: null,

        async init() {
            const { pinpointMap } = await import('./sales-map');
            this.api = pinpointMap(this.$refs.pinpointMapEl, this.lat, this.lng, (lat, lng) => this.sync(lat, lng));
            if (this.pending) this.api.placeAt(...this.pending);
            else if (this.lat && this.lng) this.sync(this.lat, this.lng);
        },

        destroy() {
            this.api?.map.remove();
        },

        sync(lat, lng) {
            this.lat = lat;
            this.lng = lng;
            // Only write real moves: re-setting the loaded pin would mark an untouched form as changed (x-ui.save-button).
            const same = (a, b) => a !== null && a !== undefined && Math.abs(Number(a) - b) < 1e-7;
            if (!same(this.$wire.get(wireLatModel), lat)) this.$wire.set(wireLatModel, lat, false);
            if (!same(this.$wire.get(wireLngModel), lng)) this.$wire.set(wireLngModel, lng, false);
            window.dispatchEvent(new CustomEvent('pinpoint-moved', { detail: { lat, lng } }));
        },

        placeAt(lat, lng) {
            if (!this.api) { this.pending = [lat, lng]; return; }
            this.api.placeAt(lat, lng);
        },

        recenter(lat, lng, zoom = 15) {
            if (this.api && !(this.lat && this.lng)) this.api.map.setView([lat, lng], zoom);
        },

        setRadius() {},

        onShow() {
            this.api?.map.invalidateSize();
        },
    }));
});

// Inside the Naraya Sales Android app the page runs in a native
// shell: Capacitor's bridge is there, GPS keeps recording with the screen off, and the app updates itself.
const cap = window.Capacitor;
const inApp = Boolean(cap?.isNativePlatform?.());
if (inApp) document.documentElement.classList.add('in-app');

// Shift GPS: while checked in, record where the sales person goes.
// Points wait in a queue on the phone and go up when there is signal, so a dead zone loses nothing. In a browser
// a web app only records while it is open; in the Android app a foreground service keeps recording.
(function track() {
    const shift = document.body.dataset.shift;
    const url = document.body.dataset.trackUrl;
    // the bridge injected into the live site has no registerPlugin(): plugins are on Capacitor.Plugins
    const BG = inApp ? cap.Plugins?.BackgroundGeolocation ?? null : null;

    // one native watcher at a time: a page load replaces the one the previous page started, and check-out ends it
    const oldWatcher = (() => { try { return sessionStorage.getItem('naraya-bg-watcher'); } catch (e) { return null; } })();
    if (BG && oldWatcher) BG.removeWatcher({ id: oldWatcher }).catch(() => {});
    if (!shift || !url || (!BG && !('geolocation' in navigator))) {
        try { sessionStorage.removeItem('naraya-bg-watcher'); } catch (e) { /* blocked */ }
        return;
    }

    const key = `naraya-track:${shift}`;
    const chip = document.getElementById('sales-gps');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const load = () => { try { return JSON.parse(localStorage.getItem(key) || '[]'); } catch (e) { return []; } };
    const save = (q) => { try { localStorage.setItem(key, JSON.stringify(q.slice(-3000))); } catch (e) { /* storage full or blocked */ } };
    const show = (ok, text) => { if (!chip) return; chip.hidden = false; chip.className = 'sales-gps ' + (ok ? 'is-ok' : 'is-bad'); chip.textContent = text; };
    const metres = (a, b) => {
        const rad = (d) => (d * Math.PI) / 180;
        const h = Math.sin(rad(b.lat - a.lat) / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(rad(b.lng - a.lng) / 2) ** 2;
        return 6371000 * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
    };

    let last = null;
    let lastFix = 0;
    let sending = null;

    const flush = () => {
        if (sending) return sending;
        const batch = load().slice(0, 500);
        if (!batch.length || !navigator.onLine) return Promise.resolve();
        sending = fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ points: batch }),
            keepalive: true,
        }).then((r) => {
            if (r.ok) save(load().slice(batch.length));
            else if (r.status === 409) localStorage.removeItem(key); // the shift is over
        }).catch(() => { /* no signal: try again later */ }).finally(() => { sending = null; });
        return sending;
    };

    const onFix = (pt) => {
        lastFix = Date.now();
        show(true, inApp ? 'GPS aktif · app' : 'GPS aktif');
        if (pt.acc > 100) return; // too rough to count
        if (last && metres(last, pt) < 20 && pt.t - last.t < 120000) return; // every 20 m, or every 2 minutes when standing
        last = pt;
        const q = load();
        q.push(pt);
        save(q);
        if (q.length >= 10) flush(); // in the background timers are slowed down: send from here too
    };

    if (BG) {
        BG.addWatcher({
            backgroundTitle: 'Naraya Sales merekam lokasi',
            backgroundMessage: 'Perjalanan Anda dicatat sampai absen pulang.',
            requestPermissions: true,
            stale: false,
            distanceFilter: 20,
        }, (loc, err) => {
            if (err) return show(false, err.code === 'NOT_AUTHORIZED' ? 'Izinkan lokasi' : 'GPS mati');
            onFix({ lat: loc.latitude, lng: loc.longitude, acc: Math.round(loc.accuracy), t: loc.time || Date.now() });
        }).then((id) => { try { sessionStorage.setItem('naraya-bg-watcher', id); } catch (e) { /* blocked */ } });
    } else {
        navigator.geolocation.watchPosition(
            (p) => onFix({ lat: p.coords.latitude, lng: p.coords.longitude, acc: Math.round(p.coords.accuracy), t: p.timestamp || Date.now() }),
            (e) => show(false, e.code === 1 ? 'Izinkan lokasi' : 'GPS mati'),
            { enableHighAccuracy: true, maximumAge: 15000, timeout: 30000 },
        );
    }

    window.salesTrackFlush = flush; // check-out sends what is queued first
    setInterval(flush, 60000);
    setInterval(() => { if (lastFix && Date.now() - lastFix > 120000) show(false, 'GPS tidak terbaca'); }, 30000);
    window.addEventListener('online', flush);
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') flush(); });
    flush();
})();


// Notifications in the app: Web Push can't reach a WebView, so the app
// registers with FCM and gives the server its token; SalesNotice pushes there too. Tapping one opens its page.
(async function nativePush() {
    const Push = inApp ? cap.Plugins?.PushNotifications : null;
    if (!Push || !document.body.dataset.deviceUrl) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    Push.addListener('registration', ({ value }) => {
        localStorage.setItem('naraya-fcm-token', value);
        if (sessionStorage.getItem('naraya-fcm-sent') === value) return; // once per app start; logout clears it
        fetch(document.body.dataset.deviceUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ token: value }),
        }).then((r) => { if (r.ok) sessionStorage.setItem('naraya-fcm-sent', value); }).catch(() => {});
    });
    Push.addListener('pushNotificationActionPerformed', ({ notification }) => {
        const url = notification.data?.url;
        if (url && new URL(url, location.href).host === location.host) location.href = url;
    });

    // everything read: clear the app's notifications from the tray (and the launcher badge with them)
    const bell = document.querySelector('[data-unread]');
    const syncTray = () => { if (bell && !(Number(bell.dataset.unread) > 0)) Push.removeAllDeliveredNotifications().catch(() => {}); };
    if (bell) new MutationObserver(syncTray).observe(bell, { attributes: true, attributeFilter: ['data-unread'] });
    syncTray();

    try {
        await Push.createChannel({ id: 'naraya', name: 'Notifikasi Naraya', importance: 4, visibility: 1 });
        let { receive } = await Push.checkPermissions();
        if (receive.startsWith('prompt')) ({ receive } = await Push.requestPermissions());
        if (receive === 'granted') await Push.register();
    } catch (e) { /* no Google Play services on this phone: no pushes, the bell still works */ }
})();
