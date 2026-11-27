<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    label: string;
    value: string;
    hint?: string;
    icon: Component;
    tone?: 'default' | 'warn' | 'live';
}>();
</script>

<template>
    <article class="rounded-card border-line border bg-white p-4 lg:p-5">
        <div class="flex items-center justify-between gap-2">
            <p class="text-sm font-medium text-slate-500">{{ label }}</p>
            <span
                class="grid size-8 place-items-center rounded-lg"
                :class="{
                    'bg-slate-100 text-slate-600': !tone || tone === 'default',
                    'text-warn-ink bg-amber-50': tone === 'warn',
                    'bg-ink-950 text-lime-400': tone === 'live',
                }"
            >
                <component :is="icon" class="size-4" />
            </span>
        </div>
        <p
            class="font-display tabular mt-3 flex items-center gap-2 text-3xl font-bold"
        >
            <span
                v-if="tone === 'live'"
                class="relative flex size-2.5"
                aria-hidden="true"
            >
                <span
                    class="absolute inline-flex size-full animate-ping rounded-full bg-lime-500 opacity-60"
                />
                <span
                    class="relative inline-flex size-2.5 rounded-full bg-lime-500"
                />
            </span>
            {{ value }}
        </p>
        <p v-if="hint" class="mt-1 text-sm text-slate-500">
            <slot name="hint">{{ hint }}</slot>
        </p>
        <slot />
    </article>
</template>
