<script setup lang="ts">
import { Delete, ScanFace } from '@lucide/vue';
import { ref } from 'vue';
import { findStudent } from '../lib/store';

/**
 * Identificação no totem: rosto (câmera do totem) ou código de aluno no
 * teclado numérico grande. Na demonstração, o código é o número do aluno
 * (1 = Ana, 2 = Bruno…).
 */
const emit = defineEmits<{
    identified: [studentId: string, method: 'facial' | 'totem'];
}>();

const digits = ref('');
const scanning = ref(false);
const error = ref('');

function press(key: string): void {
    error.value = '';

    if (digits.value.length < 6) {
        digits.value += key;
    }
}

function erase(): void {
    digits.value = digits.value.slice(0, -1);
}

function submit(): void {
    const student = findStudent(`s${Number(digits.value)}`);

    if (student === null) {
        error.value = 'Código não encontrado. Confira ou chame a recepção.';
        digits.value = '';

        return;
    }

    emit('identified', student.id, 'totem');
}

function scanFace(): void {
    scanning.value = true;
    window.setTimeout(() => {
        scanning.value = false;
        emit('identified', 's1', 'facial');
    }, 1400);
}
</script>

<template>
    <div class="grid flex-1 items-center gap-8 lg:grid-cols-2">
        <button
            type="button"
            class="bg-ink-900 flex h-full min-h-72 flex-col items-center justify-center gap-5 rounded-3xl border-2 border-lime-400/40 p-8 active:scale-[0.99]"
            :disabled="scanning"
            @click="scanFace"
        >
            <span
                class="relative grid size-36 place-items-center rounded-full bg-lime-400/10"
            >
                <ScanFace class="size-20 text-lime-400" stroke-width="1.5" />
                <span
                    v-if="scanning"
                    class="absolute inset-0 animate-ping rounded-full border-4 border-lime-400/60"
                />
            </span>
            <span class="font-display text-3xl font-bold">{{
                scanning ? 'Reconhecendo…' : 'Olhe para a câmera'
            }}</span>
            <span class="text-ink-300 text-lg"
                >Toque aqui e fique parado por um instante</span
            >
        </button>

        <div class="mx-auto w-full max-w-sm">
            <p class="text-ink-300 text-center text-lg">
                ou digite seu código de aluno
            </p>
            <output
                class="bg-ink-900 font-display tabular mt-3 flex h-20 items-center justify-center rounded-2xl text-4xl font-bold tracking-[0.3em]"
                aria-live="polite"
            >
                {{ digits || '·····' }}
            </output>
            <p v-if="error" class="text-warn mt-2 text-center" role="alert">
                {{ error }}
            </p>
            <div class="mt-4 grid grid-cols-3 gap-3">
                <button
                    v-for="key in ['1', '2', '3', '4', '5', '6', '7', '8', '9']"
                    :key="key"
                    type="button"
                    class="bg-ink-800 font-display active:bg-ink-700 h-20 rounded-2xl text-3xl font-bold active:scale-95"
                    @click="press(key)"
                >
                    {{ key }}
                </button>
                <button
                    type="button"
                    class="bg-ink-800 grid h-20 place-items-center rounded-2xl active:scale-95"
                    aria-label="Apagar"
                    @click="erase"
                >
                    <Delete class="size-8" />
                </button>
                <button
                    type="button"
                    class="bg-ink-800 font-display h-20 rounded-2xl text-3xl font-bold active:scale-95"
                    @click="press('0')"
                >
                    0
                </button>
                <button
                    type="button"
                    class="text-ink-950 h-20 rounded-2xl bg-lime-400 text-xl font-bold active:scale-95 disabled:opacity-40"
                    :disabled="digits.length === 0"
                    @click="submit"
                >
                    OK
                </button>
            </div>
            <p class="text-ink-400 mt-3 text-center text-sm">
                Demonstração: 1 = aluno em dia · 2 = mensalidade atrasada
            </p>
        </div>
    </div>
</template>
