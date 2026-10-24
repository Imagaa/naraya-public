import Chart from 'chart.js/auto';

// Colours come from the design tokens (marketplace/tokens.css), never hard-coded,
// so the dashboard and Laporan charts match the rest of the admin.
const token = (name, fallback) => getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
const brand = token('--brand', '#F0B90B');
const axis = { ticks: { color: token('--muted', '#6B7480') }, grid: { color: token('--line', '#E6E8EB') }, border: { display: false } };

const trendEl = document.getElementById('sales-trend-chart');
const productsEl = document.getElementById('top-products-chart');

let trendChart = null;
let productsChart = null;

if (trendEl) {
    const points = JSON.parse(trendEl.dataset.points);
    trendChart = new Chart(trendEl, {
        type: 'line',
        data: {
            labels: points.map((p) => p.label),
            datasets: [{
                data: points.map((p) => p.total),
                borderColor: brand,
                borderWidth: 2,
                backgroundColor: `color-mix(in srgb, ${brand} 15%, transparent)`,
                tension: 0.3,
                fill: true,
                pointRadius: 2,
            }],
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: axis,
                y: {
                    ...axis,
                    ticks: {
                        ...axis.ticks,
                        callback: (v) => 'Rp ' + Number(v).toLocaleString('id-ID'),
                    },
                },
            },
        },
    });
}

if (productsEl) {
    const points = JSON.parse(productsEl.dataset.points);
    productsChart = new Chart(productsEl, {
        type: 'bar',
        data: {
            labels: points.map((p) => p.label),
            datasets: [{
                data: points.map((p) => p.total),
                backgroundColor: brand,
                borderRadius: { topRight: 4, bottomRight: 4 },
                borderSkipped: 'start',
                maxBarThickness: 24,
            }],
        },
        options: {
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: { x: { ...axis, ticks: { ...axis.ticks, precision: 0 } }, y: { ...axis, grid: { display: false } } },
        },
    });
}

if (window.Livewire) {
    window.Livewire.on('dashboard-charts-updated', ({ trend, topProducts }) => {
        if (trendChart && trend) {
            trendChart.data.labels = trend.map((p) => p.label);
            trendChart.data.datasets[0].data = trend.map((p) => p.total);
            trendChart.update();
        }
        if (productsChart && topProducts) {
            productsChart.data.labels = topProducts.map((p) => p.label);
            productsChart.data.datasets[0].data = topProducts.map((p) => p.total);
            productsChart.update();
        }
    });
}
