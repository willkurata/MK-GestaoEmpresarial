import { fileURLToPath, URL } from 'node:url';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vite';

/**
 * Totem de Impressão Autônoma 24h (WIL-47) — frontend independente do
 * MK Gestão. Reaproveita só dependências já instaladas na raiz (vue, vite,
 * tailwindcss, @lucide/vue); não altera o build do Laravel.
 *
 * Dev:   npx vite --config totem/vite.config.ts
 * Build: npx vite build --config totem/vite.config.ts
 */
export default defineConfig({
    root: fileURLToPath(new URL('.', import.meta.url)),
    base: './',
    envPrefix: 'VITE_TOTEM_',
    plugins: [tailwindcss(), vue()],
    resolve: {
        alias: {
            '@totem': fileURLToPath(new URL('./src', import.meta.url)),
        },
    },
    server: {
        port: 5185,
        strictPort: false,
        host: true,
    },
    build: {
        outDir: 'dist',
        emptyOutDir: true,
    },
});
