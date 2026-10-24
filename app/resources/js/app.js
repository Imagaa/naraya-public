import './bootstrap';
import './attendance';
import './compress-uploads'; // photos are shrunk on the device before upload

if (document.getElementById('sales-trend-chart') || document.getElementById('top-products-chart')) {
    import('./admin-dashboard');
}

if (document.getElementById('att-checkin-chart')) {
    import('./attendance-charts');
}
