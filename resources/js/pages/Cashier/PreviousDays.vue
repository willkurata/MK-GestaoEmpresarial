<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { index as cashierIndex } from '@/routes/pos';
import { index as previousDaysIndex } from '@/routes/pos/previous-days';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Caixa', href: cashierIndex() },
            { title: 'Turnos de dias anteriores', href: previousDaysIndex() },
        ],
    },
});

type PendingSession = {
    id: number;
    operator: string;
    openedAt: string;
    daysOpen: number;
    openingAmount: string;
    expectedCash: string;
    pixTotal: string;
    continuedAt: string | null;
};
type HistorySession = {
    id: number;
    operator: string;
    openedAt: string;
    closedAt: string | null;
    daysOpen: number;
    closingMode: 'conferido' | 'automatico' | null;
    closedBy: string | null;
    continuedAt: string | null;
    expectedAmount: string | null;
    countedAmount: string | null;
    differenceAmount: string | null;
    transfer: { nextSessionId: number; operator: string; transferredAmount: string; openingAmount: string; difference: string | null } | null;
};

const props = defineProps<{
    filters: { startDate: string; endDate: string };
    pendingSessions: PendingSession[];
    history: HistorySession[];
    summary: {
        pendingCount: number;
        pendingExpectedCash: string;
        crossedDayCount: number;
        autoClosedCount: number;
        transferCount: number;
        transferDifferenceTotal: string;
    };
}>();

const form = useForm({ start_date: props.filters.startDate, end_date: props.filters.endDate });
const formatCurrency = (amount: number | string): string => Number(amount).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const daysLabel = (days: number): string => (days === 0 ? 'mesmo dia' : days === 1 ? '1 dia' : `${days} dias`);
const differenceClass = (amount: string | null): string => (amount === null || Number(amount) === 0 ? '' : Number(amount) < 0 ? 'text-destructive' : 'text-emerald-700 dark:text-emerald-400');

