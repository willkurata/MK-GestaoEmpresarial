<script setup lang="ts">
import { formatMoney } from '@cc/lib/format';
import type { DashboardMetrics } from '@cc/types';
import { computed } from 'vue';

/** Receita do dia por origem: tempo, impressões e produtos (barra empilhada). */
const props = defineProps<{
    breakdown: DashboardMetrics['revenue_breakdown'];
}>();

const parts = computed(() => {
    const breakdown = props.breakdown;

    if (!breakdown) {
        return [];
    }

    const total =
        breakdown.time_cents +
        breakdown.prints_cents +
        breakdown.products_cents;

    return [
        {
            label: 'Tempo',
            cents: breakdown.time_cents,
            swatch: 'bg-neon',
        },
        {
            label: 'Impressões',
            cents: breakdown.prints_cents,
            swatch: 'bg-sky',
        },
        {
            label: 'Produtos',
            cents: breakdown.products_cents,
            swatch: 'bg-violet',
        },
    ].map((part) => ({
        ...part,
        share: total > 0 ? part.cents / total : 0,
    }));
});

const hasRevenue = computed(() => parts.value.some((part) => part.cents > 0));
</script>

<template>
    <article class="panel pixel-corners p-5">
        <span class="eyebrow">Receita por origem · hoje</span>

        <template v-if="breakdown">
            <div
                class="bg-line mt-5 flex h-3 gap-0.5 overflow-hidden"
                role="img"
                :aria-label="
                    parts
                        .map(
                            (part) =>
                                `${part.label} ${Math.round(part.share * 100)}%`,
                        )
                        .join(', ')
                "
            >
                <div
                    v-for="part in parts.filter((item) => item.cents > 0)"
                    :key="part.label"
                    class="h-full transition-[width] duration-500"
                    :class="part.swatch"
                    :style="{ width: `${part.share * 100}%` }"
                />
            </div>
            <ul class="mt-4 grid gap-3 sm:grid-cols-3">
                <li v-for="part in parts" :key="part.label">
                    <span class="text-dim flex items-center gap-2 text-xs">
                        <span
                            aria-hidden="true"
                            class="size-2"
                            :class="part.swatch"
                        />
                        {{ part.label }}
                        <span v-if="hasRevenue" class="tabular"
                            >· {{ Math.round(part.share * 100) }}%</span
                        >
                    </span>
                    <span class="tabular mt-1 block text-xl font-semibold">
                        {{ formatMoney(part.cents) }}
                    </span>
                </li>
            </ul>
        </template>
        <p v-else class="text-dim mt-4 text-sm">
            O servidor ainda não separa a receita por origem.
        </p>
    </article>
</template>
