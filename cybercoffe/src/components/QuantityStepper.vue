<script setup lang="ts">
import { Minus, Plus } from '@lucide/vue';

/** Controle − n + para quantidades inteiras (produtos, páginas). */
const props = defineProps<{
    label: string;
    min: number;
    max: number;
    size?: 'md' | 'lg';
}>();

const model = defineModel<number>({ required: true });

function change(delta: number): void {
    model.value = Math.min(props.max, Math.max(props.min, model.value + delta));
}

function onInput(event: Event): void {
    const value = Number.parseInt((event.target as HTMLInputElement).value, 10);
    model.value = Number.isNaN(value)
        ? props.min
        : Math.min(props.max, Math.max(props.min, value));
}
</script>

<template>
    <div role="group" :aria-label="label" class="flex items-center gap-2">
        <span class="eyebrow">{{ label }}</span>
        <div class="ring-line-strong flex items-stretch ring-1 ring-inset">
            <button
                type="button"
                class="text-dim hover:bg-line hover:text-ink flex items-center justify-center disabled:opacity-30"
                :class="size === 'lg' ? 'size-14' : 'size-10'"
                :disabled="model <= min"
                :aria-label="`Diminuir ${label.toLowerCase()}`"
                @click="change(-1)"
            >
                <Minus class="size-4" />
            </button>
            <input
                :value="model"
                type="number"
                inputmode="numeric"
                :min="min"
                :max="max"
                :aria-label="label"
                class="tabular bg-void text-ink [appearance:textfield] border-0 text-center font-bold outline-none [&::-webkit-inner-spin-button]:appearance-none"
                :class="size === 'lg' ? 'w-20 text-2xl' : 'w-12 text-base'"
                @change="onInput"
            />
            <button
                type="button"
                class="text-dim hover:bg-line hover:text-ink flex items-center justify-center disabled:opacity-30"
                :class="size === 'lg' ? 'size-14' : 'size-10'"
                :disabled="model >= max"
                :aria-label="`Aumentar ${label.toLowerCase()}`"
                @click="change(1)"
            >
                <Plus class="size-4" />
            </button>
        </div>
    </div>
</template>
