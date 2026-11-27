<script setup lang="ts">
import { computed } from 'vue';

/** Barras horizontais com rótulo e valor em texto (sem depender de cor). */
const props = defineProps<{
    items: { label: string; value: number; detail?: string }[];
    format?: (value: number) => string;
    barClass?: string;
}>();

const maxValue = computed(() =>
    Math.max(...props.items.map((item) => item.value), 1),
);
</script>

<template>
    <ul class="space-y-3">
        <li v-for="item in items" :key="item.label">
            <div class="mb-1 flex items-baseline justify-between gap-2 text-sm">
                <span class="text-slate-700">{{ item.label }}</span>
                <span class="tabular font-semibold text-slate-900">
                    {{ format ? format(item.value) : item.value }}
                    <span
                        v-if="item.detail"
                        class="ml-1 font-normal text-slate-500"
                        >{{ item.detail }}</span
                    >
                </span>
            </div>
            <div class="h-2 rounded-full bg-slate-100">
                <div
                    class="h-full rounded-full transition-[width] duration-500"
                    :class="barClass ?? 'bg-series-1'"
                    :style="{
                        width: `${Math.max(item.value > 0 ? 2 : 0, (item.value / maxValue) * 100)}%`,
                    }"
                />
            </div>
        </li>
    </ul>
</template>
