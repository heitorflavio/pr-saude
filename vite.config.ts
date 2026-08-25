import vue from '@vitejs/plugin-vue';
import autoprefixer from 'autoprefixer';
import laravel from 'laravel-vite-plugin';
import path from 'path';
import tailwindcss from 'tailwindcss';
import { defineConfig, loadEnv } from 'vite';

export default defineConfig(({ mode }) => {
    // Le o .env do Laravel (mesma raiz) para nao duplicar a configuracao de ambiente
    // em dois lugares: quem manda e o APP_URL.
    const env = loadEnv(mode, process.cwd(), '');
    const appUrl = env.APP_URL ?? 'http://localhost';

    // Herd serve o site em https://pr-saude.test e o laravel-vite-plugin liga TLS
    // sozinho ao achar o certificado em ~/.config/herd/.../Certificates. No Sail o
    // site e http://localhost:8080 e essa deteccao gera mixed content — por isso ela
    // e desligada explicitamente fora do Herd.
    const usingHerd = appUrl.startsWith('https://');

    const vitePort = Number(env.VITE_PORT ?? 5173);
    const viteHmrHost = env.VITE_HMR_HOST ?? 'localhost';

    return {
        plugins: [
            laravel({
                input: ['resources/js/app.ts'],
                refresh: true,
                detectTls: usingHerd ? undefined : false,
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
        // No Herd o plugin ja resolve host, porta e origin a partir do certificado;
        // so o Sail precisa ser amarrado (container -> browser do host).
        server: usingHerd
            ? {
                  watch: {
                      ignored: ['**/.agents/**', '**/.claude/**', '**/.cursor/**', '**/.junie/**', '**/vendor/**'],
                  },
              }
            : {
                  host: '0.0.0.0',
                  port: vitePort,
                  strictPort: true,
                  origin: `http://${viteHmrHost}:${vitePort}`,
                  cors: {
                      origin: [appUrl, 'http://localhost:8080', 'http://127.0.0.1:8080'],
                  },
                  hmr: {
                      host: viteHmrHost,
                      clientPort: vitePort,
                  },
                  watch: {
                      ignored: ['**/.agents/**', '**/.claude/**', '**/.cursor/**', '**/.junie/**', '**/vendor/**'],
                  },
              },
    };
});
