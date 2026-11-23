import { fileURLToPath, URL } from 'node:url';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vite';

/**
 * CyberCoffe App — frontend independente do MK Gestão.
 *
 * Reaproveita apenas as dependências já instaladas na raiz (vue, vite,
 * tailwindcss, @lucide/vue); não altera o build do Laravel.
 *
 * Dev:   npx vite --config cybercoffe/vite.config.ts
 * Build: npx vite build --config cybercoffe/vite.config.ts
 */
export default defineConfig({
    root: fileURLToPath(new URL('.', import.meta.url)),
    base: './',
    envPrefix: 'VITE_CYBERCOFFE_',
    plugins: [tailwindcss(), vue()],
    resolve: {
        alias: {
            '@cc': fileURLToPath(new URL('./src', import.meta.url)),
        },
    },
    server: {
        port: 5174,
        strictPort: false,
    },
    build: {
        outDir: 'dist',
        emptyOutDir: true,
    },
});
