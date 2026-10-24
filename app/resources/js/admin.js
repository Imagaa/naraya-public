// Admin portal JS: a push-only service worker (no offline caching on admin)
// and the device push toggle on /notifikasi.
import './push-toggle';
import './logout-cleanup';
import './admin-table-labels';

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/admin-sw.js');
}

// Sidebar badges: refresh every minute (and when the tab comes back) instead of only on page load.
async function refreshNavBadges() {
    if (document.visibilityState !== 'visible') return;
    let counts;
    try {
        const response = await fetch('/nav-badges', { headers: { Accept: 'application/json' } });
        if (!response.ok) return;
        counts = await response.json();
    } catch (error) {
        return;
    }
    for (const [label, count] of Object.entries(counts)) {
        const link = document.querySelector(`.adm-nav-link[data-nav="${CSS.escape(label)}"]`);
        if (!link) continue;
        let badge = link.querySelector('.adm-nav-badge');
        if (count > 0 && !badge) {
            badge = document.createElement('span');
            badge.className = 'adm-nav-badge';
            link.append(badge);
        }
        if (badge) count > 0 ? (badge.textContent = count) : badge.remove();
    }
}
setInterval(refreshNavBadges, 60000);
document.addEventListener('visibilitychange', refreshNavBadges);
