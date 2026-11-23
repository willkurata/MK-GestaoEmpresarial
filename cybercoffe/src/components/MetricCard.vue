<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    label: string;
    value: string;
    hint?: string;
    icon: Component;
    tone?: 'neon' | 'sky';
}>();
</script>

<template>
    <article class="panel pixel-corners relative overflow-hidden p-5">
        <div class="flex items-center justify-between gap-3">
            <span class="eyebrow">{{ label }}</span>
            <component
                :is="icon"
                class="size-4"
                :class="tone === 'sky' ? 'text-sky' : 'text-neon'"
            />
        </div>
        <p
            class="tabular mt-3 text-3xl font-bold tracking-tight sm:text-[2rem]"
        >
            {{ value }}
        </p>
        <p v-if="hint" class="text-dim mt-1 text-xs">{{ hint }}</p>
        <span
            aria-hidden="true"
            class="absolute bottom-0 left-0 h-0.5 w-12"
            :class="tone === 'sky' ? 'bg-sky' : 'bg-neon'"
        />
    </article>
</template>
