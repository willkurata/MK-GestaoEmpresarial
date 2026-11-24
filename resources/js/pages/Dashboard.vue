<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    HandCoins,
    Package,
    Receipt,
    ShoppingCart,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import MetricCard from '@/components/dashboard/MetricCard.vue';
import PeriodTabs from '@/components/dashboard/PeriodTabs.vue';
import RiskBadge from '@/components/dashboard/RiskBadge.vue';
import TrendChart from '@/components/dashboard/TrendChart.vue';
import OpenCashSessionAlert from '@/components/OpenCashSessionAlert.vue';
import type { RiskAssessment, RiskLevel } from '@/components/dashboard/types';
import { usePermissions } from '@/composables/usePermissions';
import { formatCurrency, formatDate, formatPercent } from '@/lib/format';
import { dashboard } from '@/routes';
import { index as expensesIndex } from '@/routes/expenses';
import { index as cashierIndex } from '@/routes/pos';
import { index as productsIndex } from '@/routes/products';
import type { OpenCashSession } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const props = defineProps<{
    company: { name: string };
    companyUser: { name: string; role: string };
    openCashSessions: OpenCashSession[];
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
        refundTotal: string;
        netInflow: string;
        previousSalesTotal: string;
        salesVariation: number | null;
    };
    team: { activeUsersCount: number; openSessionCount: number };
    risk: RiskAssessment;
    trend: {
        granularity: 'day' | 'month';
        points: Array<{
            key: string;
            label: string;
            sales: string;
            expenses: string;
        }>;
    };
    recentActivities: Array<{
        id: string;
        type: 'sale' | 'expense';
        title: string;
        date: string;
        amount: string;
    }>;
}>();

const form = useForm({
    period: props.filters.period,
    start_date: props.filters.startDate,
    end_date: props.filters.endDate,
});

const periods = [
    { value: 'today', label: 'Hoje' },
    { value: 'last_7_days', label: '7 dias' },
    { value: 'this_month', label: 'Este mês' },
    { value: 'last_month', label: 'Mês anterior' },
    { value: 'custom', label: 'Personalizado' },
];

const permissions = usePermissions();
const roleLabel = computed(() =>
    props.companyUser.role === 'Funcionario'
        ? 'Funcionário'
        : props.companyUser.role,
);

const quickActions = computed(() =>
    [
        {
            title: 'Iniciar uma venda',
            description: 'Abrir o PDV',
            href: cashierIndex(),
            icon: ShoppingCart,
            primary: true,
            visible: true,
        },
        {
            title: 'Produtos',
            description: permissions.value.manageProducts
                ? 'Cadastrar e editar preços'
                : 'Consultar preços',
            href: productsIndex(),
            icon: Package,
            primary: false,
            visible: true,
        },
        {
            title: 'Despesas',
            description: 'Registrar uma despesa',
            href: expensesIndex(),
            icon: Receipt,
            primary: false,
            visible: permissions.value.manageExpenses,
        },
    ].filter((action) => action.visible),
);

const hasSales = computed(() => props.summary.salesCount > 0);

const paymentSplit = computed(() => {
    const cash = Number(props.summary.cashTotal);
    const pix = Number(props.summary.pixTotal);
    const total = cash + pix;

    return {
        cash,
        pix,
        cashShare: total ? (cash / total) * 100 : 0,
        pixShare: total ? (pix / total) * 100 : 0,
    };
});

const expenseSplit = computed(() => {
    const fixed = Number(props.summary.fixedExpenseTotal);
    const variable = Number(props.summary.variableExpenseTotal);
    const total = fixed + variable;

    return {
        fixedShare: total ? (fixed / total) * 100 : 0,
        variableShare: total ? (variable / total) * 100 : 0,
    };
});

const trendSeries = computed(() => [
    {
        key: 'sales',
        label: 'Vendas',
        colorClass: 'text-dash-sales',
        values: props.trend.points.map((point) => Number(point.sales)),
    },
    {
        key: 'expenses',
        label: 'Despesas',
        colorClass: 'text-dash-expenses',
        values: props.trend.points.map((point) => Number(point.expenses)),
    },
]);

