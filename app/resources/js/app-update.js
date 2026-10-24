// The Naraya Sales Android app updates itself: on start it asks
// /aplikasi/versi and offers a newer APK. From v1.0.2 the app has its own AppUpdater plugin: "Perbarui" downloads the
// APK inside the app with a progress bar and opens Android's installer right away (one "Pasang" tap, then "Buka").
// Older apps fall back to the phone's browser for the download. Loaded by sales.js and by the staff login page, so a
// phone on an old version is offered the update even before anyone logs in.
(async function checkAppUpdate() {
    const cap = window.Capacitor;
    if (!cap?.isNativePlatform?.() || !cap.Plugins?.App) return;
    try {
        const [info, release] = await Promise.all([
            cap.Plugins.App.getInfo(),
            fetch('/aplikasi/versi', { headers: { Accept: 'application/json' }, cache: 'no-store' }).then((r) => r.json()),
        ]);
        if (!(release.versionCode > parseInt(info.build, 10))) return;
        if (sessionStorage.getItem('naraya-update-later') === String(release.versionCode)) return;

        const bar = document.createElement('div');
        bar.className = 'app-update';
        bar.setAttribute('role', 'status');
        const text = document.createElement('span');
        const offer = `Versi aplikasi baru ${release.versionName} tersedia` + (release.notes ? ` — ${release.notes}` : '');
        text.textContent = offer;
        const later = document.createElement('button');
        later.type = 'button';
        later.className = 'btn btn-secondary btn-sm';
        later.textContent = 'Nanti';
        later.addEventListener('click', () => { sessionStorage.setItem('naraya-update-later', String(release.versionCode)); bar.remove(); });

        const updater = cap.Plugins.AppUpdater;
        let update;
        if (updater) {
            update = document.createElement('button');
            update.type = 'button';
            let downloaded = false;
            const install = async () => {
                const { needsPermission } = await updater.install();
                text.textContent = needsPermission
                    ? 'Izinkan "Instal aplikasi tak dikenal" untuk Naraya, kembali ke sini, lalu ketuk Pasang.'
                    : 'Ketuk Pasang di layar Android, lalu Buka.';
                update.textContent = 'Pasang';
                update.disabled = false;
            };
            update.addEventListener('click', async () => {
                update.disabled = true;
                later.hidden = true;
                bar.setAttribute('data-hold-refresh', ''); // no automatic page reload mid-download (sales-pwa.js)
                try {
                    if (!downloaded) {
                        const progress = await updater.addListener('progress', ({ percent }) => {
                            text.textContent = percent >= 0 ? `Mengunduh pembaruan… ${percent}%` : 'Mengunduh pembaruan…';
                        });
                        text.textContent = 'Mengunduh pembaruan…';
                        try { await updater.download({ url: release.apkUrl }); } finally { progress.remove(); }
                        downloaded = true;
                    }
                    await install();
                } catch (e) {
                    text.textContent = (e?.message || 'Pembaruan gagal.') + ' Coba lagi.';
                    update.textContent = 'Coba lagi';
                    update.disabled = false;
                    later.hidden = false;
                    bar.removeAttribute('data-hold-refresh');
                    if (downloaded && !bar.querySelector('.app-update-browser')) {
                        // the installer would not open: the old way still works
                        const browser = document.createElement('a');
                        browser.className = 'btn btn-secondary btn-sm app-update-browser';
                        browser.href = release.apkUrl;
                        browser.target = '_blank';
                        browser.rel = 'noopener';
                        browser.textContent = 'Unduh lewat browser';
                        later.before(browser);
                    }
                }
            });
        } else {
            update = document.createElement('a');
            update.href = release.apkUrl;
            update.target = '_blank'; // another host: Capacitor hands it to the phone's browser, which downloads the APK
            update.rel = 'noopener';
        }
        update.className = 'btn btn-primary btn-sm';
        update.textContent = 'Perbarui';
        bar.append(text, update, later);
        document.body.append(bar);
    } catch (e) { /* offline: ask again next time */ }
})();
