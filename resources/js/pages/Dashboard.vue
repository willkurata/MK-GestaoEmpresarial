<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { dashboard } from '@/routes';
import { index as cashierIndex } from '@/routes/pos';
import { index as productsIndex } from '@/routes/products';
import { index as expensesIndex } from '@/routes/expenses';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const props = defineProps<{
    company: { name: string };
    companyUser: { name: string; role: string };
    filters: { period: string; startDate: string; endDate: string };
    summary: {
        salesCount: number;
        salesTotal: string;
        cashTotal: string;
        pixTotal: string;
        expenseCount: number;
        expenseTotal: string;
        fixedExpenseTotal: string;
        variableExpenseTotal: string;
    };
    recentActivities: Array<{ id: string; type: 'sale' | 'expense'; title: string; date: string; amount: string }>;
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

const quickActions = [
    { title: 'Iniciar uma venda', description: 'Abrir o PDV e registrar uma venda', href: cashierIndex(), primary: true },
    { title: 'Produtos', description: 'Cadastrar preços e códigos de barras', href: productsIndex(), primary: false },
    { title: 'Despesas', description: 'Registrar uma despesa', href: expensesIndex(), primary: false },
];

const metrics = computed(() => [
    { label: 'Vendas no período', value: props.summary.salesCount ? formatCurrency(props.summary.salesTotal) : '—', description: `${props.summary.salesCount} venda(s) finalizada(s)` },
    { label: 'Despesas no período', value: props.summary.expenseCount ? formatCurrency(props.summary.expenseTotal) : '—', description: `${props.summary.expenseCount} lançamento(s)` },
    { label: 'Vendas em dinheiro', value: props.summary.salesCount ? formatCurrency(props.summary.cashTotal) : '—', description: 'Recebimentos confirmados' },
    { label: 'Vendas via Pix', value: props.summary.salesCount ? formatCurrency(props.summary.pixTotal) : '—', description: 'Recebimentos confirmados' },
]);

const salesPaymentTotal = computed(() => Number(props.summary.cashTotal) + Number(props.summary.pixTotal));
const salesCashPercent = computed(() => salesPaymentTotal.value ? Number(props.summary.cashTotal) / salesPaymentTotal.value * 100 : 0);
const expenseTypeTotal = computed(() => Number(props.summary.fixedExpenseTotal) + Number(props.summary.variableExpenseTotal));
const expenseFixedPercent = computed(() => expenseTypeTotal.value ? Number(props.summary.fixedExpenseTotal) / expenseTypeTotal.value * 100 : 0);

const salesDonutStyle = computed(() => ({ background: `conic-gradient(#2563eb 0 ${salesCashPercent.value}%, #7dd3fc ${salesCashPercent.value}% 100%)` }));
const expensesDonutStyle = computed(() => ({ background: `conic-gradient(#2563eb 0 ${expenseFixedPercent.value}%, #f59e0b ${expenseFixedPercent.value}% 100%)` }));

function formatCurrency(value: string | number): string {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value));
}

function formatDate(value: string): string {
    const [year, month, day] = value.split('-');

    return `${day}/${month}/${year}`;
}

