<script setup lang="ts">
import { ChevronRight, Flame, Repeat, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import AccessCode from '../../components/AccessCode.vue';
import ClassCard from '../../components/ClassCard.vue';
import StudentLayout from '../../layouts/StudentLayout.vue';
import { daysUntilDue } from '../../lib/billing';
import { reserveWithFeedback } from '../../lib/booking';
import { firstName, toLocalDateString } from '../../lib/format';
import { cancelClass, currentStudent, planFor, state } from '../../lib/store';
import { weeklySessions, weeklyStreak } from '../../lib/training';

const today = new Date();
const student = currentStudent;
const plan = computed(() => planFor(student.value));
const dueIn = computed(() => daysUntilDue(student.value, new Date()));

const todaysClasses = computed(() =>
    state.classes
        .filter(
            (gymClass) =>
                toLocalDateString(new Date(gymClass.startsAt)) ===
                toLocalDateString(today),
        )
        .sort((a, b) => a.startsAt.localeCompare(b.startsAt)),
);

const thisWeek = computed(
    () => weeklySessions(state.checkInHistory, new Date(), 1)[0]?.sessions ?? 0,
);
const streak = computed(() =>
    weeklyStreak(state.checkInHistory, new Date(), 3),
);

const greeting = computed(() => {
    const hour = new Date().getHours();

    return hour < 12 ? 'Bom dia' : hour < 18 ? 'Boa tarde' : 'Boa noite';
});
</script>

<template>
    <StudentLayout>
        <template #title
            >{{ greeting }}, {{ firstName(student.name) }}</template
        >

        <!-- QR de acesso: um toque até a catraca -->
        <section
            class="border-ink-800 bg-ink-900 rounded-3xl border p-5 text-center"
            aria-labelledby="access-title"
        >
            <h2 id="access-title" class="text-ink-300 text-sm font-medium">
                Seu acesso
            </h2>
            <div class="mt-4">
                <AccessCode :secret="student.id" />
            </div>
            <p class="text-ink-400 mt-3 text-xs">
                Funciona sem internet · aponte para o leitor da catraca
            </p>
        </section>

        <!-- Alerta de mensalidade (D-3 / vencida) -->
        <a
            v-if="dueIn <= 5"
            href="#/aluno/pagamento"
            class="flex items-center gap-3 rounded-2xl border p-4"
            :class="
                dueIn < 0
                    ? 'border-danger/40 bg-danger/10'
                    : 'border-warn/40 bg-warn/10'
            "
        >
            <TriangleAlert
                class="size-6 shrink-0"
                :class="dueIn < 0 ? 'text-danger' : 'text-warn'"
            />
            <span class="flex-1">
                <span class="block font-semibold">
                    {{
                        dueIn < 0
                            ? `Mensalidade vencida há ${-dueIn} dias`
                            : dueIn === 0
                              ? 'Mensalidade vence hoje'
                              : `Mensalidade vence em ${dueIn} dias`
                    }}
                </span>
                <span class="text-ink-300 text-sm"
                    >Pague por Pix em segundos</span
                >
            </span>
            <ChevronRight class="text-ink-400 size-5" />
        </a>
        <p
            v-else-if="student.hasPixAutomatico"
            class="text-ink-400 flex items-center gap-2 text-sm"
        >
            <Repeat class="size-4 text-lime-400" /> Pix Automático ativo · plano
            {{ plan?.name }}
        </p>

        <!-- Progresso -->
        <a href="#/aluno/treinos" class="grid grid-cols-2 gap-3">
            <div class="bg-ink-900 rounded-2xl p-4">
                <p class="text-ink-400 text-xs">Esta semana</p>
                <p class="font-display tabular mt-1 text-3xl font-bold">
                    {{ thisWeek
                    }}<span class="text-ink-400 text-base">/3 treinos</span>
                </p>
            </div>
            <div class="bg-ink-900 rounded-2xl p-4">
                <p class="text-ink-400 text-xs">Sequência</p>
                <p
                    class="font-display tabular mt-1 flex items-center gap-1.5 text-3xl font-bold"
                >
                    <Flame class="text-warn size-6" />{{ streak
                    }}<span class="text-ink-400 text-base">sem.</span>
                </p>
            </div>
        </a>

        <!-- Aulas de hoje -->
        <section aria-labelledby="today-classes">
            <div class="mb-3 flex items-center justify-between">
                <h2 id="today-classes" class="font-display text-lg font-bold">
                    Aulas de hoje
                </h2>
                <a href="#/aluno/aulas" class="text-sm text-lime-400"
                    >Ver agenda</a
                >
            </div>
            <div class="space-y-3">
                <ClassCard
                    v-for="gymClass in todaysClasses"
                    :key="gymClass.id"
                    :gym-class="gymClass"
                    :student-id="student.id"
                    @book="reserveWithFeedback(gymClass.id)"
                    @cancel="cancelClass(gymClass.id)"
                />
            </div>
        </section>
    </StudentLayout>
</template>
