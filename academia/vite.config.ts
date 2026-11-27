import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';

/**
 * App de Gerenciamento de Academia — projeto independente do MK Gestão.
 * Reaproveita as dependências instaladas na raiz (Vue, Tailwind, Lucide),
 * sem adicionar pacotes novos.
 */
export default defineConfig({
    root: fileURLToPath(new URL('.', import.meta.url)),
    base: './',
    plugins: [tailwindcss(), vue()],
    resolve: {
        alias: {
            '@academia': fileURLToPath(new URL('./src', import.meta.url)),
        },
    },
    server: {
        port: 5180,
    },
    test: {
        include: ['src/**/*.test.ts'],
        environment: 'node',
    },
});
