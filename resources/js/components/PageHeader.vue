<script setup lang="ts">
defineProps<{
    eyebrow?: string;
    title: string;
    description?: string;
}>();
</script>

<template>
    <header
        class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end"
    >
        <div class="min-w-0">
            <p
                v-if="eyebrow"
                class="text-xs font-semibold tracking-widest text-primary uppercase"
            >
                {{ eyebrow }}
            </p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">
                {{ title }}
            </h1>
            <p
                v-if="description || $slots.description"
                class="mt-2 max-w-2xl text-sm text-muted-foreground"
            >
                <slot name="description">{{ description }}</slot>
            </p>
        </div>
        <div
            v-if="$slots.actions"
            class="flex flex-wrap items-end gap-2 print:hidden"
        >
            <slot name="actions" />
        </div>
    </header>
</template>
