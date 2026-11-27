<script setup lang="ts">
import { computed, ref } from 'vue';

/**
 * Colunas agrupadas (até 2 séries) em superfície clara. Um único eixo y,
 * legenda sempre visível, rótulo direto só na última categoria e tooltip
 * por categoria. Alternativa em tabela para acessibilidade.
 */
type Series = {
    name: string;
    values: number[];
    colorClass: string;
    swatchClass: string;
};

const props = defineProps<{
    categories: string[];
    series: Series[];
    format: (value: number) => string;
    compactFormat?: (value: number) => string;
    label: string;
}>();

const showTable = ref(false);
const hovered = ref<number | null>(null);

const width = 640;
const height = 240;
const padding = { top: 16, right: 12, bottom: 28, left: 76 };
const plotWidth = width - padding.left - padding.right;
const plotHeight = height - padding.top - padding.bottom;

const maxValue = computed(() => {
    const max = Math.max(...props.series.flatMap((serie) => serie.values), 0);

    return niceCeil(max);
});

function niceCeil(value: number): number {
    if (value <= 0) {
        return 1;
    }

    const magnitude = 10 ** Math.floor(Math.log10(value));
    const steps = [1, 2, 2.5, 5, 10];

    return (steps.find((step) => step * magnitude >= value) ?? 10) * magnitude;
}

const ticks = computed(() =>
    [0, 0.25, 0.5, 0.75, 1].map((ratio) => ratio * maxValue.value),
);

const groupWidth = computed(() => plotWidth / props.categories.length);
const barGap = 2;
const barWidth = computed(() =>
    Math.min(
        28,
        (groupWidth.value * 0.62 - barGap * (props.series.length - 1)) /
            props.series.length,
    ),
);

function barX(categoryIndex: number, serieIndex: number): number {
    const groupInner =
        barWidth.value * props.series.length +
        barGap * (props.series.length - 1);

    return (
        padding.left +
        categoryIndex * groupWidth.value +
        (groupWidth.value - groupInner) / 2 +
        serieIndex * (barWidth.value + barGap)
    );
}

function y(value: number): number {
    return padding.top + plotHeight - (value / maxValue.value) * plotHeight;
}

/** Barra com topo arredondado (4px) e base reta no eixo. */
function barPath(x: number, value: number): string {
    const top = y(value);
    const bottom = padding.top + plotHeight;
    const radius = Math.min(4, barWidth.value / 2, bottom - top);
    const right = x + barWidth.value;

    return `M${x},${bottom} V${top + radius} Q${x},${top} ${x + radius},${top} H${right - radius} Q${right},${top} ${right},${top + radius} V${bottom} Z`;
}

const tick = (value: number): string =>
    (props.compactFormat ?? props.format)(value);
</script>

<template>
    <figure class="w-full">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <ul class="flex flex-wrap gap-4 text-xs text-slate-600">
                <li
                    v-for="serie in series"
                    :key="serie.name"
                    class="flex items-center gap-1.5"
                >
                    <span
                        class="size-2.5 rounded-sm"
                        :class="serie.swatchClass"
                        aria-hidden="true"
                    />
                    {{ serie.name }}
                </li>
            </ul>
            <button
                type="button"
                class="rounded-md px-2 py-1 text-xs font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-800"
                :aria-pressed="showTable"
                @click="showTable = !showTable"
            >
                {{ showTable ? 'Ver gráfico' : 'Ver tabela' }}
            </button>
        </div>

        <div v-if="!showTable" class="-mx-1 overflow-x-auto px-1">
            <div class="relative min-w-[30rem]">
                <svg
                    :viewBox="`0 0 ${width} ${height}`"
                    class="h-auto w-full"
                    role="img"
                    :aria-label="label"
                >
                    <g class="text-[11px]">
                        <g v-for="value in ticks" :key="value">
                            <line
                                :x1="padding.left"
                                :x2="width - padding.right"
                                :y1="y(value)"
                                :y2="y(value)"
                                class="stroke-slate-200"
                                :stroke-dasharray="
                                    value === 0 ? undefined : '2 4'
                                "
                            />
                            <text
                                :x="padding.left - 8"
                                :y="y(value) + 4"
                                text-anchor="end"
                                class="tabular fill-slate-400"
                            >
                                {{ tick(value) }}
                            </text>
                        </g>
                    </g>

                    <g
                        v-for="(category, categoryIndex) in categories"
                        :key="category"
                        @pointerenter="hovered = categoryIndex"
                        @pointerleave="hovered = null"
                    >
                        <rect
                            :x="padding.left + categoryIndex * groupWidth"
                            :y="padding.top"
                            :width="groupWidth"
                            :height="plotHeight"
                            :class="
                                hovered === categoryIndex
                                    ? 'fill-slate-100'
                                    : 'fill-transparent'
                            "
                        />
                        <path
                            v-for="(serie, serieIndex) in series"
                            :key="serie.name"
                            :d="
                                barPath(
                                    barX(categoryIndex, serieIndex),
                                    serie.values[categoryIndex] ?? 0,
                                )
                            "
                            :class="serie.colorClass"
                        />
                        <text
                            :x="
                                padding.left +
                                categoryIndex * groupWidth +
                                groupWidth / 2
                            "
                            :y="height - 8"
                            text-anchor="middle"
                            class="fill-slate-500 text-[11px] capitalize"
                        >
                            {{ category }}
                        </text>
                    </g>
                </svg>

                <div
                    v-if="hovered !== null"
                    class="pointer-events-none absolute top-0 z-10 min-w-40 -translate-x-1/2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs shadow-lg"
                    :style="{
                        left: `${((padding.left + hovered * groupWidth + groupWidth / 2) / width) * 100}%`,
                    }"
                >
                    <p class="mb-1 font-semibold text-slate-900 capitalize">
                        {{ categories[hovered] }}
                    </p>
                    <p
                        v-for="serie in series"
                        :key="serie.name"
                        class="flex items-center justify-between gap-3 text-slate-600"
                    >
                        <span class="flex items-center gap-1.5">
                            <span
                                class="size-2 rounded-sm"
                                :class="serie.swatchClass"
                            />{{ serie.name }}
                        </span>
                        <span class="tabular font-medium text-slate-900">{{
                            format(serie.values[hovered] ?? 0)
                        }}</span>
                    </p>
                </div>
            </div>
        </div>

        <table v-else class="w-full text-sm">
            <caption class="sr-only">
                {{
                    label
                }}
            </caption>
            <thead>
                <tr
                    class="border-b border-slate-200 text-left text-xs text-slate-500"
                >
                    <th class="py-2 font-medium">Mês</th>
                    <th
                        v-for="serie in series"
                        :key="serie.name"
                        class="py-2 text-right font-medium"
                    >
                        {{ serie.name }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(category, index) in categories"
                    :key="category"
                    class="border-b border-slate-100"
                >
                    <td class="py-2 capitalize">{{ category }}</td>
                    <td
                        v-for="serie in series"
                        :key="serie.name"
                        class="tabular py-2 text-right"
                    >
                        {{ format(serie.values[index] ?? 0) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </figure>
</template>
