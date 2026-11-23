<script setup lang="ts">
import { computed } from 'vue';

/**
 * Bloco decorativo de pixels (assinatura visual do CyberCoffe).
 * O padrão é determinístico a partir de `seed` para não "piscar" entre renders.
 */
const props = withDefaults(
    defineProps<{
        columns?: number;
        rows?: number;
        size?: number;
        seed?: number;
        tone?: 'neon' | 'sky' | 'mixed';
    }>(),
    { columns: 8, rows: 4, size: 8, seed: 7, tone: 'mixed' },
);

const cells = computed(() => {
    let state = props.seed * 9301 + 49297;
    const random = () => {
        state = (state * 9301 + 49297) % 233280;

        return state / 233280;
    };

    return Array.from({ length: props.columns * props.rows }, (_, index) => {
        const value = random();
        const column = index % props.columns;
        const fade = 1 - column / props.columns;
        let color = 'transparent';

        if (value < 0.55 * fade + 0.1) {
            const useSky =
                props.tone === 'sky' ||
                (props.tone === 'mixed' && value < 0.12);
            color = useSky ? 'var(--color-sky)' : 'var(--color-neon)';
        }

        return { color, opacity: 0.25 + value * 0.75 };
    });
});
</script>

<template>
    <div
        aria-hidden="true"
        class="pointer-events-none grid"
        :style="{
            gridTemplateColumns: `repeat(${columns}, ${size}px)`,
            gap: '2px',
        }"
    >
        <span
            v-for="(cell, index) in cells"
            :key="index"
            :style="{
                width: `${size}px`,
                height: `${size}px`,
                backgroundColor: cell.color,
                opacity: cell.opacity,
            }"
        />
    </div>
</template>
