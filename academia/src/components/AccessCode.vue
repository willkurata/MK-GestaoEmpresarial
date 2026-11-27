<script setup lang="ts">
import { computed } from 'vue';
import { useNow } from '../composables/useNow';
import {
    ACCESS_CODE_WINDOW_SECONDS,
    accessCode,
    accessCodeWindow,
    codeMatrix,
    secondsUntilRotation,
} from '../lib/accessCode';

const props = defineProps<{ secret: string }>();

const now = useNow(250);
const codeWindow = computed(() => accessCodeWindow(now.value.getTime()));
const code = computed(() => accessCode(props.secret, codeWindow.value));
const matrix = computed(() => codeMatrix(code.value));
const secondsLeft = computed(() => secondsUntilRotation(now.value.getTime()));
</script>

<template>
    <div class="flex flex-col items-center gap-3">
        <div
            class="rounded-3xl bg-white p-4 shadow-[0_0_0_6px_rgba(198,244,50,0.15)]"
        >
            <svg
                :viewBox="`0 0 ${matrix.length} ${matrix.length}`"
                class="size-52"
                shape-rendering="crispEdges"
                role="img"
                :aria-label="`Código de acesso ${code}, renova em ${secondsLeft} segundos`"
            >
                <template v-for="(row, rowIndex) in matrix" :key="rowIndex">
                    <rect
                        v-for="(dark, colIndex) in row"
                        v-show="dark"
                        :key="colIndex"
                        :x="colIndex"
                        :y="rowIndex"
                        width="1"
                        height="1"
                        fill="#0E1116"
                    />
                </template>
            </svg>
        </div>
        <div
            class="text-ink-400 flex w-full max-w-[15rem] items-center gap-2 text-xs"
        >
            <div class="bg-ink-700 h-1 flex-1 overflow-hidden rounded-full">
                <div
                    class="h-full rounded-full bg-lime-400 transition-[width] duration-200"
                    :style="{
                        width: `${(secondsLeft / ACCESS_CODE_WINDOW_SECONDS) * 100}%`,
                    }"
                />
            </div>
            <span class="tabular">renova em {{ secondsLeft }}s</span>
        </div>
    </div>
</template>
