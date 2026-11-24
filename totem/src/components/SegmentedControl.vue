<script setup lang="ts" generic="T extends string | number | boolean">
/** Seletor segmentado: substitui dropdowns, com alvos de 48 px. */
defineProps<{
    label: string;
    options: { value: T; label: string; hint?: string; disabled?: boolean }[];
}>();

const model = defineModel<T>({ required: true });
</script>

<template>
    <fieldset>
        <legend class="text-ink-soft mb-2 text-sm font-semibold">
            {{ label }}
        </legend>
        <div
            class="bg-surface-2 grid gap-1 rounded-2xl p-1"
            :style="{
                gridTemplateColumns: `repeat(${options.length}, minmax(0, 1fr))`,
            }"
        >
            <button
                v-for="option in options"
                :key="String(option.value)"
                type="button"
                :aria-pressed="model === option.value"
                :disabled="option.disabled"
                :class="[
                    'flex min-h-12 flex-col items-center justify-center rounded-xl px-2 py-1.5 text-center transition-colors',
                    model === option.value
                        ? 'bg-surface text-ink ring-process shadow-sm ring-2'
                        : 'text-ink-soft hover:text-ink',
                    option.disabled ? 'opacity-40' : '',
                ]"
                @click="model = option.value"
            >
                <span class="font-semibold">{{ option.label }}</span>
                <span v-if="option.hint" class="font-mono text-[11px]">
                    {{ option.hint }}
                </span>
            </button>
        </div>
    </fieldset>
</template>