function applyFilters(): void {
    form.get(dashboard().url, {
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
    <Head title="Dashboard" />

    <main class="min-h-full bg-slate-50/70 px-4 py-6 text-slate-900 sm:px-6 lg:px-8 dark:bg-slate-950 dark:text-slate-100">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
            <header class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                <div>
                    <p class="text-sm font-semibold tracking-wide text-blue-700 dark:text-blue-400">MK GESTÃO</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">Visão geral</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ company.name }} · {{ companyUser.name }} ({{ companyUser.role }})</p>
                </div>
                <form class="flex flex-wrap items-end gap-2" aria-label="Filtro do dashboard" @submit.prevent="applyFilters">
                    <button
                        v-for="period in quickPeriods"
                        :key="period.value"
                        type="button"
                        class="rounded-lg border px-3 py-2 text-sm font-medium transition-colors"
                        :class="form.period === period.value ? 'border-blue-700 bg-blue-700 text-white shadow-sm' : 'border-slate-200 bg-white text-slate-600 hover:border-blue-200 hover:text-blue-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300'"
                        :aria-pressed="form.period === period.value"
                        @click="selectPeriod(period.value)"
                    >
                        {{ period.label }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border px-3 py-2 text-sm font-medium transition-colors"
                        :class="form.period === 'custom' ? 'border-blue-700 bg-blue-700 text-white shadow-sm' : 'border-slate-200 bg-white text-slate-600 hover:border-blue-200 hover:text-blue-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300'"
                        :aria-pressed="form.period === 'custom'"
                        @click="selectPeriod('custom')"
                    >
                        Personalizado
                    </button>
                    <template v-if="form.period === 'custom'">
                        <label class="flex flex-col gap-1 text-xs font-medium text-slate-600 dark:text-slate-300">
                            De
                            <input v-model="form.start_date" type="date" class="rounded-lg border-slate-200 bg-white text-sm dark:border-slate-700 dark:bg-slate-900" required />
                            <span v-if="form.errors.start_date" class="text-red-600">{{ form.errors.start_date }}</span>
                        </label>
                        <label class="flex flex-col gap-1 text-xs font-medium text-slate-600 dark:text-slate-300">
                            Até
                            <input v-model="form.end_date" type="date" class="rounded-lg border-slate-200 bg-white text-sm dark:border-slate-700 dark:bg-slate-900" required />
                            <span v-if="form.errors.end_date" class="text-red-600">{{ form.errors.end_date }}</span>
                        </label>
                        <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:opacity-60">
                            {{ form.processing ? 'Aplicando…' : 'Aplicar' }}
                        </button>
                    </template>
                </form>
            </header>

            <section aria-label="Acesso rápido">
                <div class="mb-3">
                    <h2 class="font-semibold">Acesso rápido</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Atalhos para as tarefas do dia a dia.</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <Link v-for="action in quickActions" :key="action.title" :href="action.href" class="group flex min-h-24 items-center justify-between gap-4 rounded-xl border p-4 shadow-sm transition-colors" :class="action.primary ? 'border-blue-700 bg-blue-700 text-white hover:bg-blue-800' : 'border-slate-200 bg-white text-slate-900 hover:border-blue-200 hover:bg-blue-50/50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800'">
                        <span>
                            <span class="block font-semibold">{{ action.title }}</span>
                            <span class="mt-1 block text-xs" :class="action.primary ? 'text-blue-100' : 'text-slate-500 dark:text-slate-400'">{{ action.description }}</span>
                        </span>
                        <span class="text-xl" aria-hidden="true">→</span>
                    </Link>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Indicadores do período">
                <article v-for="metric in metrics" :key="metric.label" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/40 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-300">{{ metric.label }}</p>
                    <p class="mt-4 text-2xl font-semibold tracking-tight">{{ metric.value }}</p>
                    <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ metric.description }}</p>
                </article>
            </section>

            <section class="grid gap-6 xl:grid-cols-[1.6fr_1fr]">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/40 sm:p-6 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="font-semibold">Vendas por forma de pagamento</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Vendas finalizadas no período</p>
                        </div>
                        <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 dark:bg-blue-950/60 dark:text-blue-300">{{ formatCurrency(summary.salesTotal) }}</span>
                    </div>
                    <div v-if="summary.salesCount" class="mt-6 flex min-h-56 flex-col items-center justify-center gap-6 sm:flex-row">
                        <div class="relative size-40 shrink-0 rounded-full" :style="salesDonutStyle" role="img" :aria-label="`Distribuição: dinheiro ${formatCurrency(summary.cashTotal)}, Pix ${formatCurrency(summary.pixTotal)}`">
                            <div class="absolute inset-5 grid place-content-center rounded-full bg-white text-center dark:bg-slate-900">
                                <span class="text-xs text-slate-500 dark:text-slate-400">Vendas</span>
                                <strong class="text-lg">{{ summary.salesCount }}</strong>
                            </div>
                        </div>
                        <div class="w-full max-w-xs space-y-3">
                            <div class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3 dark:bg-slate-950/60"><span class="flex items-center gap-2 text-sm"><span class="size-2.5 rounded-full bg-blue-600"></span>Dinheiro</span><strong class="text-sm">{{ formatCurrency(summary.cashTotal) }}</strong></div>
                            <div class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3 dark:bg-slate-950/60"><span class="flex items-center gap-2 text-sm"><span class="size-2.5 rounded-full bg-sky-300"></span>Pix</span><strong class="text-sm">{{ formatCurrency(summary.pixTotal) }}</strong></div>
                        </div>
                    </div>
                    <div v-else class="mt-6 flex min-h-56 flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/70 px-6 py-8 text-center dark:border-slate-800 dark:bg-slate-950/50">
                        <h3 class="text-sm font-semibold">Nenhuma venda neste período</h3>
                        <p class="mt-1 max-w-sm text-sm leading-6 text-slate-500 dark:text-slate-400">Quando houver vendas registradas, o resumo aparecerá aqui.</p>
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/40 sm:p-6 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
                    <div>
                        <h2 class="font-semibold">Despesas por tipo</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Lançamentos no período</p>
                    </div>
                    <div v-if="summary.expenseCount" class="mt-6 flex min-h-56 flex-col items-center justify-center gap-5">
                        <div class="relative size-36 shrink-0 rounded-full" :style="expensesDonutStyle" role="img" :aria-label="`Distribuição: despesas fixas ${formatCurrency(summary.fixedExpenseTotal)}, variáveis ${formatCurrency(summary.variableExpenseTotal)}`">
                            <div class="absolute inset-5 grid place-content-center rounded-full bg-white text-center dark:bg-slate-900">
                                <span class="text-xs text-slate-500 dark:text-slate-400">Total</span>
                                <strong class="text-sm">{{ formatCurrency(summary.expenseTotal) }}</strong>
                            </div>
                        </div>
                        <div class="w-full space-y-3">
                            <div class="flex items-center justify-between text-sm"><span class="flex items-center gap-2"><span class="size-2.5 rounded-full bg-blue-600"></span>Fixas</span><strong>{{ formatCurrency(summary.fixedExpenseTotal) }}</strong></div>
                            <div class="flex items-center justify-between text-sm"><span class="flex items-center gap-2"><span class="size-2.5 rounded-full bg-amber-500"></span>Variáveis</span><strong>{{ formatCurrency(summary.variableExpenseTotal) }}</strong></div>
                        </div>
                    </div>
                    <div v-else class="mt-6 flex min-h-56 flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/70 px-5 py-8 text-center dark:border-slate-800 dark:bg-slate-950/50">
                        <h3 class="text-sm font-semibold">Sem despesas para exibir</h3>
                        <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">Os lançamentos do período aparecerão neste resumo.</p>
                    </div>
                </article>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-200/40 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4 sm:px-6 dark:border-slate-800">
                    <div>
                        <h2 class="font-semibold">Atividade recente</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Vendas e despesas no período selecionado</p>
                    </div>
                    <span class="text-xs font-medium text-slate-400">{{ formatDate(filters.startDate) }} — {{ formatDate(filters.endDate) }}</span>
                </div>
                <ul v-if="recentActivities.length" class="divide-y divide-slate-100 dark:divide-slate-800">
                    <li v-for="activity in recentActivities" :key="activity.id" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 sm:px-6">
                        <div class="flex items-center gap-3">
                            <span class="grid size-10 place-items-center rounded-xl" :class="activity.type === 'sale' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300'" aria-hidden="true">{{ activity.type === 'sale' ? '↗' : '↙' }}</span>
                            <div><p class="text-sm font-medium">{{ activity.title }}</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ activity.type === 'sale' ? 'Venda' : 'Despesa' }} · {{ activity.date }}</p></div>
                        </div>
                        <strong class="text-sm" :class="activity.type === 'sale' ? 'text-emerald-700 dark:text-emerald-300' : 'text-slate-700 dark:text-slate-200'">{{ activity.type === 'expense' ? '−' : '+' }}{{ formatCurrency(activity.amount) }}</strong>
                    </li>
                </ul>
                <div v-else class="px-6 py-10 text-center">
                    <h3 class="text-sm font-semibold">Nenhuma movimentação neste período</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">As vendas e despesas recentes serão listadas aqui.</p>
                </div>
            </section>
        </div>
    </main>
</template>
