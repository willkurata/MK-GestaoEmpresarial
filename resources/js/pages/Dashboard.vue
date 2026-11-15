<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowDownLeft,
    ArrowRight,
    ArrowUpRight,
    Package,
    ReceiptText,
    ShoppingCart,
} from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import PeriodFilter from '@/components/PeriodFilter.vue';
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
    recentActivities: Array<{
        id: string;
        type: 'sale' | 'expense';
        title: string;
        date: string;
        amount: string;
    }>;
}>();

const quickActions = [
    {
        title: 'Iniciar uma venda',
        description: 'Abrir o caixa e registrar vendas',
        href: cashierIndex(),
        icon: ShoppingCart,
        primary: true,
    },
    {
        title: 'Produtos',
        description: 'Cadastrar e ajustar preços',
        href: productsIndex(),
        icon: Package,
        primary: false,
    },
    {
        title: 'Despesas',
        description: 'Registrar uma despesa',
        href: expensesIndex(),
        icon: ReceiptText,
        primary: false,
    },
];

const metrics = computed(() => [
    {
        label: 'Vendas no período',
        value: props.summary.salesCount
            ? formatCurrency(props.summary.salesTotal)
            : '—',
        description: `${props.summary.salesCount} venda(s) finalizada(s)`,
    },
    {
        label: 'Despesas no período',
        value: props.summary.expenseCount
            ? formatCurrency(props.summary.expenseTotal)
            : '—',
        description: `${props.summary.expenseCount} lançamento(s)`,
    },
    {
        label: 'Vendas em dinheiro',
        value: props.summary.salesCount
            ? formatCurrency(props.summary.cashTotal)
            : '—',
        description: 'Recebimentos confirmados',
    },
    {
        label: 'Vendas via Pix',
        value: props.summary.salesCount
            ? formatCurrency(props.summary.pixTotal)
            : '—',
        description: 'Recebimentos confirmados',
    },
]);

const salesPaymentTotal = computed(
    () => Number(props.summary.cashTotal) + Number(props.summary.pixTotal),
);
const salesCashPercent = computed(() =>
    salesPaymentTotal.value
        ? (Number(props.summary.cashTotal) / salesPaymentTotal.value) * 100
        : 0,
);
const expenseTypeTotal = computed(
    () =>
        Number(props.summary.fixedExpenseTotal) +
        Number(props.summary.variableExpenseTotal),
);
const expenseFixedPercent = computed(() =>
    expenseTypeTotal.value
        ? (Number(props.summary.fixedExpenseTotal) / expenseTypeTotal.value) *
          100
        : 0,
);

const salesDonutStyle = computed(() => ({
    background: `conic-gradient(var(--primary) 0 ${salesCashPercent.value}%, #7dd3fc ${salesCashPercent.value}% 100%)`,
}));
const expensesDonutStyle = computed(() => ({
    background: `conic-gradient(var(--primary) 0 ${expenseFixedPercent.value}%, #f59e0b ${expenseFixedPercent.value}% 100%)`,
}));

function formatCurrency(value: string | number): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(Number(value));
}

function formatPercent(value: number): string {
    return `${Math.round(value)}%`;
}

function formatDate(value: string): string {
    const [year, month, day] = value.split('-');

    return `${day}/${month}/${year}`;
}

const roleLabel = (role: string): string =>
    role === 'Funcionario' ? 'Funcionário' : role;
</script>

