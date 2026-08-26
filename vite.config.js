import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/scss/todays-retail.scss',
                'resources/js/todays-retail.js',
                'resources/js/pages/operations-schedule.js',
            ],
            refresh: true,
        }),
    ],
});
