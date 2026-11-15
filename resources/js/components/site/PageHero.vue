<script setup lang="ts">
import BrandBars from '@/components/site/BrandBars.vue';

const { eyebrow, title, description } = defineProps<{
    eyebrow?: string;
    title: string;
    description?: string;
}>();
</script>

<template>
    <section
        class="relative overflow-hidden border-b border-mk-graphite-100 bg-mk-sand"
    >
        <BrandBars
            class="absolute -right-6 -bottom-4 hidden size-56 text-mk-green-200 md:block"
        />
        <div class="relative mx-auto max-w-6xl px-4 py-14 sm:px-6 sm:py-20">
            <p
                v-if="eyebrow"
                class="text-xs font-semibold tracking-[0.25em] text-mk-green-600 uppercase"
            >
                {{ eyebrow }}
            </p>
            <h1
                class="mt-3 max-w-3xl font-display text-4xl leading-[1.1] font-semibold text-mk-graphite-950 sm:text-5xl"
            >
                {{ title }}
            </h1>
            <p
                v-if="description"
                class="mt-5 max-w-2xl text-lg leading-relaxed text-mk-graphite-600"
            >
                {{ description }}
            </p>
            <div v-if="$slots.default" class="mt-8 flex flex-wrap gap-3">
                <slot />
            </div>
        </div>
    </section>
</template>
