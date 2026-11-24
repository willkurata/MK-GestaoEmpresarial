<script setup lang="ts">
defineProps<{ code: string; size?: 'md' | 'xl' }>();
</script>

<template>
    <span
        class="inline-flex gap-1.5"
        role="text"
        :aria-label="`Código de retirada ${code.split('').join(' ')}`"
    >
        <span
            v-for="(char, index) in code.split('')"
            :key="index"
            aria-hidden="true"
            :class="[
                'bg-surface-2 font-display ring-line grid place-items-center rounded-xl font-extrabold ring-1',
                size === 'xl' ? 'h-24 w-20 text-6xl' : 'h-12 w-10 text-3xl',
            ]"
            >{{ char }}</span
        >
    </span>
</template>
