import vue from '@vitejs/plugin-vue';
import autoprefixer from 'autoprefixer';
import laravel from 'laravel-vite-plugin';
import path from 'path';
import tailwindcss from 'tailwindcss';
import { defineConfig } from 'vite';

const vitePort = Number(process.env.VITE_PORT ?? 5173);
const viteHmrHost = process.env.VITE_HMR_HOST ?? 'localhost';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
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
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
    css: {
        postcss: {
            plugins: [tailwindcss, autoprefixer],
        },
    },
    server: {
        host: '0.0.0.0',
        port: vitePort,
        strictPort: true,
        origin: `http://${viteHmrHost}:${vitePort}`,
        hmr: {
            host: viteHmrHost,
            clientPort: vitePort,
        },
    },
});
