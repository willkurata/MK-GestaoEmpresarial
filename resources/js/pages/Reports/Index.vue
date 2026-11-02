<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { index as reportsIndex } from '@/routes/reports';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Relatórios', href: reportsIndex() }],
    },
});

const props = defineProps<{
    filters: { period: string; startDate: string; endDate: string };
    summary: {
        salesCount: number;
        salesTotal: string;
        cashTotal: string;
        pixTotal: string;
        refundedCount: number;
        refundedTotal: string;
    };
    recentSales: Array<{
        id: number;
        occurredAt: string;
        operator: string;
        paymentMethod: string;
        total: string;
        status: 'finalizada' | 'estornada';
    }>;
}>();

const form = useForm({
    period: props.filters.period,
    start_date: props.filters.startDate,
    end_date: props.filters.endDate,
});

const quickPeriods = [
    { value: 'today', label: 'Hoje' },
    { value: 'last_7_days', label: '7 dias' },
    { value: 'this_month', label: 'Este mês' },
    { value: 'last_month', label: 'Mês anterior' },
];

const metrics = computed(() => [
    { label: 'Vendas finalizadas', value: props.summary.salesCount.toString(), detail: formatCurrency(props.summary.salesTotal) },
    { label: 'Recebido em dinheiro', value: formatCurrency(props.summary.cashTotal), detail: 'Pagamentos confirmados' },
    { label: 'Recebido via Pix', value: formatCurrency(props.summary.pixTotal), detail: 'Pagamentos confirmados' },
    { label: 'Vendas estornadas', value: props.summary.refundedCount.toString(), detail: formatCurrency(props.summary.refundedTotal) },
]);

function formatCurrency(value: string | number): string {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value));
}

function formatDate(value: string): string {
    const [year, month, day] = value.split('-');

    return `${day}/${month}/${year}`;
}

function applyFilters(): void {
    form.get(reportsIndex(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: () => {
            form.period = props.filters.period;
            form.start_date = props.filters.startDate;
            form.end_date = props.filters.endDate;
        },
    });
}

function selectPeriod(period: string): void {
    form.period = period;

    if (period !== 'custom') {
        applyFilters();
    }
}
</script>

<template>
    <Head title="Relatórios" />

    <main class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <header class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
            <div>
                <p class="text-sm font-semibold tracking-wide text-blue-700 dark:text-blue-400">FINANCEIRO</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">Relatório de vendas</h1>
                <p class="mt-2 text-sm text-muted-foreground">Acompanhe vendas e recebimentos no período escolhido.</p>
            </div>
            <form class="flex flex-wrap items-end gap-2" aria-label="Período do relatório" @submit.prevent="applyFilters">
                <button
                    v-for="period in quickPeriods"
                    :key="period.value"
                    type="button"
                    class="min-h-10 rounded-lg border px-3 text-sm font-medium transition-colors"
                    :class="form.period === period.value ? 'border-primary bg-primary text-primary-foreground' : 'bg-background text-muted-foreground hover:border-primary hover:text-foreground'"
                    :aria-pressed="form.period === period.value"
                    @click="selectPeriod(period.value)"
                >
                    {{ period.label }}
                </button>
                <button
                    type="button"
                    class="min-h-10 rounded-lg border px-3 text-sm font-medium transition-colors"
                    :class="form.period === 'custom' ? 'border-primary bg-primary text-primary-foreground' : 'bg-background text-muted-foreground hover:border-primary hover:text-foreground'"
                    :aria-pressed="form.period === 'custom'"
                    @click="selectPeriod('custom')"
                >
                    Personalizado
                </button>
                <template v-if="form.period === 'custom'">
                    <label class="grid gap-1 text-xs font-medium text-muted-foreground">
                        De
                        <input v-model="form.start_date" type="date" required class="h-10 rounded-md border border-input bg-background px-2 text-sm text-foreground" />
                        <span v-if="form.errors.start_date" class="text-destructive">{{ form.errors.start_date }}</span>
                    </label>
                    <label class="grid gap-1 text-xs font-medium text-muted-foreground">
                        Até
                        <input v-model="form.end_date" type="date" required class="h-10 rounded-md border border-input bg-background px-2 text-sm text-foreground" />
                        <span v-if="form.errors.end_date" class="text-destructive">{{ form.errors.end_date }}</span>
                    </label>
                    <button type="submit" :disabled="form.processing" class="min-h-10 rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground disabled:opacity-60">
                        {{ form.processing ? 'Aplicando…' : 'Aplicar' }}
                    </button>
                </template>
            </form>
        </header>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumo de vendas">
            <article v-for="metric in metrics" :key="metric.label" class="rounded-xl border bg-card p-5 text-card-foreground">
                <p class="text-sm font-medium text-muted-foreground">{{ metric.label }}</p>
                <p class="mt-3 text-2xl font-semibold tracking-tight">{{ metric.value }}</p>
                <p class="mt-2 text-xs text-muted-foreground">{{ metric.detail }}</p>
            </article>
        </section>

        <section class="overflow-hidden rounded-xl border bg-card text-card-foreground" aria-labelledby="sales-list-title">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4 sm:px-6">
                <div>
                    <h2 id="sales-list-title" class="font-semibold">Vendas recentes</h2>
                    <p class="mt-1 text-sm text-muted-foreground">Até 25 movimentações no período filtrado.</p>
                </div>
                <span class="text-sm text-muted-foreground">{{ formatDate(filters.startDate) }} — {{ formatDate(filters.endDate) }}</span>
            </div>
            <div v-if="recentSales.length === 0" class="px-5 py-12 text-center sm:px-6">
                <p class="font-medium">Nenhuma venda neste período</p>
                <p class="mt-1 text-sm text-muted-foreground">Quando houver vendas, elas aparecerão neste relatório.</p>
            </div>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[700px] text-left text-sm">
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-medium sm:px-6">Data e hora</th>
                            <th scope="col" class="px-5 py-3 font-medium">Venda</th>
                            <th scope="col" class="px-5 py-3 font-medium">Operador</th>
                            <th scope="col" class="px-5 py-3 font-medium">Pagamento</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium">Total</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium">Situação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="sale in recentSales" :key="sale.id" class="border-t">
                            <td class="px-5 py-4 sm:px-6">{{ sale.occurredAt }}</td>
                            <th scope="row" class="px-5 py-4 font-medium">#{{ sale.id }}</th>
                            <td class="px-5 py-4">{{ sale.operator }}</td>
                            <td class="px-5 py-4">{{ sale.paymentMethod }}</td>
                            <td class="px-5 py-4 text-right font-medium">{{ formatCurrency(sale.total) }}</td>
                            <td class="px-5 py-4 text-right">
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="sale.status === 'estornada' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'">
                                    {{ sale.status === 'estornada' ? 'Estornada' : 'Finalizada' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</template>
