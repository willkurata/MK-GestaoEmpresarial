<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        /** 1 → cheio, 0 → vazio. */
        progress: number;
        seconds: number;
        size?: number;
        stroke?: number;
        tone?: 'lime' | 'danger';
    }>(),
    { size: 320, stroke: 14, tone: 'lime' },
);

const radius = computed(() => (props.size - props.stroke) / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);
const dashOffset = computed(
    () => circumference.value * (1 - Math.max(0, Math.min(1, props.progress))),
);
</script>

<template>
    <div
        class="relative grid place-items-center"
        :style="{
            width: `${size}px`,
            height: `${size}px`,
            maxWidth: '80vw',
            maxHeight: '80vw',
        }"
        role="timer"
        :aria-label="`${seconds} segundos restantes para passar`"
    >
        <svg
            :viewBox="`0 0 ${size} ${size}`"
            class="absolute inset-0 size-full -rotate-90"
        >
            <circle
                :cx="size / 2"
                :cy="size / 2"
                :r="radius"
                fill="none"
                class="stroke-ink-800"
                :stroke-width="stroke"
            />
            <circle
                :cx="size / 2"
                :cy="size / 2"
                :r="radius"
                fill="none"
                :class="tone === 'lime' ? 'stroke-lime-400' : 'stroke-danger'"
                :stroke-width="stroke"
                stroke-linecap="round"
                :stroke-dasharray="circumference"
                :stroke-dashoffset="dashOffset"
            />
        </svg>
        <div class="relative text-center">
            <slot :seconds="seconds">
                <span class="font-display tabular text-8xl font-bold">{{
                    seconds
                }}</span>
            </slot>
        </div>
    </div>
</template>
