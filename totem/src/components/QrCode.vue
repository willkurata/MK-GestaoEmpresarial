<script setup lang="ts">
import { createQr, qrPath } from '@totem/lib/qr';
import { computed } from 'vue';

/**
 * Mostra um QR Code. Se o servidor mandar o SVG pronto (BaconQrCode), usa
 * ele; senão gera no navegador a partir de `text`.
 */
const props = defineProps<{
    text: string;
    svg?: string | null;
    label: string;
}>();

const QUIET = 4;

const generated = computed(() => {
    if (props.svg) {
        return null;
    }

    const matrix = createQr(props.text, 'M');

    return { size: matrix.size + QUIET * 2, path: qrPath(matrix, QUIET) };
});
</script>

<template>
    <div
        role="img"
        :aria-label="label"
        class="aspect-square w-full overflow-hidden rounded-2xl bg-white p-1"
    >
        <!-- SVG gerado pelo nosso backend (BaconQrCode), não por usuário. -->
        <div v-if="svg" class="size-full [&>svg]:size-full" v-html="svg" />
        <svg
            v-else-if="generated"
            :viewBox="`0 0 ${generated.size} ${generated.size}`"
            shape-rendering="crispEdges"
            class="size-full"
        >
            <rect
                :width="generated.size"
                :height="generated.size"
                fill="#fff"
            />
            <path :d="generated.path" fill="#000" />
        </svg>
    </div>
</template>
