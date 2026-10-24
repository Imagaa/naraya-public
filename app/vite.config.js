import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/pinpoint-map.js', 'resources/js/delivery-photo.js', 'resources/css/marketplace.css', 'resources/js/marketplace.js', 'resources/js/sales-pwa.js', 'resources/js/sales.js', 'resources/css/sales.css', 'resources/css/admin.css', 'resources/js/admin.js', 'resources/js/app-update.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
