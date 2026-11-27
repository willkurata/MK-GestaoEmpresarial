<script setup lang="ts">
import { computed, ref } from 'vue';
import ClassCard from '../../components/ClassCard.vue';
import StudentLayout from '../../layouts/StudentLayout.vue';
import { reserveWithFeedback } from '../../lib/booking';
import {
    formatShortDate,
    formatWeekday,
    toLocalDateString,
} from '../../lib/format';
import { cancelClass, currentStudent, state } from '../../lib/store';

const days = computed(() =>
    [
        ...new Set(
            state.classes.map((gymClass) =>
                toLocalDateString(new Date(gymClass.startsAt)),
            ),
        ),
    ].sort(),
);
const selectedDay = ref(days.value[0] ?? '');

const classesOfDay = computed(() =>
    state.classes
        .filter(
            (gymClass) =>
                toLocalDateString(new Date(gymClass.startsAt)) ===
                selectedDay.value,
        )
        .sort((a, b) => a.startsAt.localeCompare(b.startsAt)),
);

const myBookings = computed(
    () =>
        state.classes.filter((gymClass) =>
            gymClass.bookedStudentIds.includes(currentStudent.value.id),
        ).length,
);
</script>

<template>
    <StudentLayout title="Aulas">
        <div class="flex gap-2" role="tablist" aria-label="Dia">
            <button
                v-for="(day, index) in days"
                :key="day"
                type="button"
                role="tab"
                class="flex min-h-16 flex-1 flex-col items-center justify-center rounded-2xl border text-sm"
                :class="
                    selectedDay === day
                        ? 'text-ink-950 border-lime-400 bg-lime-400'
                        : 'border-ink-800 bg-ink-900 text-ink-300'
                "
                :aria-selected="selectedDay === day"
                @click="selectedDay = day"
            >
                <span class="font-semibold capitalize">{{
                    index === 0 ? 'Hoje' : formatWeekday(day)
                }}</span>
                <span class="text-xs opacity-80">{{
                    formatShortDate(day)
                }}</span>
            </button>
        </div>

        <p class="text-ink-400 text-sm">
            {{ myBookings }}
            {{
                myBookings === 1 ? 'reserva confirmada' : 'reservas confirmadas'
            }}
            · cancelar libera a vaga para quem está na fila
        </p>

        <div class="space-y-3">
            <ClassCard
                v-for="gymClass in classesOfDay"
                :key="gymClass.id"
                :gym-class="gymClass"
                :student-id="currentStudent.id"
                @book="reserveWithFeedback(gymClass.id)"
                @cancel="cancelClass(gymClass.id)"
            />
        </div>
    </StudentLayout>
</template>
