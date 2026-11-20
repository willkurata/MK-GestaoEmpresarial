<script setup lang="ts">
/**
 * Título de seção do site. `font="sans"` usa o estilo clean da home (WIL-28):
 * sans grande, peso médio e entrelinha folgada; o padrão continua serifado
 * para as demais páginas.
 */
const {
    eyebrow,
    title,
    description,
    align = 'left',
    font = 'display',
} = defineProps<{
    eyebrow?: string;
    title: string;
    description?: string;
    align?: 'left' | 'center';
    font?: 'display' | 'sans';
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
            v-if="font === 'sans'"
            class="mt-4 text-3xl leading-[1.15] font-medium tracking-tight text-balance text-mk-graphite-950 sm:text-[2.75rem]"
        >
            {{ title }}
        </h2>
        <h2
            v-else
            class="mt-3 font-display text-3xl leading-tight font-semibold text-mk-graphite-950 sm:text-4xl"
        >
            {{ title }}
        </h2>
        <p
            v-if="description"
            class="text-base leading-relaxed text-mk-graphite-600"
            :class="font === 'sans' ? 'mt-5 sm:text-lg sm:leading-8' : 'mt-4'"
        >
            {{ description }}
        </p>
    </div>
</template>
