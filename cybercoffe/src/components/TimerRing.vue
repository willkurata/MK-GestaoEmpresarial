<script setup lang="ts">
import { formatClock } from '@cc/lib/format';
import { computed } from 'vue';

/**
 * Timer gigante com anel segmentado ("pixels" em volta do relógio).
 * Os números usam `tabular-nums` para não dançarem durante a contagem.
 */
const props = defineProps<{
    remainingSeconds: number;
    totalSeconds: number;
    tone: 'neon' | 'amber' | 'violet' | 'idle';
    caption: string;
}>();

const SEGMENTS = 60;
const RADIUS = 46;

const progress = computed(() =>
    props.totalSeconds > 0
        ? Math.min(1, Math.max(0, props.remainingSeconds / props.totalSeconds))
        : 0,
);

const litSegments = computed(() => Math.ceil(progress.value * SEGMENTS));

const color = computed(
    () =>
        ({
            neon: 'var(--color-neon)',
            amber: 'var(--color-amber)',
            violet: 'var(--color-violet)',
            idle: 'var(--color-idle)',
        })[props.tone],
);

const segments = computed(() =>
    Array.from({ length: SEGMENTS }, (_, index) => {
        const angle = (index / SEGMENTS) * Math.PI * 2 - Math.PI / 2;

        return {
            x: 50 + RADIUS * Math.cos(angle),
            y: 50 + RADIUS * Math.sin(angle),
            rotate: (index / SEGMENTS) * 360,
            lit: index < litSegments.value,
        };
    }),
);
</script>

<template>
    <div
        class="relative mx-auto aspect-square w-full max-w-[min(82vw,460px)]"
        role="timer"
        :aria-label="`${caption}: ${formatClock(remainingSeconds)}`"
    >
        <svg viewBox="0 0 100 100" class="absolute inset-0 size-full">
            <defs>
                <filter
                    id="cc-glow"
                    x="-50%"
                    y="-50%"
                    width="200%"
                    height="200%"
                >
                    <feGaussianBlur stdDeviation="0.9" result="blur" />
                    <feMerge>
                        <feMergeNode in="blur" />
                        <feMergeNode in="SourceGraphic" />
                    </feMerge>
                </filter>
            </defs>
            <circle
                cx="50"
                cy="50"
                r="38"
                fill="none"
                stroke="var(--color-line)"
                stroke-width="0.4"
                stroke-dasharray="1 1.6"
            />
            <rect
                v-for="(segment, index) in segments"
                :key="index"
                :x="segment.x - 1.1"
                :y="segment.y - 2.4"
                width="2.2"
                height="4.8"
                :transform="`rotate(${segment.rotate} ${segment.x} ${segment.y})`"
                :fill="segment.lit ? color : 'var(--color-line)'"
                :filter="segment.lit ? 'url(#cc-glow)' : undefined"
                class="transition-[fill] duration-500"
            />
        </svg>

        <div
            class="absolute inset-0 flex flex-col items-center justify-center text-center"
        >
            <span class="eyebrow">{{ caption }}</span>
            <span
                class="tabular mt-1 text-[clamp(2.75rem,12vw,5.25rem)] leading-none font-bold tracking-tight"
                :style="{
                    color,
                    textShadow: `0 0 28px color-mix(in srgb, ${color} 45%, transparent)`,
                }"
            >
                {{ formatClock(remainingSeconds) }}
            </span>
            <span class="tabular text-dim mt-3 text-xs">
                {{ Math.round(progress * 100) }}% restante
            </span>
        </div>
    </div>
</template>
