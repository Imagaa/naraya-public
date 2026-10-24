// Field work with no signal. With the phone offline — or the
// "Kerja offline" switch on, for weak signal — the Kerja buttons marked data-offline open a light form here
// instead of the Livewire sheet. What is saved waits on the phone (IndexedDB outbox, photos as Blobs) and is
// sent to /offline/sync, oldest first, whenever there is signal. The forms check against a snapshot from
// /offline/data, with every waiting item already applied to it.
import { getLocation } from './geolocation';
import { watermarkAndCompressImage } from './image-compression';

const SNAP = 'naraya-offline-data';

// --- Outbox (IndexedDB) -------------------------------------------------------
let opening = null;
const db = () => opening ??= new Promise((resolve, reject) => {
    const req = indexedDB.open('naraya-sales', 1);
    req.onupgradeneeded = () => req.result.createObjectStore('outbox', { keyPath: 'uuid' });
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
});
const tx = async (mode, fn) => {
    const store = (await db()).transaction('outbox', mode).objectStore('outbox');
    return new Promise((resolve, reject) => {
        const req = fn(store);
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
};
const outbox = {
    all: async () => (await tx('readonly', (s) => s.getAll())).sort((a, b) => a.at.localeCompare(b.at)),
    put: (item) => tx('readwrite', (s) => s.put(item)),
    remove: (uuid) => tx('readwrite', (s) => s.delete(uuid)),
};

// --- Snapshot -----------------------------------------------------------------
const loadSnap = () => { try { return JSON.parse(localStorage.getItem(SNAP)); } catch (e) { return null; } };
const saveSnap = (snap) => { try { localStorage.setItem(SNAP, JSON.stringify(snap)); } catch (e) { /* full or blocked */ } };

/** What a waiting item does to the books, so the next offline form checks against the right numbers. */
function apply(snap, item) {
    const p = item.payload;
    const store = snap.stores.find((s) => s.id === p.store);
    const take = (items) => Object.entries(items || {}).forEach(([id, q]) => { snap.carried[id] = (snap.carried[id] || 0) - q; });
    if (item.type === 'awal' && store) { store.opened = true; store.at = { ...p.items }; }
    if (item.type === 'titip' && store) {
        // what was left comes back with the sales person; the store keeps only the fresh lot
        store.at = Object.fromEntries(Object.entries(p.titip).filter(([, q]) => q > 0));
        store.last = item.at;
        take(p.titip);
        store.stop = null;
    }
    if (item.type === 'nota') { take(p.items); if (store) store.stop = null; }
    if (item.type === 'tutup' && store) store.stop = null;
    if (item.type === 'toko') snap.stores.push({ id: `baru-${item.uuid}`, name: p.name, opened: false, stop: null, at: {}, unsynced: true });
    snap.carried = Object.fromEntries(Object.entries(snap.carried).filter(([, q]) => q > 0));
}

const desk = () => document.getElementById('offline-desk');
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
const changed = () => window.dispatchEvent(new CustomEvent('offline-outbox'));

async function refresh() {
    if (!navigator.onLine || !desk()) return;
    try {
        const r = await fetch(desk().dataset.dataUrl, { headers: { Accept: 'application/json' } });
        if (!r.ok) return;
        const snap = await r.json();
        (await outbox.all()).filter((i) => !i.error).forEach((i) => apply(snap, i));
        saveSnap(snap);
        changed();
    } catch (e) { /* no signal after all */ }
}

let syncing = null;
function sync() {
    if (syncing || !desk()) return syncing;
    syncing = (async () => {
        let sent = 0;
        for (const item of (await outbox.all()).filter((i) => !i.error)) {
            const body = new FormData();
            body.append('uuid', item.uuid);
            body.append('type', item.type);
            body.append('line', item.line);
            body.append('recorded_at', item.at);
            body.append('payload', JSON.stringify(item.payload));
            if (item.photo) body.append('photo', item.photo, 'foto.jpg');
            let r;
            try {
                r = await fetch(desk().dataset.syncUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body });
            } catch (e) { break; } // no signal: try again later
            if (r.ok) { await outbox.remove(item.uuid); sent++; continue; }
            if (r.status !== 422) break; // logged out, expired page, server trouble: try again later
            item.error = (await r.json().catch(() => ({}))).message || 'Ditolak server.';
            await outbox.put(item);
        }
        if (sent) {
            await refresh();
            window.Livewire?.dispatch('offline-synced'); // Kerja re-renders with what just arrived
        }
        changed();
    })().finally(() => { syncing = null; });
    return syncing;
}

const uuid = () => crypto.randomUUID?.() ?? 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
});
const rp = (v) => 'Rp ' + Math.round(v).toLocaleString('id-ID');