const riskScale: Array<{ level: RiskLevel; label: string; rule: string }> = [
    {
        level: 'ok',
        label: 'Saudável',
        rule: 'Despesas abaixo de 60% da receita',
    },
    {
        level: 'attention',
        label: 'Atenção',
        rule: '60–80% da receita, queda de 10% ou falta no caixa',
    },
    {
        level: 'high',
        label: 'Risco alto',
        rule: '80–100% da receita ou queda de 30%',
    },
    { level: 'critical', label: 'Crítico', rule: 'Despesas acima da receita' },
];

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

    <main class="mk-dash dark min-h-full px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 lg:gap-8">
            <header
                class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end"
            >
                <div>
                    <p
                        class="text-xs font-semibold tracking-[0.2em] text-dash-accent"
                    >
                        MK GESTÃO
                    </p>
                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        <h1
                            class="text-2xl font-semibold tracking-tight sm:text-3xl"
                        >
                            Visão geral
                        </h1>
                        <RiskBadge
                            :level="risk.level"
                            :label="risk.label"
                            size="md"
                        />
                    </div>
                    <p class="mt-2 text-sm text-dash-muted">
                        {{ company.name }} · {{ companyUser.name }}
                        <span
                            class="ml-1 rounded-md bg-white/5 px-1.5 py-0.5 text-xs ring-1 ring-white/10 ring-inset"
                            >{{ roleLabel }}</span
                        >
                    </p>
                </div>
                <form
                    class="flex flex-col items-start gap-3 lg:items-end"
                    aria-label="Filtro do dashboard"
                    @submit.prevent="applyFilters"
                >
                    <PeriodTabs
                        :periods="periods"
                        :model-value="form.period"
                        label="Período"
                        @select="selectPeriod"
                    />
                    <div
                        v-if="form.period === 'custom'"
                        class="flex flex-wrap items-end gap-2"
                    >
                        <label
                            class="grid gap-1 text-xs font-medium text-dash-muted"
                        >
                            De
                            <input
                                v-model="form.start_date"
                                type="date"
                                required
                                class="h-9 rounded-lg border border-dash-line bg-dash-surface px-2 text-sm text-dash-text"
                            />
                            <span
                                v-if="form.errors.start_date"
                                class="text-risk-critical"
                                >{{ form.errors.start_date }}</span
                            >
                        </label>
                        <label
                            class="grid gap-1 text-xs font-medium text-dash-muted"
                        >
                            Até
                            <input
                                v-model="form.end_date"
                                type="date"
                                required
                                class="h-9 rounded-lg border border-dash-line bg-dash-surface px-2 text-sm text-dash-text"
                            />
                            <span
                                v-if="form.errors.end_date"
                                class="text-risk-critical"
                                >{{ form.errors.end_date }}</span
                            >
                        </label>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="h-9 rounded-lg bg-dash-accent-strong px-4 text-sm font-semibold text-white disabled:opacity-60"
                        >
                            {{ form.processing ? 'Aplicando…' : 'Aplicar' }}
                        </button>
                    </div>
                    <p class="text-xs text-dash-muted">
                        {{ formatDate(filters.startDate) }} —
                        {{ formatDate(filters.endDate) }}
                    </p>
                </form>
            </header>

            <OpenCashSessionAlert
                :sessions="openCashSessions"
                hint="Para trocar de operador, quem abriu o caixa fecha o turno no PDV informando o dinheiro contado."
            />

            <nav aria-label="Acesso rápido" class="grid gap-3 sm:grid-cols-3">
                <Link
                    v-for="action in quickActions"
                    :key="action.title"
                    :href="action.href"
                    class="group flex items-center gap-4 rounded-2xl border p-4 transition-colors"
                    :class="
                        action.primary
                            ? 'border-dash-accent/50 bg-dash-accent-strong text-white shadow-[0_10px_40px_-16px_var(--dash-accent)] hover:bg-[#2b6ed8]'
                            : 'border-dash-line bg-dash-surface/70 hover:border-dash-accent/40 hover:bg-dash-raised'
                    "
                >
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-xl"
                        :class="
                            action.primary
                                ? 'bg-white/15'
                                : 'bg-dash-accent/10 text-dash-accent'
                        "
                        aria-hidden="true"
                    >
                        <component :is="action.icon" class="size-5" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold">{{
                            action.title
                        }}</span>
                        <span
                            class="block text-xs"
                            :class="
                                action.primary
                                    ? 'text-blue-100'
                                    : 'text-dash-muted'
                            "
                            >{{ action.description }}</span
                        >
                    </span>
                    <ArrowRight
                        class="size-4 shrink-0 transition-transform group-hover:translate-x-0.5"
                        aria-hidden="true"
                    />
                </Link>
            </nav>

            <section
                class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5"
                aria-label="Indicadores principais"
                :class="{ 'opacity-60 transition-opacity': form.processing }"
            >
                <MetricCard
                    label="Vendas"
                    :value="hasSales ? formatCurrency(summary.salesTotal) : '—'"
                    :icon="ShoppingCart"
                    :variation="summary.salesVariation"
                    :hint="`${summary.salesCount} venda(s)`"
                    accent
                />
                <MetricCard
                    label="Fluxo de caixa"
                    :value="
                        hasSales || Number(summary.refundTotal) > 0
                            ? formatCurrency(summary.netInflow)
                            : '—'
                    "
                    :icon="Wallet"
                    :hint="
                        Number(summary.refundTotal) > 0
                            ? `Estornos: ${formatCurrency(summary.refundTotal)}`
                            : 'Pix + dinheiro − estornos'
                    "
                />
                <MetricCard
                    label="Estoque"
                    value="—"
                    :icon="Package"
                    badge="Em breve"
                    hint="Controle previsto para a V2"
                />
                <MetricCard
                    label="Inadimplência"
                    value="—"
                    :icon="HandCoins"
                    badge="Não se aplica"
                    hint="Vendas pagas no ato"
                />
                <MetricCard
                    label="Usuários ativos"
                    :value="String(team.activeUsersCount)"
                    :icon="Users"
                    :hint="
                        team.openSessionCount
                            ? `${team.openSessionCount} caixa(s) aberto(s)`
                            : 'Nenhum caixa aberto'
                    "
                />
            </section>

            <section
                class="grid gap-6 xl:grid-cols-[minmax(0,1.75fr)_minmax(0,1fr)]"
            >
                <article
                    class="dash-panel p-5 sm:p-6"
                    aria-labelledby="trend-title"
                >
                    <header
                        class="mb-5 flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <h2 id="trend-title" class="font-semibold">
                                Tendência de vendas e despesas
                            </h2>
                            <p class="mt-1 text-sm text-dash-muted">
                                {{
                                    trend.granularity === 'month'
                                        ? 'Por mês'
                                        : 'Por dia'
                                }}
                                · {{ trend.points[0]?.label }} a
                                {{
                                    trend.points[trend.points.length - 1]?.label
                                }}
                            </p>
                        </div>
                        <dl class="flex gap-5 text-right text-xs">
                            <div>
                                <dt class="text-dash-muted">Vendas</dt>
                                <dd class="mt-0.5 text-sm font-semibold">
                                    {{ formatCurrency(summary.salesTotal) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-dash-muted">Despesas</dt>
                                <dd class="mt-0.5 text-sm font-semibold">
                                    {{ formatCurrency(summary.expenseTotal) }}
                                </dd>
                            </div>
                        </dl>
                    </header>
                    <TrendChart
                        :labels="trend.points.map((point) => point.label)"
                        :series="trendSeries"
                        caption="Vendas finalizadas e despesas lançadas por período"
                    />
                    <p
                        class="mt-4 flex items-center gap-2 text-xs text-dash-muted"
                    >
                        <Package class="size-3.5" aria-hidden="true" />
                        Tendência de estoque: disponível quando o controle de
                        estoque entrar (V2).
                    </p>
                </article>

                <article
                    class="dash-panel flex flex-col p-5 sm:p-6"
                    aria-labelledby="risk-title"
                >
                    <header class="flex items-start justify-between gap-3">
                        <div>
                            <h2 id="risk-title" class="font-semibold">
                                Indicador de risco
                            </h2>
                            <p class="mt-1 text-sm text-dash-muted">
                                Calculado com os dados do período
                            </p>
                        </div>
                        <RiskBadge :level="risk.level" :label="risk.label" />
                    </header>
                    <ul class="mt-5 grid gap-2 text-sm">
                        <li
                            v-for="reason in risk.reasons"
                            :key="reason"
                            class="rounded-xl border border-dash-line bg-white/[0.02] px-3 py-2.5"
                        >
                            {{ reason }}
                        </li>
                    </ul>
                    <ol
                        class="mt-auto grid gap-2 pt-6 text-xs"
                        aria-label="Escala de risco"
                    >
                        <li
                            v-for="step in riskScale"
                            :key="step.level"
                            class="flex items-center gap-3 rounded-lg px-2 py-1.5"
                            :class="{
                                'bg-white/5 ring-1 ring-white/10':
                                    step.level === risk.level,
                            }"
                        >
                            <RiskBadge
                                :level="step.level"
                                :label="step.label"
                            />
                            <span class="text-dash-muted">{{ step.rule }}</span>
                        </li>
                    </ol>
                </article>
            </section>

            <section class="grid gap-6 lg:grid-cols-3">
                <article
                    class="dash-panel p-5 sm:p-6"
                    aria-labelledby="payments-title"
                >
                    <h2 id="payments-title" class="font-semibold">
                        Forma de pagamento
                    </h2>
                    <p class="mt-1 text-sm text-dash-muted">
                        Vendas finalizadas no período
                    </p>
                    <template v-if="hasSales">
                        <div
                            class="mt-6 flex h-2.5 gap-0.5 overflow-hidden rounded-full"
                            role="img"
                            :aria-label="`Dinheiro ${formatPercent(paymentSplit.cashShare)}, Pix ${formatPercent(paymentSplit.pixShare)}`"
                        >
                            <div
                                class="rounded-full bg-dash-sales"
                                :style="{ width: `${paymentSplit.pixShare}%` }"
                            />
                            <div
                                class="rounded-full bg-cyan-300"
                                :style="{ width: `${paymentSplit.cashShare}%` }"
                            />
                        </div>
                        <dl class="mt-5 grid gap-3 text-sm">
                            <div class="flex items-center justify-between">
                                <dt
                                    class="flex items-center gap-2 text-dash-muted"
                                >
                                    <span
                                        class="size-2.5 rounded-sm bg-dash-sales"
                                    />Pix ·
                                    {{ formatPercent(paymentSplit.pixShare) }}
                                </dt>
                                <dd class="font-semibold">
                                    {{ formatCurrency(summary.pixTotal) }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt
                                    class="flex items-center gap-2 text-dash-muted"
                                >
                                    <span
                                        class="size-2.5 rounded-sm bg-cyan-300"
                                    />Dinheiro ·
                                    {{ formatPercent(paymentSplit.cashShare) }}
                                </dt>
                                <dd class="font-semibold">
                                    {{ formatCurrency(summary.cashTotal) }}
                                </dd>
                            </div>
                        </dl>
                    </template>
                    <p
                        v-else
                        class="mt-6 rounded-xl border border-dashed border-dash-line px-4 py-8 text-center text-sm text-dash-muted"
                    >
                        Nenhuma venda neste período.
                    </p>
                </article>

                <article
                    class="dash-panel p-5 sm:p-6"
                    aria-labelledby="expenses-title"
                >
                    <h2 id="expenses-title" class="font-semibold">
                        Despesas por tipo
                    </h2>
                    <p class="mt-1 text-sm text-dash-muted">
                        {{ summary.expenseCount }} lançamento(s) ·
                        {{ formatCurrency(summary.expenseTotal) }}
                    </p>
                    <template v-if="summary.expenseCount">
                        <div
                            class="mt-6 flex h-2.5 gap-0.5 overflow-hidden rounded-full"
                            role="img"
                            :aria-label="`Fixas ${formatPercent(expenseSplit.fixedShare)}, variáveis ${formatPercent(expenseSplit.variableShare)}`"
                        >
                            <div
                                class="rounded-full bg-dash-expenses"
                                :style="{
                                    width: `${expenseSplit.fixedShare}%`,
                                }"
                            />
                            <div
                                class="rounded-full bg-amber-300"
                                :style="{
                                    width: `${expenseSplit.variableShare}%`,
                                }"
                            />
                        </div>
                        <dl class="mt-5 grid gap-3 text-sm">
                            <div class="flex items-center justify-between">
                                <dt
                                    class="flex items-center gap-2 text-dash-muted"
                                >
                                    <span
                                        class="size-2.5 rounded-sm bg-dash-expenses"
                                    />Fixas ·
                                    {{ formatPercent(expenseSplit.fixedShare) }}
                                </dt>
                                <dd class="font-semibold">
                                    {{
                                        formatCurrency(
                                            summary.fixedExpenseTotal,
                                        )
                                    }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt
                                    class="flex items-center gap-2 text-dash-muted"
                                >
                                    <span
                                        class="size-2.5 rounded-sm bg-amber-300"
                                    />Variáveis ·
                                    {{
                                        formatPercent(
                                            expenseSplit.variableShare,
                                        )
                                    }}
                                </dt>
                                <dd class="font-semibold">
                                    {{
                                        formatCurrency(
                                            summary.variableExpenseTotal,
                                        )
                                    }}
                                </dd>
                            </div>
                        </dl>
                    </template>
                    <p
                        v-else
                        class="mt-6 rounded-xl border border-dashed border-dash-line px-4 py-8 text-center text-sm text-dash-muted"
                    >
                        Sem despesas neste período.
                    </p>
                </article>

                <article
                    class="dash-panel overflow-hidden"
                    aria-labelledby="activity-title"
                >
                    <header class="border-b border-dash-line px-5 py-4 sm:px-6">
                        <h2 id="activity-title" class="font-semibold">
                            Atividade recente
                        </h2>
                        <p class="mt-1 text-sm text-dash-muted">
                            Últimas vendas e despesas
                        </p>
                    </header>
                    <ul
                        v-if="recentActivities.length"
                        class="divide-y divide-dash-line"
                    >
                        <li
                            v-for="activity in recentActivities"
                            :key="activity.id"
                            class="flex items-center justify-between gap-3 px-5 py-3 sm:px-6"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <span
                                    class="grid size-8 shrink-0 place-items-center rounded-lg text-sm"
                                    :class="
                                        activity.type === 'sale'
                                            ? 'bg-dash-sales/15 text-dash-sales'
                                            : 'bg-dash-expenses/15 text-dash-expenses'
                                    "
                                    aria-hidden="true"
                                    >{{
                                        activity.type === 'sale' ? '↗' : '↙'
                                    }}</span
                                >
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">
                                        {{ activity.title }}
                                    </p>
                                    <p class="text-xs text-dash-muted">
                                        {{
                                            activity.type === 'sale'
                                                ? 'Venda'
                                                : 'Despesa'
                                        }}
                                        · {{ activity.date }}
                                    </p>
                                </div>
                            </div>
                            <strong class="shrink-0 text-sm"
                                >{{ activity.type === 'expense' ? '−' : '+'
                                }}{{ formatCurrency(activity.amount) }}</strong
                            >
                        </li>
                    </ul>
                    <p
                        v-else
                        class="px-6 py-10 text-center text-sm text-dash-muted"
                    >
                        Nenhuma movimentação neste período.
                    </p>
                </article>
            </section>
        </div>
    </main>
</template>
