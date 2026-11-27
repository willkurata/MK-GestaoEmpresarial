<script setup lang="ts">
import { Repeat, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import Card from '../../components/Card.vue';
import StatusPill from '../../components/StatusPill.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
import { daysOverdue, isAtRisk } from '../../lib/billing';
import { formatShortDate, initials } from '../../lib/format';
import { planFor, state } from '../../lib/store';
import { daysSince } from '../../lib/training';
import type { StudentStatus } from '../../lib/types';

const query = ref('');
const statusFilter = ref<'todos' | StudentStatus | 'risco'>('todos');

const counts = computed(() => ({
    todos: state.students.length,
    ativo: state.students.filter((student) => student.status === 'ativo')
        .length,
    inadimplente: state.students.filter(
        (student) => student.status === 'inadimplente',
    ).length,
    risco: state.students.filter((student) => isAtRisk(student, new Date()))
        .length,
    inativo: state.students.filter((student) => student.status === 'inativo')
        .length,
}));

const filters = [
    { value: 'todos', label: 'Todos' },
    { value: 'ativo', label: 'Em dia' },
    { value: 'inadimplente', label: 'Em atraso' },
    { value: 'risco', label: 'Em risco' },
    { value: 'inativo', label: 'Inativos' },
] as const;

const filtered = computed(() => {
    const normalized = query.value.trim().toLocaleLowerCase('pt-BR');

    return state.students.filter((student) => {
        if (
            normalized &&
            !student.name.toLocaleLowerCase('pt-BR').includes(normalized)
        ) {
            return false;
        }

        if (statusFilter.value === 'todos') {
            return true;
        }

        if (statusFilter.value === 'risco') {
            return isAtRisk(student, new Date());
        }

        return student.status === statusFilter.value;
    });
});

function lastSeen(iso: string | null): string {
    const days = daysSince(iso, new Date());

    if (days === null) {
        return 'Nunca';
    }

    return days === 0 ? 'Hoje' : days === 1 ? 'Ontem' : `Há ${days} dias`;
}
</script>

<template>
    <AdminLayout title="Alunos" :subtitle="`${counts.todos} cadastrados`">
        <Card>
            <div
                class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
            >
                <div class="flex gap-1.5 overflow-x-auto">
                    <button
                        v-for="filter in filters"
                        :key="filter.value"
                        type="button"
                        class="flex min-h-9 shrink-0 items-center gap-1.5 rounded-full px-3 text-sm font-medium"
                        :class="
                            statusFilter === filter.value
                                ? 'bg-ink-950 text-white'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                        "
                        :aria-pressed="statusFilter === filter.value"
                        @click="statusFilter = filter.value"
                    >
                        {{ filter.label }}
                        <span class="tabular opacity-70">{{
                            counts[filter.value]
                        }}</span>
                    </button>
                </div>
                <label class="relative block md:w-72">
                    <span class="sr-only">Buscar aluno</span>
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                    />
                    <input
                        v-model="query"
                        type="search"
                        placeholder="Buscar por nome"
                        class="border-line h-10 w-full rounded-lg border bg-white pr-3 pl-9 text-sm outline-none focus:border-slate-400"
                    />
                </label>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[40rem] text-sm">
                    <thead>
                        <tr
                            class="border-line border-b text-left text-xs text-slate-500"
                        >
                            <th class="py-2 pr-3 font-medium">Aluno</th>
                            <th class="py-2 pr-3 font-medium">Plano</th>
                            <th class="py-2 pr-3 font-medium">Situação</th>
                            <th class="py-2 pr-3 font-medium">Vencimento</th>
                            <th class="py-2 font-medium">Último treino</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="student in filtered"
                            :key="student.id"
                            class="border-b border-slate-100 hover:bg-slate-50"
                        >
                            <td class="py-2.5 pr-3">
                                <div class="flex items-center gap-2.5">
                                    <span
                                        class="grid size-8 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600"
                                    >
                                        {{ initials(student.name) }}
                                    </span>
                                    <span class="font-medium">{{
                                        student.name
                                    }}</span>
                                </div>
                            </td>
                            <td class="py-2.5 pr-3 text-slate-600">
                                <span class="flex items-center gap-1.5">
                                    {{ planFor(student)?.name }}
                                    <Repeat
                                        v-if="student.hasPixAutomatico"
                                        class="size-3.5 text-lime-700"
                                        aria-label="Pix Automático ativo"
                                    />
                                </span>
                            </td>
                            <td class="py-2.5 pr-3">
                                <StatusPill
                                    :status="student.status"
                                    tone="light"
                                />
                            </td>
                            <td
                                class="tabular py-2.5 pr-3"
                                :class="
                                    daysOverdue(student, new Date()) > 0 &&
                                    student.status !== 'inativo'
                                        ? 'text-warn-ink font-medium'
                                        : 'text-slate-600'
                                "
                            >
                                {{ formatShortDate(student.dueDate) }}
                            </td>
                            <td class="py-2.5 text-slate-600">
                                {{ lastSeen(student.lastCheckInAt) }}
                            </td>
                        </tr>
                        <tr v-if="filtered.length === 0">
                            <td
                                colspan="5"
                                class="py-8 text-center text-slate-500"
                            >
                                Nenhum aluno encontrado.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </AdminLayout>
</template>
