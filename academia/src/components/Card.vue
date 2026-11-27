<script setup lang="ts">
defineProps<{ title?: string; description?: string }>();
</script>

<template>
    <section class="rounded-card border-line border bg-white p-4 lg:p-6">
        <header
            v-if="title || $slots.actions"
            class="mb-4 flex flex-wrap items-start justify-between gap-3"
        >
            <div>
                <h2 class="font-display text-base font-bold">{{ title }}</h2>
                <p v-if="description" class="mt-0.5 text-sm text-slate-500">
                    {{ description }}
                </p>
            </div>
            <slot name="actions" />
        </header>
        <slot />
    </section>
</template>