document.addEventListener('alpine:init', () => {
    Alpine.data('salesOffline', () => ({
        online: navigator.onLine,
        items: [],
        snap: null,
        flash: '',
        // the open form
        form: null, // toko | awal | titip | nota | tutup | toko-baru
        store: null,
        step: 'hitung',
        rows: {}, // rows[group][productId] = { n } — pcs for "remaining", whole units (lembar) for the rest
        method: 'cash',
        photo: null,
        preview: null,
        fields: { name: '', owner: '', phone: '', address: '', note: '', search: '' },
        pin: null,
        error: '',
        busy: false,

        // offline only when the phone really has no signal (the manual switch was removed on 2026-10-23)
        get on() { return !this.online; },

        init() {
            this.snap = loadSnap();
            window.addEventListener('online', () => { this.online = true; sync(); refresh(); });
            window.addEventListener('offline', () => { this.online = false; });
            window.addEventListener('offline-outbox', () => this.reload());
            // the Kerja buttons: capture phase, so this runs before Livewire's click handler
            this.onClick = (e) => {
                const guard = e.target.closest('[data-offline-guard]');
                if (guard && !guard.dataset.cleared) return this.guard(e, guard);
                const el = e.target.closest('[data-offline]');
                if (!el || !this.on) return;
                e.preventDefault();
                e.stopImmediatePropagation();
                const [form, id] = el.dataset.offline.split(':');
                this.open(form, id ? Number(id) : null);
            };
            document.addEventListener('click', this.onClick, true);
            // what Livewire changed on Kerja (a nota, stock taken…) shows up in the snapshot shortly after
            let timer = null;
            this.onSheet = () => { clearTimeout(timer); timer = setTimeout(refresh, 800); };
            ['sheet-done', 'sheet-open'].forEach((name) => window.addEventListener(name, this.onSheet));
            this.onPop = () => { if (this.form) this.close(true); };
            window.addEventListener('popstate', this.onPop);
            this.timer = setInterval(() => { if (this.items.some((i) => !i.error)) sync(); }, 60000);
            this.reload();
            refresh().then(() => sync());
        },
        destroy() {
            document.removeEventListener('click', this.onClick, true);
            ['sheet-done', 'sheet-open'].forEach((name) => window.removeEventListener(name, this.onSheet));
            window.removeEventListener('popstate', this.onPop);
            clearInterval(this.timer);
        },

        async reload() {
            this.snap = loadSnap();
            this.items = (await outbox.all().catch(() => [])).map(({ uuid: id, label, error, at }) => ({ uuid: id, label, error, at }));
        },
        sendNow() { this.flash = ''; sync(); },
        async drop(id) { await outbox.remove(id); await refresh(); this.reload(); },

        /** Absen pulang: everything recorded offline must be on the server first (cash, recap). */
        async guard(e, el) {
            if (!this.items.length) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            this.flash = 'Mengirim catatan offline dulu…';
            await sync();
            await this.reload();
            if (this.items.length) {
                this.flash = this.items.some((i) => i.error)
                    ? 'Ada catatan offline yang ditolak. Hapus atau perbaiki dulu sebelum absen pulang.'
                    : 'Masih ada catatan offline yang belum terkirim. Cari sinyal, lalu coba lagi.';
                return;
            }
            this.flash = '';
            el.dataset.cleared = '1';
            el.click();
            delete el.dataset.cleared;
        },

        // --- the forms ---------------------------------------------------------------
        product(id) { return this.snap?.products[id] ?? { name: '?', price: 0, unit: 'pcs', ppu: 1 }; },
        label(id, pcs) {
            const p = this.product(id);
            if (p.ppu === 1) return `${pcs} ${p.unit}`;
            const whole = Math.floor(pcs / p.ppu);
            const rest = pcs % p.ppu;
            return [whole ? `${whole} ${p.unit}` : '', rest ? `${rest} pcs` : ''].filter(Boolean).join(' ') || `0 ${p.unit}`;
        },
        value(id, pcs) { const p = this.product(id); return Math.round((pcs * p.price) / p.ppu); },
        rp,
        pcs(group, id) {
            const n = Math.max(0, parseInt(this.rows[group]?.[id]?.n) || 0);
            return group === 'remaining' ? n : n * this.product(id).ppu;
        },
        step1(group, id, by) {
            const row = this.rows[group][id];
            let n = Math.max(0, (parseInt(row.n) || 0) + by);
            const top = this.top(group, id);
            if (top !== null) n = Math.min(n, top);
            row.n = n ? String(n) : (group === 'remaining' ? '0' : '');
        },
        /** the most the stepper goes to: the store's count for "remaining", whole units carried for titip/nota */
        top(group, id) {
            if (group === 'remaining') return this.store.at[id];
            if (group === 'awal') return null;
            return Math.floor((this.snap.carried[id] || 0) / this.product(id).ppu);
        },
        when(iso) { return iso ? new Date(iso).toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : ''; },
        picked(group) {
            return Object.fromEntries(Object.keys(this.rows[group] || {}).map((id) => [id, this.pcs(group, id)]).filter(([, q]) => q > 0));
        },
        carriedIds() { return Object.keys(this.snap?.carried || {}); },
        catalog() {
            const q = this.fields.search.trim().toLowerCase();
            return Object.keys(this.snap?.products || {}).filter((id) => !q || this.product(id).name.toLowerCase().includes(q));
        },

        open(form, id) {
            this.snap = loadSnap();
            this.flash = '';
            this.error = '';
            if (!this.snap) { this.flash = 'Data offline belum ada. Buka halaman ini sekali saat ada sinyal.'; return; }
            this.store = id ? this.snap.stores.find((s) => s.id === id) : null;
            if (id && !this.store) { this.flash = 'Toko ini belum ada di data offline. Buka halaman ini sekali saat ada sinyal.'; return; }
            // a store's first visit records what is already there before anything else (same gate as online)
            if (this.store && !this.store.opened && (form === 'titip' || form === 'nota')) form = 'awal';
            this.form = form;
            this.step = 'hitung';
            this.method = 'cash';
            this.photo = null;
            this.preview = null;
            this.pin = null;
            this.fields = { name: '', owner: '', phone: '', address: '', note: '', search: '' };
            this.rows = { awal: {}, items: {}, remaining: {}, titip: {} };
            if (form === 'titip') Object.entries(this.store.at).forEach(([pid, had]) => { this.rows.remaining[pid] = { n: String(had) }; });
            if (form === 'awal') Object.keys(this.snap.products).forEach((pid) => { this.rows.awal[pid] = { n: '' }; });
            if (form === 'nota') this.carriedIds().forEach((pid) => { this.rows.items[pid] = { n: '' }; });
            if (form === 'titip') this.carriedIds().forEach((pid) => { this.rows.titip[pid] = { n: '' }; });
            if (form === 'toko-baru') this.locate();
            document.body.classList.add('sheet-open');
            if (!history.state?.offlineSheet) history.pushState({ offlineSheet: true }, '');
        },
        close(popped = false) {
            this.form = null;
            document.body.classList.remove('sheet-open');
            if (!popped && history.state?.offlineSheet) history.back();
        },

        async locate() {
            this.pin = 'mencari';
            const loc = await getLocation(15000);
            this.pin = loc.latitude !== null ? { lat: loc.latitude, lng: loc.longitude } : 'gagal';
        },
        async capture(event) {
            const file = event.target.files[0];
            event.target.value = '';
            if (!file) return;
            this.busy = true;
            try {
                const loc = await getLocation();
                const stamp = new Date().toLocaleString('id-ID');
                this.photo = await watermarkAndCompressImage(file, loc.latitude !== null
                    ? `${loc.latitude.toFixed(5)}, ${loc.longitude.toFixed(5)} — ${stamp}` : `Lokasi tidak tersedia — ${stamp}`);
                if (this.preview) URL.revokeObjectURL(this.preview);
                this.preview = URL.createObjectURL(this.photo);
            } catch (e) {
                this.error = 'Gagal memproses foto. Coba lagi.';
            } finally {
                this.busy = false;
            }
        },

        sold() {
            return Object.fromEntries(Object.entries(this.store?.at || {})
                .map(([id, had]) => [id, had - this.pcs('remaining', id)]).filter(([, q]) => q > 0));
        },
        total(items) { return Object.entries(items).reduce((sum, [id, q]) => sum + this.value(id, q), 0); },

        /** titip: hitung → titip → bayar, checking each step like the online sheet does */
        next() {
            this.error = '';
            if (this.step === 'hitung') {
                for (const [id, had] of Object.entries(this.store.at)) {
                    if (this.pcs('remaining', id) > had) return (this.error = `${this.product(id).name}: sisa lebih dari yang dititip.`);
                }
                this.step = 'titip';
                return;
            }
            if (this.overCarried('titip')) return;
            this.step = 'bayar';
        },
        overCarried(group) {
            for (const [id, q] of Object.entries(this.picked(group))) {
                if (q > (this.snap.carried[id] || 0)) return (this.error = `${this.product(id).name}: Anda hanya membawa ${this.label(id, this.snap.carried[id] || 0)}.`);
            }
            return false;
        },

        async save() {
            this.error = '';
            const s = this.store;
            let payload;
            let label;
            const needsProof = this.method !== 'cash';
            if (this.form === 'awal') {
                payload = { store: s.id, items: this.picked('awal') };
                label = `Barang awal ${s.name}`;
            } else if (this.form === 'titip') {
                const sold = this.sold();
                if (Object.keys(sold).length && needsProof && !this.photo) return (this.error = 'Ambil foto bukti transfer.');
                payload = { store: s.id, remaining: Object.fromEntries(Object.keys(s.at).map((id) => [id, this.pcs('remaining', id)])), titip: this.picked('titip'), method: this.method };
                label = `Kunjungan ${s.name}` + (Object.keys(sold).length ? ` · ${rp(this.total(sold))}` : '');
            } else if (this.form === 'nota') {
                const items = this.picked('items');
                if (!Object.keys(items).length) return (this.error = 'Isi jumlah minimal satu barang.');
                if (this.overCarried('items')) return;
                if (needsProof && !this.photo) return (this.error = 'Ambil foto bukti transfer.');
                payload = { store: s.id, items, method: this.method };
                label = `Nota ${s.name} · ${rp(this.total(items))}`;
            } else if (this.form === 'tutup') {
                if (!this.photo) return (this.error = 'Foto toko yang tutup wajib diambil.');
                payload = { store: s.id, note: this.fields.note };
                label = `${s.name} tutup`;
            } else if (this.form === 'toko-baru') {
                const f = this.fields;
                if (!f.name.trim() || !f.owner.trim() || !f.address.trim()) return (this.error = 'Isi nama toko, pemilik dan alamat.');
                if (!/^(\+?62|0)8[0-9\- ]{7,14}$/.test(f.phone.trim())) return (this.error = 'Masukkan nomor HP Indonesia, mis. 0812xxxxxxxx.');
                if (!this.pin?.lat) return (this.error = 'Titik lokasi belum terbaca. Nyalakan GPS lalu ketuk "Ambil titik".');
                if (!this.photo) return (this.error = 'Foto depan warung wajib.');
                payload = { name: f.name.trim(), owner: f.owner.trim(), phone: f.phone.trim(), address: f.address.trim(), lat: this.pin.lat, lng: this.pin.lng };
                label = `Toko baru ${payload.name}`;
            }
            const item = {
                uuid: uuid(), type: this.form === 'toko-baru' ? 'toko' : this.form, line: this.snap.line.value, at: new Date().toISOString(),
                payload, label, error: null,
                photo: (this.form === 'tutup' || this.form === 'toko-baru' || needsProof) ? this.photo : null,
            };
            this.busy = true;
            try {
                await outbox.put(item);
            } catch (e) {
                this.busy = false;
                return (this.error = 'Memori HP penuh. Kosongkan sedikit ruang lalu coba lagi.');
            }
            apply(this.snap, item);
            saveSnap(this.snap);
            this.busy = false;
            this.close();
            this.flash = `${label} disimpan di HP — dikirim otomatis saat ada sinyal.`;
            await this.reload();
            sync();
        },
    }));
});
