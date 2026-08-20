import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import lucidePreprocess from 'vite-plugin-lucide-preprocess';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
    plugins: [
        lucidePreprocess(),
        laravel({
            input: ['resources/js/app.js'],
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
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    server: {
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
        watch: {
            ignored: [
                '**/storage/**',
                '**/public/build/**',
                '**/vendor/**',
            ],
        },
    },
});
