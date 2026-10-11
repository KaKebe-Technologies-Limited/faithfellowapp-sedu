import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/scss/app.scss',
                'resources/css/faithfellows.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                // Bootstrap 5.3 still uses @import / global functions.
                silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'mixed-decls', 'if-function'],
                quietDeps: true,
            },
        },
    },
    build: {
        chunkSizeWarningLimit: 900,
    },
});
