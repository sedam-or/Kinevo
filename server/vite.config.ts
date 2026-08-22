import { defineConfig } from 'vite';
import { fileURLToPath, URL } from 'node:url';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import { viteShellPrecache } from './vite-shell-precache-plugin';

export default defineConfig({
    // Compile-time e2e seam switch: `KINEVO_E2E_SEAM=1 npm run build` embeds
    // window.__kinevoCanvasAdapter (browser-test only); plain prod builds get
    // `false` and dead-code elimination removes the seam entirely.
    define: {
        __KINEVO_E2E_SEAM__: JSON.stringify(process.env.KINEVO_E2E_SEAM === '1'),
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/offline/sw.ts',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        vue(),
        tailwindcss(),
        viteShellPrecache(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    oxc: {
        jsx: { runtime: 'automatic' },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});