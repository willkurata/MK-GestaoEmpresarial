<script setup lang="ts">
defineProps<{
    periods: Array<{ value: string; label: string }>;
    modelValue: string;
    label: string;
}>();

defineEmits<{ select: [value: string] }>();
</script>

<template>
    <div
        class="inline-flex max-w-full flex-wrap gap-1 rounded-xl border border-dash-line bg-dash-surface/80 p-1"
        role="group"
        :aria-label="label"
    >
        <button
            v-for="period in periods"
            :key="period.value"
            type="button"
            class="min-h-9 rounded-lg px-3 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-dash-accent"
            :class="
                modelValue === period.value
                    ? 'bg-dash-accent-strong text-white shadow-[0_0_20px_-6px_var(--dash-accent)]'
                    : 'text-dash-muted hover:bg-white/5 hover:text-dash-text'
            "
            :aria-pressed="modelValue === period.value"
            @click="$emit('select', period.value)"
        >
            {{ period.label }}
        </button>
    </div>
</template>
