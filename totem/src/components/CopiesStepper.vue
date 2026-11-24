<script setup lang="ts">
import { Minus, Plus } from '@lucide/vue';
import { clampCopies } from '@totem/lib/pricing';

const props = defineProps<{ max: number }>();
const model = defineModel<number>({ required: true });

function set(value: number): void {
    model.value = clampCopies(value, props.max);
}

function onInput(event: Event): void {
    set(Number((event.target as HTMLInputElement).value));
}
</script>

<template>
    <div>
        <span
            id="copies-label"
            class="text-ink-soft mb-2 block text-sm font-semibold"
        >
            Cópias
        </span>
        <div
            class="bg-surface-2 flex items-center justify-between gap-2 rounded-2xl p-1"
            role="group"
            aria-labelledby="copies-label"
        >
            <button
                type="button"
                class="bg-surface text-ink grid size-12 place-items-center rounded-xl shadow-sm disabled:opacity-40"
                :disabled="model <= 1"
                aria-label="Menos uma cópia"
                @click="set(model - 1)"
            >
                <Minus class="size-5" />
            </button>
            <input
                type="number"
                inputmode="numeric"
                min="1"
                :max="max"
                :value="model"
                aria-labelledby="copies-label"
                class="w-20 [appearance:textfield] bg-transparent text-center font-mono text-2xl font-bold focus:outline-none [&::-webkit-inner-spin-button]:appearance-none"
                @change="onInput"
            />
            <button
                type="button"
                class="bg-surface text-ink grid size-12 place-items-center rounded-xl shadow-sm disabled:opacity-40"
                :disabled="model >= max"
                aria-label="Mais uma cópia"
                @click="set(model + 1)"
            >
                <Plus class="size-5" />
            </button>
        </div>
    </div>
</template>
