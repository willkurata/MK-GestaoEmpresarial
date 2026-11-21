<script setup lang="ts">
import { useElementSize } from '@vueuse/core';
import { computed, ref, useId } from 'vue';
import { formatCompactCurrency, formatCurrency } from '@/lib/format';
import type { TrendSeries } from './types';

/**
 * Gráfico de linhas com área suave, um único eixo Y em reais.
 * Interação: linha-guia + tooltip no mouse/toque e setas do teclado.
 * Acessibilidade: legenda sempre visível e tabela equivalente para leitores de tela.
 */
const props = withDefaults(
    defineProps<{
        labels: string[];
        series: TrendSeries[];
        caption: string;
        height?: number;
    }>(),
    { height: 240 },
);

const container = ref<HTMLElement | null>(null);
const { width } = useElementSize(container);
const activeIndex = ref<number | null>(null);
const gradientId = useId();

const padding = { top: 16, right: 12, bottom: 28, left: 56 };
const plotWidth = computed(() =>
    Math.max(0, width.value - padding.left - padding.right),
);
const plotHeight = computed(() => props.height - padding.top - padding.bottom);

const hasData = computed(() =>
    props.series.some((serie) => serie.values.some((value) => value > 0)),
);

/** Teto "redondo" para o eixo, com 4 divisões. */
const yMax = computed(() => {
    const max = Math.max(0, ...props.series.flatMap((serie) => serie.values));

    if (max <= 0) {
        return 100;
    }

    const magnitude = 10 ** Math.floor(Math.log10(max));
    const step =
        [1, 2, 2.5, 5, 10]
            .map((factor) => factor * magnitude)
            .find((candidate) => candidate * 4 >= max) ?? max;

    return step * 4;
});

const ticks = computed(() =>
    [0, 1, 2, 3, 4].map((step) => (yMax.value / 4) * step),
);

function x(index: number): number {
    const count = props.labels.length;

    return (
        padding.left +
        (count <= 1
            ? plotWidth.value / 2
            : (index / (count - 1)) * plotWidth.value)
    );
}

function y(value: number): number {
    return (
        padding.top + plotHeight.value - (value / yMax.value) * plotHeight.value
    );
}

const paths = computed(() =>
    props.series.map((serie) => {
        const points = serie.values.map(
            (value, index) => [x(index), y(value)] as const,
        );
        const line = points
            .map(
                ([px, py], index) =>
                    `${index === 0 ? 'M' : 'L'}${px.toFixed(1)},${py.toFixed(1)}`,
            )
            .join(' ');
        const baseline = y(0).toFixed(1);
        const area = points.length
            ? `${line} L${points[points.length - 1][0].toFixed(1)},${baseline} L${points[0][0].toFixed(1)},${baseline} Z`
            : '';

        return { ...serie, line, area };
    }),
);

/** Mostra no máximo ~7 rótulos no eixo X para não sobrepor. */
const xLabelStep = computed(() =>
    Math.max(
        1,
        Math.ceil(
            props.labels.length / Math.max(2, Math.floor(plotWidth.value / 72)),
        ),
    ),
);

function onPointerMove(event: PointerEvent): void {
    const bounds = (event.currentTarget as SVGElement).getBoundingClientRect();
    const relativeX = event.clientX - bounds.left - padding.left;
    const count = props.labels.length;

    activeIndex.value =
        count <= 1
            ? 0
            : Math.min(
                  count - 1,
                  Math.max(
                      0,
                      Math.round((relativeX / plotWidth.value) * (count - 1)),
                  ),
              );
}

function onKeydown(event: KeyboardEvent): void {
    const last = props.labels.length - 1;

    if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
        event.preventDefault();
        const current =
            activeIndex.value ?? (event.key === 'ArrowRight' ? -1 : last + 1);
        activeIndex.value = Math.min(
            last,
            Math.max(0, current + (event.key === 'ArrowRight' ? 1 : -1)),
        );
    } else if (event.key === 'Escape') {
        activeIndex.value = null;
    }
}

const tooltipStyle = computed(() => {
    if (activeIndex.value === null) {
        return {};
    }

    const left = x(activeIndex.value);
    const alignRight = left > width.value - 180;

    return {
        left: `${left}px`,
        top: `${padding.top}px`,
        transform: alignRight
            ? 'translateX(calc(-100% - 12px))'
            : 'translateX(12px)',
    };
});
</script>

