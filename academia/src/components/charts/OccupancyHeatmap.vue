<script setup lang="ts">
import { computed, ref } from 'vue';

/**
 * Heatmap de ocupação (dia × hora). Rampa sequencial de um tom; a escala
 * aparece abaixo e cada célula tem tooltip com o valor exato.
 */
const props = defineProps<{
    days: string[];
    hours: number[];
    values: number[][];
    capacity: number;
}>();

const RAMP = [
    'bg-seq-0',
    'bg-seq-1',
    'bg-seq-2',
    'bg-seq-3',
    'bg-seq-4',
    'bg-seq-5',
];

const maxValue = computed(() => Math.max(...props.values.flat(), 1));
const hovered = ref<{ day: number; hour: number } | null>(null);

function level(value: number): number {
    return Math.min(
        RAMP.length - 1,
        Math.floor((value / maxValue.value) * (RAMP.length - 1) + 0.5),
    );
}

const peak = computed(() => {
    let best = { day: 0, hour: 0, value: -1 };

    props.values.forEach((row, day) =>
        row.forEach((value, hour) => {
            if (value > best.value) {
                best = { day, hour, value };
            }
        }),
    );

    return best;
});
</script>

<template>
    <figure>
        <div class="overflow-x-auto pb-1">
            <div
                class="grid min-w-[34rem] gap-[2px]"
                :style="{
                    gridTemplateColumns: `2.5rem repeat(${hours.length}, minmax(0, 1fr))`,
                }"
                role="grid"
                aria-label="Ocupação média por dia da semana e hora"
            >
                <div />
                <div
                    v-for="hour in hours"
                    :key="hour"
                    class="tabular pb-1 text-center text-[10px] text-slate-400"
                >
                    {{ hour % 2 === 0 ? `${hour}h` : '' }}
                </div>

                <template v-for="(day, dayIndex) in days" :key="day">
                    <div class="flex items-center text-xs text-slate-500">
                        {{ day }}
                    </div>
                    <button
                        v-for="(hour, hourIndex) in hours"
                        :key="hour"
                        type="button"
                        class="relative h-7 rounded-[3px] transition-transform hover:z-10 hover:scale-110 hover:ring-2 hover:ring-white"
                        :class="RAMP[level(values[dayIndex]?.[hourIndex] ?? 0)]"
                        :aria-label="`${day} ${hour}h: ${values[dayIndex]?.[hourIndex]} pessoas`"
                        @pointerenter="
                            hovered = { day: dayIndex, hour: hourIndex }
                        "
                        @pointerleave="hovered = null"
                        @focus="hovered = { day: dayIndex, hour: hourIndex }"
                        @blur="hovered = null"
                    />
                </template>
            </div>
        </div>

        <figcaption
            class="mt-3 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500"
        >
            <span class="flex items-center gap-2">
                Menos
                <span class="flex gap-[2px]">
                    <span
                        v-for="step in RAMP"
                        :key="step"
                        class="h-3 w-5 rounded-[2px]"
                        :class="step"
                    />
                </span>
                Mais
            </span>
            <span v-if="hovered" class="tabular font-medium text-slate-800">
                {{ days[hovered.day] }} às {{ hours[hovered.hour] }}h ·
                {{ values[hovered.day]?.[hovered.hour] }} pessoas ({{
                    Math.round(
                        ((values[hovered.day]?.[hovered.hour] ?? 0) /
                            capacity) *
                            100,
                    )
                }}% da capacidade)
            </span>
            <span v-else>
                Pico:
                <strong class="font-semibold text-slate-800"
                    >{{ days[peak.day] }} às {{ hours[peak.hour] }}h</strong
                >
                ({{ peak.value }} pessoas)
            </span>
        </figcaption>
    </figure>
</template>
