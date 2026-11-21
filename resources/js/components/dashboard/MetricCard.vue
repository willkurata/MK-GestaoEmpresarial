<script setup lang="ts">
import { ArrowDownRight, ArrowUpRight, Minus } from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import { formatVariation } from '@/lib/format';

const props = withDefaults(
    defineProps<{
        label: string;
        value: string;
        icon: Component;
        hint?: string;
        /** Variação percentual vs. período anterior; null = sem base. */
        variation?: number | null;
        higherIsBetter?: boolean;
        /** Indicador fora do escopo atual (ex.: estoque na V1). */
        badge?: string;
        accent?: boolean;
    }>(),
    {
        hint: undefined,
        variation: undefined,
        higherIsBetter: true,
        badge: undefined,
        accent: false,
    },
);

const variationTone = computed(() => {
    if (
        props.variation === undefined ||
        props.variation === null ||
        props.variation === 0
    ) {
        return { icon: Minus, classes: 'text-dash-muted bg-white/5' };
    }

    const isGood = props.variation > 0 === props.higherIsBetter;

    return {
        icon: props.variation > 0 ? ArrowUpRight : ArrowDownRight,
        classes: isGood
            ? 'text-risk-ok bg-risk-ok/10'
            : 'text-risk-critical bg-risk-critical/10',
    };
});
</script>

<template>
    <article
        class="dash-panel relative flex min-h-36 flex-col gap-4 overflow-hidden p-5"
        :class="{ 'ring-1 ring-dash-accent/40': accent }"
    >
        <div
            v-if="accent"
            class="pointer-events-none absolute inset-x-0 top-0 h-px bg-linear-to-r from-transparent via-dash-accent to-transparent"
            aria-hidden="true"
        />
        <header class="flex items-start justify-between gap-3">
            <p class="text-sm font-medium text-dash-muted">{{ label }}</p>
            <span
                class="grid size-9 shrink-0 place-items-center rounded-xl bg-dash-accent/10 text-dash-accent ring-1 ring-dash-accent/20 ring-inset"
                aria-hidden="true"
            >
                <component :is="icon" class="size-4" />
            </span>
        </header>
        <p
            class="text-2xl font-semibold tracking-tight sm:text-[1.7rem]"
            :class="badge ? 'text-dash-muted' : 'text-dash-text'"
        >
            {{ value }}
        </p>
        <footer class="mt-auto flex flex-wrap items-center gap-2 text-xs">
            <span
                v-if="badge"
                class="rounded-full bg-white/5 px-2 py-0.5 font-medium text-dash-muted ring-1 ring-white/10 ring-inset"
                >{{ badge }}</span
            >
            <span
                v-else-if="variation !== undefined"
                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-medium"
                :class="variationTone.classes"
            >
                <component
                    :is="variationTone.icon"
                    class="size-3.5"
                    aria-hidden="true"
                />
                <span class="text-dash-text">{{
                    formatVariation(variation)
                }}</span>
            </span>
            <span v-if="hint" class="text-dash-muted">{{ hint }}</span>
        </footer>
    </article>
</template>
