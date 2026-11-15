<script setup lang="ts">
const { inverted = false } = defineProps<{ inverted?: boolean }>();
</script>

<template>
    <span class="inline-flex items-center gap-3">
        <span
            class="flex h-[30px] w-[58px] shrink-0 items-start overflow-hidden"
            :class="inverted ? 'rounded bg-mk-paper px-1 pt-0.5' : ''"
        >
            <img
                src="/logo-mk-negocios.png"
                alt=""
                width="58"
                height="40"
                class="h-auto w-full"
            />
        </span>
        <span
            class="font-sans text-sm leading-none font-semibold tracking-[0.32em] whitespace-nowrap"
        >
            <span :class="inverted ? 'text-mk-green-300' : 'text-mk-green-600'"
                >MK</span
            >
            <span :class="inverted ? 'text-white' : 'text-mk-graphite-900'">
                NEGÓCIOS</span
            >
        </span>
        <span class="sr-only">MK Negócios — página inicial</span>
    </span>
</template>
