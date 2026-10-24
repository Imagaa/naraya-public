import Chart from 'chart.js/auto';

// Rekap Absensi charts. Colours come from the admin tokens so both
// themes stay consistent; lateness uses the reserved status colour (--sale),
// and the tooltip always carries the words, never colour alone.
const token = (name, fallback) => getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
const hm = (m) => `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;

const ink = token('--ink-2', '#4a4a4a');
const muted = token('--muted', '#8a8a8a');
const grid = token('--line', '#e8e8e8');
const ok = token('--ok', '#1f8a4c');
const bad = token('--sale', '#d23c3c');
const brand = token('--brand', '#F0B90B');

const axis = { ticks: { color: muted }, grid: { color: grid }, border: { display: false } };

const checkinEl = document.getElementById('att-checkin-chart');
if (checkinEl) {
    const pts = JSON.parse(checkinEl.dataset.points);
    const times = pts.flatMap((p) => [p.jadwal, p.batas, p.masuk]).filter((v) => v !== null);
    const floor = Math.floor((Math.min(...times) - 30) / 30) * 30;
    const ceil = Math.ceil((Math.max(...times) + 30) / 30) * 30;

    new Chart(checkinEl, {
        type: 'line',
        data: {
            labels: pts.map((p) => p.label),
            datasets: [
                { label: 'Jadwal masuk', data: pts.map((p) => p.jadwal), borderColor: ink, borderWidth: 2, borderDash: [4, 4], pointRadius: 0, spanGaps: true },
                { label: 'Toleransi', data: pts.map((p) => p.batas), borderColor: 'transparent', backgroundColor: `color-mix(in srgb, ${ok} 14%, transparent)`, fill: '-1', pointRadius: 0, spanGaps: true },
                {
                    label: 'Jam masuk',
                    data: pts.map((p) => p.masuk),
                    showLine: false,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBorderColor: token('--surface', '#fff'),
                    pointBorderWidth: 2,
                    pointBackgroundColor: pts.map((p) => (p.state === 'terlambat' ? bad : p.judged ? ok : muted)),
                },
                {
                    label: 'Tidak absen',
                    data: pts.map((p) => (p.state === 'tidak_absen' ? floor : null)),
                    clip: false, // the crosses sit on the axis floor; don't cut them in half
                    showLine: false,
                    pointStyle: 'crossRot',
                    pointRadius: 6,
                    pointBorderWidth: 2,
                    pointBorderColor: bad,
                },
            ],
        },
        options: {
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: axis,
                y: { ...axis, min: floor, max: ceil, ticks: { ...axis.ticks, stepSize: 30, callback: (v) => hm(v) } },
            },
            plugins: {
                legend: { position: 'bottom', labels: { color: ink, usePointStyle: true, filter: (item) => item.text !== 'Toleransi' } },
                tooltip: {
                    filter: (item) => item.datasetIndex === 2 || item.datasetIndex === 0 || (item.datasetIndex === 3 && item.raw !== null),
                    callbacks: {
                        title: (items) => `Tanggal ${items[0].label}`,
                        label: (item) => {
                            const p = pts[item.dataIndex];
                            if (item.datasetIndex === 0) return `Jadwal ${hm(p.jadwal)}`;
                            if (item.datasetIndex === 3) return 'Tidak absen';
                            return `Masuk ${hm(p.masuk)} · ${p.text}`;
                        },
                    },
                },
            },
        },
    });
}

const trendEl = document.getElementById('att-trend-chart');
if (trendEl) {
    const pts = JSON.parse(trendEl.dataset.points);
    new Chart(trendEl, {
        type: 'bar',
        data: {
            labels: pts.map((p) => p.label),
            datasets: [{ label: 'Terlambat', data: pts.map((p) => p.terlambat), backgroundColor: brand, borderRadius: { topLeft: 4, topRight: 4 }, borderSkipped: 'start', maxBarThickness: 28 }],
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (item) => `${item.raw}x terlambat` } } },
            scales: { x: { ...axis, grid: { display: false } }, y: { ...axis, beginAtZero: true, ticks: { ...axis.ticks, precision: 0 } } },
        },
    });
}
