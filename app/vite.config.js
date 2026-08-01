import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    server: {
        watch: {
            // Vite ignores node_modules by default but knows nothing about
            // vendor/, which is ~3,700 directories here — 1,700 of them the AWS
            // SDK's per-service data dirs. Watching them exhausts the inotify
            // watch limit and the dev server dies with ENOSPC. Nothing under
            // these paths is a Vite input.
            ignored: ['**/vendor/**', '**/storage/**', '**/public/build/**'],
        },
    },
    plugins: [
        laravel({
            input: 'resources/js/app.ts',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