<template>
    <Head title="Dashboard" />

    <main
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8"
    >
        <PageHeader
            eyebrow="Visão geral"
            :title="`Olá, ${companyUser.name.split(' ')[0]}`"
            :description="`${company.name} · ${roleLabel(companyUser.role)}`"
        >
            <template #actions>
                <PeriodFilter
                    :url="dashboard().url"
                    :filters="filters"
                    label="Período do dashboard"
                />
            </template>
        </PageHeader>

        <section aria-labelledby="quick-actions-title">
            <h2 id="quick-actions-title" class="sr-only">Acesso rápido</h2>
            <div class="grid gap-3 sm:grid-cols-3">
                <Link
                    v-for="action in quickActions"
                    :key="action.title"
                    :href="action.href"
                    class="group flex min-h-20 items-center gap-4 rounded-xl border p-4 transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :class="
                        action.primary
                            ? 'border-primary bg-primary text-primary-foreground hover:bg-primary/90'
                            : 'bg-card text-card-foreground hover:border-primary/40 hover:bg-primary/5'
                    "
                >
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-lg"
                        :class="
                            action.primary
                                ? 'bg-white/15'
                                : 'bg-primary/10 text-primary'
                        "
                    >
                        <component
                            :is="action.icon"
                            class="size-5"
                            aria-hidden="true"
                        />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold">{{
                            action.title
                        }}</span>
                        <span
                            class="mt-0.5 block text-xs"
                            :class="
                                action.primary
                                    ? 'text-primary-foreground/80'
                                    : 'text-muted-foreground'
                            "
                            >{{ action.description }}</span
                        >
                    </span>
                    <ArrowRight
                        class="size-4 shrink-0 transition-transform group-hover:translate-x-0.5"
                        aria-hidden="true"
                    />
                </Link>
            </div>
        </section>

        <section
            class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
            aria-label="Indicadores do período"
        >
            <article
                v-for="metric in metrics"
                :key="metric.label"
                class="rounded-xl border bg-card p-5 text-card-foreground"
            >
                <p class="text-sm font-medium text-muted-foreground">
                    {{ metric.label }}
                </p>
                <p
                    class="mt-3 text-2xl font-semibold tracking-tight tabular-nums"
                >
                    {{ metric.value }}
                </p>
                <p class="mt-2 text-xs text-muted-foreground">
                    {{ metric.description }}
                </p>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <article
                class="rounded-xl border bg-card p-5 text-card-foreground sm:p-6"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">
                            Vendas por forma de pagamento
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Vendas finalizadas no período
                        </p>
                    </div>
                    <span
                        class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary tabular-nums"
                        >{{ formatCurrency(summary.salesTotal) }}</span
                    >
                </div>
                <div
                    v-if="summary.salesCount"
                    class="mt-6 flex min-h-48 flex-col items-center justify-center gap-6 sm:flex-row"
                >
                    <div
                        class="relative size-36 shrink-0 rounded-full"
                        :style="salesDonutStyle"
                        role="img"
                        :aria-label="`Distribuição: dinheiro ${formatCurrency(summary.cashTotal)}, Pix ${formatCurrency(summary.pixTotal)}`"
                    >
                        <div
                            class="absolute inset-5 grid place-content-center rounded-full bg-card text-center"
                        >
                            <span class="text-xs text-muted-foreground"
                                >Vendas</span
                            >
                            <strong class="text-lg tabular-nums">{{
                                summary.salesCount
                            }}</strong>
                        </div>
                    </div>
                    <dl class="w-full max-w-xs space-y-2">
                        <div
                            class="flex items-center justify-between rounded-lg bg-muted/50 px-4 py-3 text-sm"
                        >
                            <dt class="flex items-center gap-2">
                                <span
                                    class="size-2.5 rounded-full bg-primary"
                                    aria-hidden="true"
                                ></span
                                >Dinheiro
                                <span class="text-xs text-muted-foreground">{{
                                    formatPercent(salesCashPercent)
                                }}</span>
                            </dt>
                            <dd class="font-semibold tabular-nums">
                                {{ formatCurrency(summary.cashTotal) }}
                            </dd>
                        </div>
                        <div
                            class="flex items-center justify-between rounded-lg bg-muted/50 px-4 py-3 text-sm"
                        >
                            <dt class="flex items-center gap-2">
                                <span
                                    class="size-2.5 rounded-full bg-sky-300"
                                    aria-hidden="true"
                                ></span
                                >Pix
                                <span class="text-xs text-muted-foreground">{{
                                    formatPercent(100 - salesCashPercent)
                                }}</span>
                            </dt>
                            <dd class="font-semibold tabular-nums">
                                {{ formatCurrency(summary.pixTotal) }}
                            </dd>
                        </div>
                    </dl>
                </div>
                <div
                    v-else
                    class="mt-6 flex min-h-48 flex-col items-center justify-center rounded-lg border border-dashed bg-muted/30 px-6 py-8 text-center"
                >
                    <h3 class="text-sm font-semibold">
                        Nenhuma venda neste período
                    </h3>
                    <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                        Quando houver vendas registradas, o resumo aparecerá
                        aqui.
                    </p>
                    <Link
                        :href="cashierIndex()"
                        class="mt-4 inline-flex min-h-9 items-center rounded-md border bg-card px-3 text-sm font-medium hover:bg-muted"
                        >Ir para o caixa</Link
                    >
                </div>
            </article>

            <article
                class="rounded-xl border bg-card p-5 text-card-foreground sm:p-6"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">Despesas por tipo</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Lançamentos no período
                        </p>
                    </div>
                    <span
                        class="rounded-full bg-muted px-2.5 py-1 text-xs font-medium tabular-nums"
                        >{{ formatCurrency(summary.expenseTotal) }}</span
                    >
                </div>
                <div
                    v-if="summary.expenseCount"
                    class="mt-6 flex min-h-48 flex-col items-center justify-center gap-6 sm:flex-row"
                >
                    <div
                        class="relative size-36 shrink-0 rounded-full"
                        :style="expensesDonutStyle"
                        role="img"
                        :aria-label="`Distribuição: despesas fixas ${formatCurrency(summary.fixedExpenseTotal)}, variáveis ${formatCurrency(summary.variableExpenseTotal)}`"
                    >
                        <div
                            class="absolute inset-5 grid place-content-center rounded-full bg-card text-center"
                        >
                            <span class="text-xs text-muted-foreground"
                                >Lançamentos</span
                            >
                            <strong class="text-lg tabular-nums">{{
                                summary.expenseCount
                            }}</strong>
                        </div>
                    </div>
                    <dl class="w-full max-w-xs space-y-2">
                        <div
                            class="flex items-center justify-between rounded-lg bg-muted/50 px-4 py-3 text-sm"
                        >
                            <dt class="flex items-center gap-2">
                                <span
                                    class="size-2.5 rounded-full bg-primary"
                                    aria-hidden="true"
                                ></span
                                >Fixas
                                <span class="text-xs text-muted-foreground">{{
                                    formatPercent(expenseFixedPercent)
                                }}</span>
                            </dt>
                            <dd class="font-semibold tabular-nums">
                                {{ formatCurrency(summary.fixedExpenseTotal) }}
                            </dd>
                        </div>
                        <div
                            class="flex items-center justify-between rounded-lg bg-muted/50 px-4 py-3 text-sm"
                        >
                            <dt class="flex items-center gap-2">
                                <span
                                    class="size-2.5 rounded-full bg-amber-500"
                                    aria-hidden="true"
                                ></span
                                >Variáveis
                                <span class="text-xs text-muted-foreground">{{
                                    formatPercent(100 - expenseFixedPercent)
                                }}</span>
                            </dt>
                            <dd class="font-semibold tabular-nums">
                                {{
                                    formatCurrency(summary.variableExpenseTotal)
                                }}
                            </dd>
                        </div>
                    </dl>
                </div>
                <div
                    v-else
                    class="mt-6 flex min-h-48 flex-col items-center justify-center rounded-lg border border-dashed bg-muted/30 px-5 py-8 text-center"
                >
                    <h3 class="text-sm font-semibold">
                        Sem despesas para exibir
                    </h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Os lançamentos do período aparecerão neste resumo.
                    </p>
                    <Link
                        :href="expensesIndex()"
                        class="mt-4 inline-flex min-h-9 items-center rounded-md border bg-card px-3 text-sm font-medium hover:bg-muted"
                        >Registrar despesa</Link
                    >
                </div>
            </article>
        </section>

        <section
            class="overflow-hidden rounded-xl border bg-card text-card-foreground"
            aria-labelledby="activity-title"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-2 border-b px-5 py-4 sm:px-6"
            >
                <div>
                    <h2 id="activity-title" class="font-semibold">
                        Atividade recente
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Vendas e despesas no período selecionado
                    </p>
                </div>
                <span class="text-xs font-medium text-muted-foreground"
                    >{{ formatDate(filters.startDate) }} —
                    {{ formatDate(filters.endDate) }}</span
                >
            </div>
            <ul v-if="recentActivities.length" class="divide-y">
                <li
                    v-for="activity in recentActivities"
                    :key="activity.id"
                    class="flex items-center justify-between gap-3 px-5 py-3 sm:px-6"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            class="grid size-9 shrink-0 place-items-center rounded-lg"
                            :class="
                                activity.type === 'sale'
                                    ? 'bg-success/10 text-success'
                                    : 'bg-warning/10 text-warning'
                            "
                            aria-hidden="true"
                        >
                            <ArrowUpRight
                                v-if="activity.type === 'sale'"
                                class="size-4"
                            />
                            <ArrowDownLeft v-else class="size-4" />
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">
                                {{ activity.title }}
                            </p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{
                                    activity.type === 'sale'
                                        ? 'Venda'
                                        : 'Despesa'
                                }}
                                · {{ activity.date }}
                            </p>
                        </div>
                    </div>
                    <strong
                        class="shrink-0 text-sm tabular-nums"
                        :class="activity.type === 'sale' ? 'text-success' : ''"
                        >{{ activity.type === 'expense' ? '−' : '+'
                        }}{{ formatCurrency(activity.amount) }}</strong
                    >
                </li>
            </ul>
            <div v-else class="px-6 py-10 text-center">
                <h3 class="text-sm font-semibold">
                    Nenhuma movimentação neste período
                </h3>
                <p class="mt-1 text-sm text-muted-foreground">
                    As vendas e despesas recentes serão listadas aqui.
                </p>
            </div>
        </section>
    </main>
</template>