<template>
    <figure class="grid gap-4">
        <figcaption class="sr-only">{{ caption }}</figcaption>
        <ul
            class="flex flex-wrap gap-x-5 gap-y-2 text-xs text-dash-muted"
            aria-hidden="true"
        >
            <li
                v-for="serie in series"
                :key="serie.key"
                class="flex items-center gap-2"
            >
                <span
                    class="h-0.5 w-4 rounded-full bg-current"
                    :class="serie.colorClass"
                />
                <span>{{ serie.label }}</span>
            </li>
        </ul>

        <div
            ref="container"
            class="relative w-full"
            :style="{ height: `${height}px` }"
        >
            <svg
                v-if="width > 0"
                :width="width"
                :height="height"
                class="block touch-pan-y rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-dash-accent/60"
                role="img"
                :aria-label="`${caption}. Use as setas para percorrer os pontos.`"
                tabindex="0"
                @pointermove="onPointerMove"
                @pointerleave="activeIndex = null"
                @keydown="onKeydown"
                @blur="activeIndex = null"
            >
                <defs>
                    <linearGradient
                        v-for="serie in series"
                        :id="`${gradientId}-${serie.key}`"
                        :key="serie.key"
                        x1="0"
                        x2="0"
                        y1="0"
                        y2="1"
                        :class="serie.colorClass"
                    >
                        <stop
                            offset="0%"
                            stop-color="currentColor"
                            stop-opacity="0.28"
                        />
                        <stop
                            offset="100%"
                            stop-color="currentColor"
                            stop-opacity="0"
                        />
                    </linearGradient>
                </defs>

                <g>
                    <template v-for="tick in ticks" :key="tick">
                        <line
                            :x1="padding.left"
                            :x2="padding.left + plotWidth"
                            :y1="y(tick)"
                            :y2="y(tick)"
                            stroke="currentColor"
                            class="text-white/[0.07]"
                            :stroke-dasharray="tick === 0 ? undefined : '3 5'"
                        />
                        <text
                            :x="padding.left - 10"
                            :y="y(tick)"
                            text-anchor="end"
                            dominant-baseline="middle"
                            class="fill-dash-muted text-[11px]"
                        >
                            {{ formatCompactCurrency(tick) }}
                        </text>
                    </template>
                    <template
                        v-for="(label, index) in labels"
                        :key="`x-${label}-${index}`"
                    >
                        <text
                            v-if="
                                index % xLabelStep === 0 ||
                                index === labels.length - 1
                            "
                            :x="x(index)"
                            :y="height - 8"
                            text-anchor="middle"
                            class="fill-dash-muted text-[11px]"
                        >
                            {{ label }}
                        </text>
                    </template>
                </g>

                <g
                    v-for="path in paths"
                    :key="path.key"
                    :class="path.colorClass"
                >
                    <path
                        :d="path.area"
                        :fill="`url(#${gradientId}-${path.key})`"
                    />
                    <path
                        :d="path.line"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linejoin="round"
                        stroke-linecap="round"
                    />
                </g>

                <g v-if="activeIndex !== null">
                    <line
                        :x1="x(activeIndex)"
                        :x2="x(activeIndex)"
                        :y1="padding.top"
                        :y2="padding.top + plotHeight"
                        stroke="currentColor"
                        class="text-white/30"
                    />
                    <circle
                        v-for="serie in series"
                        :key="`dot-${serie.key}`"
                        :cx="x(activeIndex)"
                        :cy="y(serie.values[activeIndex] ?? 0)"
                        r="4.5"
                        fill="currentColor"
                        stroke="var(--dash-surface)"
                        stroke-width="2"
                        :class="serie.colorClass"
                    />
                </g>
            </svg>

            <div
                v-if="activeIndex !== null"
                class="pointer-events-none absolute z-10 min-w-40 rounded-xl border border-dash-line bg-dash-raised/95 px-3 py-2.5 text-xs shadow-xl backdrop-blur"
                :style="tooltipStyle"
                aria-hidden="true"
            >
                <p class="mb-1.5 font-medium text-dash-muted">
                    {{ labels[activeIndex] }}
                </p>
                <p
                    v-for="serie in series"
                    :key="`tip-${serie.key}`"
                    class="flex items-center justify-between gap-4 py-0.5"
                >
                    <span class="flex items-center gap-2 text-dash-muted">
                        <span
                            class="h-0.5 w-3 rounded-full bg-current"
                            :class="serie.colorClass"
                        />{{ serie.label }}
                    </span>
                    <strong class="font-semibold text-dash-text">{{
                        formatCurrency(serie.values[activeIndex] ?? 0)
                    }}</strong>
                </p>
            </div>

            <p
                v-if="!hasData"
                class="pointer-events-none absolute inset-0 grid place-items-center text-sm text-dash-muted"
            >
                Sem movimentação registrada neste intervalo.
            </p>
        </div>

        <table class="sr-only">
            <caption>
                {{
                    caption
                }}
            </caption>
            <thead>
                <tr>
                    <th scope="col">Período</th>
                    <th
                        v-for="serie in series"
                        :key="`th-${serie.key}`"
                        scope="col"
                    >
                        {{ serie.label }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(label, index) in labels"
                    :key="`row-${label}-${index}`"
                >
                    <th scope="row">{{ label }}</th>
                    <td v-for="serie in series" :key="`td-${serie.key}`">
                        {{ formatCurrency(serie.values[index] ?? 0) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </figure>
</template>
