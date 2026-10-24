// Absensi: live camera + live geofence chip in the
// check-in / check-out sheet, any time of day. The server judges everything; what is computed here is
// only what the sales sees while waiting. Check-in also carries the stock picked in the sheet (cfg.items).

const csrf = () => document.querySelector('meta[name="csrf-token"]').getAttribute('content');

function uuid() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') return crypto.randomUUID();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });
}

function metres(lat1, lng1, lat2, lng2) {
    const rad = (d) => (d * Math.PI) / 180;
    const a = Math.sin(rad(lat2 - lat1) / 2) ** 2 + Math.cos(rad(lat1)) * Math.cos(rad(lat2)) * Math.sin(rad(lng2 - lng1) / 2) ** 2;
    return 6371000 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

const distText = (m) => (m >= 1000 ? `${(m / 1000).toFixed(1).replace('.', ',')} km` : `${Math.round(m)} m`);

const wibClock = () => new Date().toLocaleTimeString('id-ID', { timeZone: 'Asia/Jakarta', hour12: false });
const wibDate = () => new Date().toLocaleDateString('id-ID', { timeZone: 'Asia/Jakarta', weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });

document.addEventListener('alpine:init', () => {
    Alpine.data('absenCamera', (cfg) => ({
        stream: null,
        cameraError: '',
        photo: null,
        photoUrl: null,
        token: null,
        tokenAt: 0,
        key: uuid(),
        pos: null,
        gpsError: null,
        near: null,
        meters: null,
        inside: false,
        sending: false,
        status: '',
        clock: wibClock(),

        init() {
            this.openCamera();
            if ('geolocation' in navigator) {
                this.watchId = navigator.geolocation.watchPosition(
                    (p) => this.onPosition(p.coords),
                    (e) => { this.gpsError = e.code === 1 ? 'Izin lokasi ditolak. Aktifkan lokasi untuk aplikasi ini lalu muat ulang.' : null; },
                    { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 },
                );
            } else {
                this.gpsError = 'Perangkat ini tidak mendukung GPS.';
            }
            this.ticker = setInterval(() => { this.clock = wibClock(); }, 1000);
        },

        destroy() {
            this.stream?.getTracks().forEach((t) => t.stop());
            if (this.watchId !== undefined) navigator.geolocation.clearWatch(this.watchId);
            clearInterval(this.ticker);
        },

        async openCamera() {
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
                this.$refs.video.srcObject = this.stream;
            } catch (e) {
                this.cameraError = 'Kamera tidak bisa dibuka. Izinkan akses kamera untuk aplikasi ini di pengaturan browser, lalu muat ulang halaman.';
            }
            await this.fetchToken();
        },

        async fetchToken() {
            const r = await fetch(cfg.sessionUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' } });
            this.token = r.ok ? (await r.json()).token : null;
            this.tokenAt = Date.now();
        },

        onPosition(c) {
            this.pos = c;
            this.gpsError = null;
            const measured = cfg.locations
                .map((l) => ({ l, m: metres(c.latitude, c.longitude, l.lat, l.lng) }))
                .sort((a, b) => a.m - b.m);
            const hit = measured.find((x) => x.m <= x.l.radius_m);
            const best = hit ?? measured[0];
            this.inside = Boolean(hit);
            this.near = best?.l.nama ?? null;
            this.meters = best?.m ?? null;
        },

        /** A fix good enough to stamp on the photo: until then "Ambil foto" waits. */
        get located() {
            return Boolean(this.pos) && !this.gpsError && Math.round(this.pos.accuracy) <= cfg.maxAkurasi;
        },

        get chip() {
            if (this.gpsError) return { tone: 'bad', text: this.gpsError };
            if (!this.pos) return { tone: 'wait', text: 'Mencari GPS…' };
            const acc = Math.round(this.pos.accuracy);
            if (acc > cfg.maxAkurasi) return { tone: 'wait', text: `Sinyal lemah ±${acc} m — coba di area terbuka` };
            if (!this.inside) return { tone: 'bad', text: `Di luar area · ${distText(this.meters)} dari ${this.near}` };
            return { tone: 'ok', text: `Di ${this.near} · ${distText(this.meters)} · ±${acc} m` };
        },

        get stamp() {
            const where = this.pos ? `${this.near ?? '—'} (${distText(this.meters ?? 0)})` : 'Mencari lokasi…';
            const coords = this.pos ? `${this.pos.latitude.toFixed(6)}, ${this.pos.longitude.toFixed(6)} · ±${Math.round(this.pos.accuracy)} m` : '';
            return [`${String(cfg.tipeLabel).toUpperCase()} · ${wibDate()} · ${this.clock} WIB`, where, `${coords} · ${cfg.salesName}`];
        },

        capture() {
            const video = this.$refs.video;
            // 1024 px is plenty for a face + watermark and keeps the upload small on slow phones
            const scale = Math.min(1, 1024 / Math.max(video.videoWidth, video.videoHeight));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(video.videoWidth * scale);
            canvas.height = Math.round(video.videoHeight * scale);
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            canvas.toBlob((blob) => { this.photo = blob; this.photoUrl = URL.createObjectURL(blob); }, 'image/jpeg', 0.8);
        },

        retake() {
            if (this.photoUrl) URL.revokeObjectURL(this.photoUrl);
            this.photo = null;
            this.photoUrl = null;
            this.status = '';
        },

        async submit() {
            if (!this.photo || this.sending || this.chip.tone !== 'ok') return;
            this.sending = true;
            this.status = 'Mengirim…';
            if (!this.token || Date.now() - this.tokenAt > 100000) await this.fetchToken();
            if (cfg.tipe === 'pulang') await window.salesTrackFlush?.(); // the day's GPS points first, so the recap is whole

            const fd = new FormData();
            fd.append('tipe', cfg.tipe);
            fd.append('token', this.token ?? '');
            fd.append('latitude', this.pos.latitude);
            fd.append('longitude', this.pos.longitude);
            fd.append('akurasi', this.pos.accuracy);
            fd.append('foto', this.photo, 'absen.jpg');
            fd.append('idempotency_key', this.key);
            if (this.$refs.note?.value) fd.append('catatan', this.$refs.note.value);
            if (cfg.items && Object.keys(cfg.items).length) fd.append('items', JSON.stringify(cfg.items));

            try {
                const r = await fetch(cfg.submitUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' }, body: fd });
                const body = await r.json().catch(() => ({}));
                if (r.ok) {
                    window.location.href = cfg.doneUrl;
                    return;
                }
                this.status = body.message || (body.errors ? Object.values(body.errors)[0][0] : 'Absen gagal, coba lagi.');
                this.token = null; // a rejected submit spent its token
            } catch (e) {
                this.status = 'Tidak ada sinyal. Absen butuh koneksi internet — coba lagi.';
            }
            this.sending = false;
        },
    }));
});
