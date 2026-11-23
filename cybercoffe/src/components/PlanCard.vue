<script setup lang="ts">
import { formatDuration, formatMoney } from '@cc/lib/format';
import type { Plan } from '@cc/types';

defineProps<{
    plan: Plan;
    selected: boolean;
}>();

defineEmits<{ select: [planId: number] }>();
</script>

<template>
    <button
        type="button"
        role="radio"
        :aria-checked="selected"
        class="pixel-corners relative flex w-full flex-col gap-2 p-4 text-left transition-[background-color,box-shadow] duration-150"
        :class="
            selected
                ? 'bg-sky/10 shadow-[inset_0_0_0_2px_var(--color-sky)]'
                : 'bg-panel-2 hover:bg-line shadow-[inset_0_0_0_1px_var(--color-line-strong)]'
        "
        @click="$emit('select', plan.id)"
    >
        <span
            v-if="plan.highlight"
            class="bg-neon text-void absolute top-3 right-3 px-1.5 py-0.5 text-[10px] font-bold tracking-widest uppercase"
        >
            Popular
        </span>
        <span class="eyebrow">{{ plan.name }}</span>
        <span class="tabular text-3xl font-bold tracking-tight">
            {{ formatDuration(plan.minutes) }}
        </span>
        <span class="text-dim text-sm">{{ plan.description }}</span>
        <span
            class="tabular mt-1 text-lg font-semibold"
            :class="selected ? 'text-sky' : 'text-ink'"
        >
            {{ formatMoney(plan.price_cents) }}
        </span>
    </button>
</template>
