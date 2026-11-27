<script setup lang="ts">
import { computed, ref } from 'vue';
import { formatShortDate } from '../../lib/format';
import type { WeekSummary } from '../../lib/training';

/** Treinos por semana (superfície escura do app do aluno). */
const props = defineProps<{ weeks: WeekSummary[]; goal: number }>();

const hovered = ref<number | null>(null);
const maxValue = computed(() =>
    Math.max(props.goal + 1, ...props.weeks.map((week) => week.sessions)),
);
</script>

<template>
    <figure>
        <div
            class="relative flex h-32 items-end gap-1.5"
            role="img"
            :aria-label="`Treinos por semana nas últimas ${weeks.length} semanas`"
        >
            <div
                class="border-ink-600 pointer-events-none absolute inset-x-0 border-t border-dashed"
                :style="{ bottom: `${(goal / maxValue) * 100}%` }"
            >
                <span
                    class="bg-ink-900 text-ink-300 absolute -top-2.5 left-0 z-10 rounded pr-1.5 text-[10px] leading-5"
                    >meta {{ goal }}x</span
                >
            </div>
            <button
                v-for="(week, index) in weeks"
                :key="week.weekStart"
                type="button"
                class="group relative flex h-full flex-1 items-end"
                :aria-label="`Semana de ${formatShortDate(week.weekStart)}: ${week.sessions} treinos`"
                @pointerenter="hovered = index"
                @pointerleave="hovered = null"
                @focus="hovered = index"
                @blur="hovered = null"
            >
                <span
                    class="w-full rounded-t-[4px] transition-colors"
                    :class="[
                        week.sessions >= goal ? 'bg-lime-400' : 'bg-ink-600',
                        hovered === index ? 'brightness-125' : '',
                    ]"
                    :style="{
                        height: `${Math.max(week.sessions === 0 ? 0 : 6, (week.sessions / maxValue) * 100)}%`,
                    }"
                />
            </button>
        </div>
        <figcaption class="text-ink-400 mt-2 flex justify-between text-[11px]">
            <span>{{ formatShortDate(weeks[0]?.weekStart ?? '') }}</span>
            <span v-if="hovered !== null" class="text-ink-100">
                Semana de {{ formatShortDate(weeks[hovered]!.weekStart) }}:
                {{ weeks[hovered]!.sessions }} treinos
            </span>
            <span>esta semana</span>
        </figcaption>
    </figure>
</template>