function applyFilters(): void {
    form.get(previousDaysIndex().url, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <Head title="Turnos de dias anteriores" />
    <main class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-muted-foreground">CAIXA</p>
                <h1 class="mt-1 text-3xl font-semibold tracking-tight">Turnos de dias anteriores</h1>
                <p class="mt-2 max-w-2xl text-sm text-muted-foreground">Caixas que ficaram abertos de um dia para o outro, como foram resolvidos e o saldo transferido para o turno seguinte.</p>
            </div>
            <Link :href="cashierIndex()" class="text-sm font-medium text-primary underline-offset-4 hover:underline">Voltar ao caixa</Link>
        </header>

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" aria-label="Resumo">
            <div class="rounded-xl border bg-card p-4"><p class="text-sm text-muted-foreground">Abertos de dias anteriores</p><p class="mt-1 text-2xl font-semibold">{{ props.summary.pendingCount }}</p><p class="text-xs text-muted-foreground">{{ formatCurrency(props.summary.pendingExpectedCash) }} esperados em dinheiro</p></div>
            <div class="rounded-xl border bg-card p-4"><p class="text-sm text-muted-foreground">Turnos que viraram o dia</p><p class="mt-1 text-2xl font-semibold">{{ props.summary.crossedDayCount }}</p><p class="text-xs text-muted-foreground">no período</p></div>
            <div class="rounded-xl border bg-card p-4"><p class="text-sm text-muted-foreground">Fechados sem conferência</p><p class="mt-1 text-2xl font-semibold">{{ props.summary.autoClosedCount }}</p><p class="text-xs text-muted-foreground">fechamento automático</p></div>
            <div class="rounded-xl border bg-card p-4"><p class="text-sm text-muted-foreground">Transferências de saldo</p><p class="mt-1 text-2xl font-semibold">{{ props.summary.transferCount }}</p><p class="text-xs" :class="differenceClass(props.summary.transferDifferenceTotal)">Diferença total {{ formatCurrency(props.summary.transferDifferenceTotal) }}</p></div>
        </section>

        <section class="overflow-hidden rounded-xl border bg-card" aria-labelledby="pending-title">
            <div class="border-b px-5 py-4 sm:px-6">
                <h2 id="pending-title" class="font-semibold">Ainda abertos</h2>
                <p class="mt-1 text-sm text-muted-foreground">Resolva no Caixa: continuar no mesmo turno, conferir e fechar ou fechar automaticamente.</p>
            </div>
            <p v-if="props.pendingSessions.length === 0" class="px-5 py-8 text-center text-sm text-muted-foreground sm:px-6">Nenhum caixa aberto de dias anteriores.</p>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="bg-muted/50 text-muted-foreground"><tr><th scope="col" class="px-4 py-3 font-medium">Turno</th><th scope="col" class="px-4 py-3 font-medium">Operador</th><th scope="col" class="px-4 py-3 font-medium">Aberto em</th><th scope="col" class="px-4 py-3 font-medium">Tempo</th><th scope="col" class="px-4 py-3 text-right font-medium">Abertura</th><th scope="col" class="px-4 py-3 text-right font-medium">Dinheiro esperado</th><th scope="col" class="px-4 py-3 text-right font-medium">Pix</th></tr></thead>
                    <tbody>
                        <tr v-for="session in props.pendingSessions" :key="session.id" class="border-t">
                            <th scope="row" class="px-4 py-3 font-medium">#{{ session.id }}</th>
                            <td class="px-4 py-3">{{ session.operator }}</td>
                            <td class="px-4 py-3">{{ session.openedAt }}<span v-if="session.continuedAt" class="block text-xs text-muted-foreground">Continuado em {{ session.continuedAt }}</span></td>
                            <td class="px-4 py-3">{{ daysLabel(session.daysOpen) }}</td>
                            <td class="px-4 py-3 text-right">{{ formatCurrency(session.openingAmount) }}</td>
                            <td class="px-4 py-3 text-right font-medium">{{ formatCurrency(session.expectedCash) }}</td>
                            <td class="px-4 py-3 text-right">{{ formatCurrency(session.pixTotal) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border bg-card" aria-labelledby="history-title">
            <div class="flex flex-wrap items-end justify-between gap-4 border-b px-5 py-4 sm:px-6">
                <div>
                    <h2 id="history-title" class="font-semibold">Histórico</h2>
                    <p class="mt-1 text-sm text-muted-foreground">Turnos abertos no período que viraram o dia, foram continuados, fechados automaticamente ou tiveram saldo transferido.</p>
                </div>
                <form class="flex flex-wrap items-end gap-3" @submit.prevent="applyFilters">
                    <label class="grid gap-1 text-xs font-medium" for="previous-days-start">De
                        <input id="previous-days-start" v-model="form.start_date" type="date" class="h-10 rounded-md border border-input bg-background px-2 text-sm font-normal" />
                    </label>
                    <label class="grid gap-1 text-xs font-medium" for="previous-days-end">Até
                        <input id="previous-days-end" v-model="form.end_date" type="date" class="h-10 rounded-md border border-input bg-background px-2 text-sm font-normal" />
                    </label>
                    <button type="submit" :disabled="form.processing" class="min-h-10 rounded-md border px-3 text-sm font-medium hover:bg-muted disabled:opacity-50">Filtrar</button>
                </form>
            </div>
            <p v-if="form.errors.end_date" class="px-5 pt-3 text-sm text-destructive sm:px-6">{{ form.errors.end_date }}</p>
            <p v-if="props.history.length === 0" class="px-5 py-8 text-center text-sm text-muted-foreground sm:px-6">Nenhum turno de dia anterior no período.</p>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="bg-muted/50 text-muted-foreground"><tr><th scope="col" class="px-4 py-3 font-medium">Turno</th><th scope="col" class="px-4 py-3 font-medium">Abertura → fechamento</th><th scope="col" class="px-4 py-3 font-medium">Fechamento</th><th scope="col" class="px-4 py-3 text-right font-medium">Esperado</th><th scope="col" class="px-4 py-3 text-right font-medium">Contado</th><th scope="col" class="px-4 py-3 text-right font-medium">Diferença</th><th scope="col" class="px-4 py-3 font-medium">Transferência</th></tr></thead>
                    <tbody>
                        <tr v-for="session in props.history" :key="session.id" class="border-t align-top">
                            <th scope="row" class="px-4 py-3 font-medium">#{{ session.id }}<span class="block font-normal text-muted-foreground">{{ session.operator }}</span></th>
                            <td class="px-4 py-3">{{ session.openedAt }} → {{ session.closedAt ?? '—' }}<span class="block text-xs text-muted-foreground">{{ daysLabel(session.daysOpen) }}<template v-if="session.continuedAt"> · continuado em {{ session.continuedAt }}</template></span></td>
                            <td class="px-4 py-3">
                                <span v-if="session.closingMode === 'automatico'" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900 dark:bg-amber-900/40 dark:text-amber-100">Automático · sem conferência</span>
                                <span v-else class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium">Conferido</span>
                                <span v-if="session.closedBy" class="mt-1 block text-xs text-muted-foreground">por {{ session.closedBy }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">{{ session.expectedAmount === null ? '—' : formatCurrency(session.expectedAmount) }}</td>
                            <td class="px-4 py-3 text-right">{{ session.countedAmount === null ? '—' : formatCurrency(session.countedAmount) }}</td>
                            <td class="px-4 py-3 text-right" :class="differenceClass(session.differenceAmount)">{{ session.differenceAmount === null ? '—' : formatCurrency(session.differenceAmount) }}</td>
                            <td class="px-4 py-3">
                                <template v-if="session.transfer">
                                    {{ formatCurrency(session.transfer.transferredAmount) }} → turno #{{ session.transfer.nextSessionId }} ({{ session.transfer.operator }})
                                    <span class="block text-xs text-muted-foreground">Aberto com {{ formatCurrency(session.transfer.openingAmount) }}<template v-if="session.transfer.difference !== null && Number(session.transfer.difference) !== 0"> · <span :class="differenceClass(session.transfer.difference)">diferença {{ formatCurrency(session.transfer.difference) }}</span></template></span>
                                </template>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</template>
