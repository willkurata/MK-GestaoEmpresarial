<script setup lang="ts">
import {
    CircleCheck,
    CircleDashed,
    OctagonAlert,
    TriangleAlert,
    CircleAlert,
} from '@lucide/vue';
import { computed } from 'vue';
import type { RiskLevel } from './types';

const props = withDefaults(
    defineProps<{ level: RiskLevel; label: string; size?: 'sm' | 'md' }>(),
    { size: 'sm' },
);

/** Cor + ícone + texto: o nível nunca depende só da cor (daltonismo). */
const appearance: Record<
    RiskLevel,
    { icon: typeof CircleCheck; classes: string }
> = {
    none: {
        icon: CircleDashed,
        classes: 'text-dash-muted bg-white/5 ring-white/10',
    },
    ok: {
        icon: CircleCheck,
        classes: 'text-risk-ok bg-risk-ok/10 ring-risk-ok/30',
    },
    attention: {
        icon: CircleAlert,
        classes:
            'text-risk-attention bg-risk-attention/10 ring-risk-attention/30',
    },
    high: {
        icon: TriangleAlert,
        classes: 'text-risk-high bg-risk-high/10 ring-risk-high/30',
    },
    critical: {
        icon: OctagonAlert,
        classes: 'text-risk-critical bg-risk-critical/10 ring-risk-critical/30',
    },
};

const current = computed(() => appearance[props.level]);
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center gap-1.5 rounded-full font-medium ring-1 ring-inset"
        :class="[
            current.classes,
            size === 'md' ? 'px-3 py-1 text-sm' : 'px-2 py-0.5 text-xs',
        ]"
    >
        <component
            :is="current.icon"
            :class="size === 'md' ? 'size-4' : 'size-3.5'"
            aria-hidden="true"
        />
        <span class="text-dash-text">{{ label }}</span>
    </span>
</template>
