<script setup lang="ts">
const {
    eyebrow,
    title,
    description,
    align = 'left',
} = defineProps<{
    eyebrow?: string;
    title: string;
    description?: string;
    align?: 'left' | 'center';
}>();
</script>

<template>
    <div
        class="max-w-2xl"
        :class="align === 'center' ? 'mx-auto text-center' : ''"
    >
        <p
            v-if="eyebrow"
            class="text-xs font-semibold tracking-[0.25em] text-mk-green-600 uppercase"
        >
            {{ eyebrow }}
        </p>
        <h2
            class="mt-3 font-display text-3xl leading-tight font-semibold text-mk-graphite-950 sm:text-4xl"
        >
            {{ title }}
        </h2>
        <p
            v-if="description"
            class="mt-4 text-base leading-relaxed text-mk-graphite-600"
        >
            {{ description }}
        </p>
    </div>
</template>
