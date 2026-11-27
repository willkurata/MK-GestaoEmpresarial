<script setup lang="ts">
import { CalendarDays, Clock, Flame, Trophy } from '@lucide/vue';
import { computed } from 'vue';
import WeekBars from '../../components/charts/WeekBars.vue';
import StudentLayout from '../../layouts/StudentLayout.vue';
import { formatShortDate, formatTime, formatWeekday } from '../../lib/format';
import { state } from '../../lib/store';
import { startOfWeek, weeklySessions, weeklyStreak } from '../../lib/training';

const WEEKLY_GOAL = 3;
const today = new Date();

const weeks = computed(() => weeklySessions(state.checkInHistory, today, 12));
const streak = computed(() =>
    weeklyStreak(state.checkInHistory, today, WEEKLY_GOAL),
);
const total30 = computed(
    () =>
        state.checkInHistory.filter(
            (iso) =>
                today.getTime() - new Date(iso).getTime() <= 30 * 86_400_000,
        ).length,
);
const bestWeek = computed(() =>
    Math.max(0, ...weeks.value.map((week) => week.sessions)),
);

/** Calendário da semana atual (seg–dom) marcando os dias treinados. */
const currentWeekDays = computed(() => {
    const monday = startOfWeek(today);
    const trained = new Set(
        state.checkInHistory.map((iso) => new Date(iso).toDateString()),
    );

    return Array.from({ length: 7 }, (_, index) => {
        const day = new Date(monday);
        day.setDate(day.getDate() + index);

        return {
            label: formatWeekday(day.toISOString()),
            date: day.getDate(),
            trained: trained.has(day.toDateString()),
            isToday: day.toDateString() === today.toDateString(),
        };
    });
});

function sentenceCase(text: string): string {
    return text.charAt(0).toUpperCase() + text.slice(1);
}

const recent = computed(() =>
    [...state.checkInHistory].sort().reverse().slice(0, 8),
);
</script>

<template>
    <StudentLayout title="Treinos">
        <section class="grid grid-cols-7 gap-1.5" aria-label="Esta semana">
            <div
                v-for="day in currentWeekDays"
                :key="day.date"
                class="flex flex-col items-center gap-1 rounded-2xl py-2"
                :class="day.isToday ? 'bg-ink-800' : ''"
            >
                <span class="text-ink-400 text-[11px] capitalize">{{
                    day.label
                }}</span>
                <span
                    class="tabular grid size-9 place-items-center rounded-full text-sm font-semibold"
                    :class="
                        day.trained
                            ? 'text-ink-950 bg-lime-400'
                            : 'text-ink-300'
                    "
                    :aria-label="
                        day.trained
                            ? `Dia ${day.date}: treinou`
                            : `Dia ${day.date}`
                    "
                >
                    {{ day.date }}
                </span>
            </div>
        </section>

        <div class="grid grid-cols-3 gap-3">
            <div class="bg-ink-900 rounded-2xl p-3">
                <Flame class="text-warn size-5" />
                <p class="font-display tabular mt-2 text-2xl font-bold">
                    {{ streak }}
                </p>
                <p class="text-ink-400 text-xs">semanas seguidas</p>
            </div>
            <div class="bg-ink-900 rounded-2xl p-3">
                <CalendarDays class="size-5 text-lime-400" />
                <p class="font-display tabular mt-2 text-2xl font-bold">
                    {{ total30 }}
                </p>
                <p class="text-ink-400 text-xs">treinos em 30 dias</p>
            </div>
            <div class="bg-ink-900 rounded-2xl p-3">
                <Trophy class="text-ink-300 size-5" />
                <p class="font-display tabular mt-2 text-2xl font-bold">
                    {{ bestWeek }}
                </p>
                <p class="text-ink-400 text-xs">melhor semana</p>
            </div>
        </div>

        <section class="bg-ink-900 rounded-3xl p-5">
            <h2 class="font-display mb-6 font-bold">Frequência — 12 semanas</h2>
            <WeekBars :weeks="weeks" :goal="WEEKLY_GOAL" />
        </section>

        <section>
            <h2 class="font-display mb-3 text-lg font-bold">
                Últimos check-ins
            </h2>
            <p
                v-if="recent.length === 0"
                class="border-ink-700 text-ink-400 rounded-2xl border border-dashed p-6 text-center"
            >
                Seu histórico aparece aqui depois do primeiro check-in.
            </p>
            <ul v-else class="divide-ink-800 bg-ink-900 divide-y rounded-2xl">
                <li
                    v-for="iso in recent"
                    :key="iso"
                    class="flex items-center justify-between px-4 py-3"
                >
                    <span
                        >{{ sentenceCase(formatWeekday(iso)) }},
                        {{ formatShortDate(iso) }}</span
                    >
                    <span
                        class="text-ink-400 tabular flex items-center gap-1.5 text-sm"
                        ><Clock class="size-3.5" />{{ formatTime(iso) }}</span
                    >
                </li>
            </ul>
            <p class="text-ink-400 mt-3 text-xs">
                O histórico é gerado automaticamente pelos check-ins na catraca.
            </p>
        </section>
    </StudentLayout>
</template>
