<script setup lang="ts">
import type { PrintOptions } from '@totem/types';
import { computed } from 'vue';

/**
 * Prévia esquemática do layout: como as páginas escolhidas caem nas folhas
 * (páginas por folha, frente e verso, cor). Mostra no máximo 3 folhas.
 */
const props = defineProps<{
    options: PrintOptions;
    pages: number[];
    imageUrl?: string | null;
}>();

const sides = computed(() => {
    const perSheet = props.options.pages_per_sheet;
    const result: number[][] = [];

    for (let i = 0; i < props.pages.length; i += perSheet) {
        result.push(props.pages.slice(i, i + perSheet));
    }

    return result;
});

const sheets = computed(() => {
    const result: { front: number[]; hasBack: boolean }[] = [];
    const step = props.options.duplex ? 2 : 1;

    for (let i = 0; i < sides.value.length; i += step) {
        result.push({
            front: sides.value[i],
            hasBack: props.options.duplex && i + 1 < sides.value.length,
        });
    }

    return result;
});

const shown = computed(() => sheets.value.slice(0, 3));

const grid = computed(() =>
    props.options.pages_per_sheet === 1
        ? 'grid-cols-1 grid-rows-1'
        : props.options.pages_per_sheet === 2
          ? 'grid-cols-1 grid-rows-2'
          : 'grid-cols-2 grid-rows-2',
);
</script>

<template>
    <figure>
        <div
            class="flex items-end justify-center gap-3 overflow-hidden rounded-2xl bg-surface-2 px-4 pt-6 pb-4"
            aria-hidden="true"
        >
            <div
                v-for="(sheet, sheetIndex) in shown"
                :key="sheetIndex"
                class="relative w-[30%] max-w-28 animate-rise"
                :style="{ animationDelay: `${sheetIndex * 60}ms` }"
            >
                <div
                    v-if="sheet.hasBack"
                    class="absolute inset-0 translate-x-1.5 -translate-y-1.5 rounded-md bg-surface ring-1 ring-line"
                />
                <div
                    :class="[
                        'relative grid aspect-[210/297] gap-1 rounded-md bg-white p-1.5 shadow-md ring-1 ring-line',
                        grid,
                    ]"
                >
                    <div
                        v-for="page in sheet.front"
                        :key="page"
                        :class="[
                            'relative grid place-items-center overflow-hidden rounded-[3px]',
                            options.color === 'color'
                                ? 'bg-gradient-to-br from-cyan-200 via-amber-100 to-rose-200'
                                : 'bg-neutral-200',
                        ]"
                    >
                        <img
                            v-if="imageUrl"
                            :src="imageUrl"
                            alt=""
                            :class="[
                                'absolute inset-0 size-full object-cover',
                                options.color === 'bw' ? 'grayscale' : '',
                            ]"
                        />
                        <span
                            v-else
                            class="font-mono text-[10px] font-bold text-neutral-600"
                            >{{ page }}</span
                        >
                    </div>
                </div>
            </div>
        </div>
        <figcaption class="mt-2 text-center text-sm text-ink-soft">
            {{ sheets.length }}
            {{ sheets.length === 1 ? 'folha' : 'folhas' }} por cópia
            <template v-if="options.duplex"> · frente e verso</template>
            <template v-if="sheets.length > shown.length">
                · mostrando {{ shown.length }}</template
            >
        </figcaption>
    </figure>
</template>
