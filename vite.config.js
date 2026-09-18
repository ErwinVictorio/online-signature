import { defineConfig } from 'vite';
import { fileURLToPath } from 'node:url';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.jsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    server: {
        // Bind to IPv4 so the dev-server URL written to public/hot matches
        // `php artisan serve` (127.0.0.1). Without this, Vite may bind to the
        // IPv6 loopback ([::1]) and the browser fails to load the injected
        // stylesheet, leaving the app unstyled.
        host: '127.0.0.1',
    },
});
