<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { HandCoins, Package, ShoppingCart, Users, Wallet } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import MetricCard from '@/components/dashboard/MetricCard.vue';
import PeriodTabs from '@/components/dashboard/PeriodTabs.vue';
import RiskBadge from '@/components/dashboard/RiskBadge.vue';
import TrendChart from '@/components/dashboard/TrendChart.vue';
import type { RiskAssessment, RiskLevel } from '@/components/dashboard/types';
import { formatCurrency, formatDate, formatPercent } from '@/lib/format';
import { show as showCompany } from '@/routes/mk/companies';

type Tone = 'positive' | 'warning' | 'neutral';
type ProductRow = {
    productId: number | null;
    productName: string;
    quantity: number;
    revenue: string;
    grossMargin: number | null;
};

export type CompanyDashboardData = {
    filters: { period: string; startDate: string; endDate: string };
    sales: {
        count: number;
        grossTotal: string;
        discountTotal: string;
        netTotal: string;
        averageTicket: string;
        discountRate: number | null;
        refundedCount: number;
        refundedTotal: string;
        refundRate: number | null;
        byPaymentMethod: { pix: string; dinheiro: string };
        byOperator: Array<{
            operator: string;
            salesCount: number;
            total: string;
            averageTicket: string;
        }>;
    };
    margin: {
        revenueWithCost: string;
        costOfGoodsSold: string;
        grossProfit: string | null;
        grossMargin: number | null;
        costCoverage: number | null;
    };
    result: {
        revenue: string;
        expenses: string;
        revenueMinusExpenses: string;
        costOfGoodsSold: string;
        operatingResult: string | null;
        expenseToRevenueRate: number | null;
    };
    comparison: {
        previousStartDate: string;
        previousEndDate: string;
        netTotal: number | null;
        salesCount: number | null;
        averageTicket: number | null;
        expenseTotal: number | null;
    };
    expenses: {
        count: number;
        total: string;
        fixedTotal: string;
        variableTotal: string;
        fixedShare: number | null;
        topExpenses: Array<{
            description: string;
            entries: number;
            total: string;
            share: number | null;
        }>;
    };
    products: {
        catalogCount: number;
        activeCount: number;
        inactiveCount: number;
        activeWithoutCostCount: number;
        soldDistinctCount: number;
        unitsSold: number;
        bestSellers: ProductRow[];
        worstSellers: ProductRow[];
        topRevenue: ProductRow[];
        lowMargin: ProductRow[];
        activeWithoutSalesCount: number;
        activeWithoutSales: string[];
    };
    cashFlow: {
        inflow: string;
        inflowPix: string;
        inflowCash: string;
        refundOutflow: string;
        refundOutflowPix: string;
        refundOutflowCash: string;
        netInflow: string;
        withdrawals: string;
        sessionCount: number;
        openSessionCount: number;
        closedSessionCount: number;
        differenceTotal: string;
        shortageCount: number;
        surplusCount: number;
    };
    monthlyTrend: Array<{
        month: string;
        label: string;
        salesCount: number;
        revenue: string;
        expenses: string;
        result: string;
    }>;
    insights: Array<{ tone: Tone; title: string; detail: string }>;
    unavailable: { stock: string; delinquency: string };
    risk: RiskAssessment;
    activeUsersCount: number;
};

const props = defineProps<{
    companyId: number;
    dashboard: CompanyDashboardData;
}>();

const quickPeriods = [
    { value: 'today', label: 'Hoje' },
    { value: 'last_7_days', label: '7 dias' },
    { value: 'this_month', label: 'Este mês' },
    { value: 'last_month', label: 'Mês anterior' },
    { value: 'last_90_days', label: '90 dias' },
    { value: 'this_year', label: 'Ano' },
];

const filters = reactive({
    period: props.dashboard.filters.period,
    start_date: props.dashboard.filters.startDate,
    end_date: props.dashboard.filters.endDate,
});
const loading = ref(false);
const errors = ref<Record<string, string>>({});

function applyFilters(): void {
    const query =
        filters.period === 'custom'
            ? {
                  period: 'custom',
                  start_date: filters.start_date,
                  end_date: filters.end_date,
              }
            : { period: filters.period };

    router.get(
        showCompany({ company: props.companyId }, { query }).url,
        {},
        {
            only: ['dashboard', 'errors'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
            onSuccess: () => (errors.value = {}),
            onError: (validationErrors) => (errors.value = validationErrors),
        },
    );
}

function selectPeriod(period: string): void {
    filters.period = period;

    if (period !== 'custom') {
        applyFilters();
    }
}

const paymentSplit = computed(() => {
    const pix = Number(props.dashboard.sales.byPaymentMethod.pix);
    const cash = Number(props.dashboard.sales.byPaymentMethod.dinheiro);
    const total = pix + cash;

    return {
        pixShare: total > 0 ? (pix / total) * 100 : 0,
        cashShare: total > 0 ? (cash / total) * 100 : 0,
        hasData: total > 0,
    };
});

const periods = [...quickPeriods, { value: 'custom', label: 'Personalizado' }];

const secondaryMetrics = computed(() => [
    {
        label: 'Vendas finalizadas',
        value: props.dashboard.sales.count.toLocaleString('pt-BR'),
        variation: props.dashboard.comparison.salesCount,
        higherIsBetter: true,
    },
    {
        label: 'Ticket médio',
        value: props.dashboard.sales.count
            ? formatCurrency(props.dashboard.sales.averageTicket)
            : '—',
        variation: props.dashboard.comparison.averageTicket,
        higherIsBetter: true,
    },
    {
        label: 'Despesas',
        value: formatCurrency(props.dashboard.expenses.total),
        variation: props.dashboard.comparison.expenseTotal,
        higherIsBetter: false,
    },
    {
        label: 'Margem bruta',
        value: formatPercent(props.dashboard.margin.grossMargin),
        variation: null,
        higherIsBetter: true,
    },
    {
        label: 'Receita − despesas',
        value: formatCurrency(props.dashboard.result.revenueMinusExpenses),
        variation: null,
        higherIsBetter: true,
    },
]);

function variationText(value: number | null): string {
    if (value === null) {
        return '';
    }

    return `${value > 0 ? '+' : ''}${value.toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`;
}

function variationClass(value: number | null, higherIsBetter: boolean): string {
    if (value === null || value === 0) {
        return 'text-dash-muted';
    }

    return value > 0 === higherIsBetter ? 'text-risk-ok' : 'text-risk-critical';
}

const trendSeries = computed(() => [
    {
        key: 'revenue',
        label: 'Receita',
        colorClass: 'text-dash-sales',
        values: props.dashboard.monthlyTrend.map((month) =>
            Number(month.revenue),
        ),
    },
    {
        key: 'expenses',
        label: 'Despesas',
        colorClass: 'text-dash-expenses',
        values: props.dashboard.monthlyTrend.map((month) =>
            Number(month.expenses),
        ),
    },
]);

/** Sinais do backend mapeados para a escala de risco (cor + ícone + texto). */
const insightLevels: Record<Tone, { level: RiskLevel; label: string }> = {
    positive: { level: 'ok', label: 'Acertando' },
    warning: { level: 'attention', label: 'Atenção' },
    neutral: { level: 'none', label: 'Observação' },
};
</script>

<template>
    <section
        class="mb-8 grid gap-6"
        aria-labelledby="company-dashboard-title"
        :aria-busy="loading"
    >
        <header
            class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end"
        >
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h2
                        id="company-dashboard-title"
                        class="text-xl font-semibold tracking-tight"
                    >
                        Resumo para o contador
                    </h2>
                    <RiskBadge
                        :level="dashboard.risk.level"
                        :label="dashboard.risk.label"
                        size="md"
                    />
                </div>
                <p class="mt-1 text-sm text-dash-muted">
                    {{ formatDate(dashboard.filters.startDate) }} a
                    {{ formatDate(dashboard.filters.endDate) }} · comparado com
                    {{ formatDate(dashboard.comparison.previousStartDate) }} a
                    {{ formatDate(dashboard.comparison.previousEndDate) }}
                </p>
            </div>
            <form
                class="flex flex-col items-start gap-3 lg:items-end"
                aria-label="Período do resumo"
                @submit.prevent="applyFilters"
            >
                <PeriodTabs
                    :periods="periods"
                    :model-value="filters.period"
                    label="Período"
                    @select="selectPeriod"
                />
                <div
                    v-if="filters.period === 'custom'"
                    class="flex flex-wrap items-end gap-2"
                >
                    <label class="grid gap-1 text-xs text-dash-muted"
                        >De
                        <input
                            v-model="filters.start_date"
                            type="date"
                            required
                            class="h-9 rounded-lg border border-dash-line bg-dash-surface px-2 text-sm text-dash-text"
                        />
                        <span
                            v-if="errors.start_date"
                            class="text-risk-critical"
                            >{{ errors.start_date }}</span
                        >
                    </label>
                    <label class="grid gap-1 text-xs text-dash-muted"
                        >Até
                        <input
                            v-model="filters.end_date"
                            type="date"
                            required
                            class="h-9 rounded-lg border border-dash-line bg-dash-surface px-2 text-sm text-dash-text"
                        />
                        <span
                            v-if="errors.end_date"
                            class="text-risk-critical"
                            >{{ errors.end_date }}</span
                        >
                    </label>
                    <button
                        type="submit"
                        :disabled="loading"
                        class="h-9 rounded-lg bg-dash-accent-strong px-4 text-sm font-semibold text-white disabled:opacity-60"
                    >
                        {{ loading ? 'Aplicando…' : 'Aplicar' }}
                    </button>
                </div>
            </form>
        </header>

        <div
            class="grid gap-6 transition-opacity"
            :class="{ 'opacity-60': loading }"
        >
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <MetricCard
                    label="Receita líquida"
                    :value="formatCurrency(dashboard.sales.netTotal)"
                    :icon="ShoppingCart"
                    :variation="dashboard.comparison.netTotal"
                    :hint="`${dashboard.sales.count} venda(s)`"
                    accent
                />
                <MetricCard
                    label="Fluxo de caixa"
                    :value="formatCurrency(dashboard.cashFlow.netInflow)"
                    :icon="Wallet"
                    :hint="`Estornos: ${formatCurrency(dashboard.cashFlow.refundOutflow)}`"
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
                    :value="String(dashboard.activeUsersCount)"
                    :icon="Users"
                    :hint="`${dashboard.cashFlow.openSessionCount} caixa(s) aberto(s)`"
                />
            </div>

            <dl
                class="dash-panel grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5"
            >
                <div
                    v-for="metric in secondaryMetrics"
                    :key="metric.label"
                    class="px-5 py-4"
                >
                    <dt class="text-xs text-dash-muted">{{ metric.label }}</dt>
                    <dd class="mt-1 flex flex-wrap items-baseline gap-x-2">
                        <span class="text-lg font-semibold">{{
                            metric.value
                        }}</span>
                        <span
                            v-if="metric.variation !== null"
                            class="text-xs font-medium"
                            :class="
                                variationClass(
                                    metric.variation,
                                    metric.higherIsBetter,
                                )
                            "
                            >{{ variationText(metric.variation) }}</span
                        >
                    </dd>
                </div>
            </dl>

            <div
                class="grid gap-6 xl:grid-cols-[minmax(0,1.75fr)_minmax(0,1fr)]"
            >
                <section
                    class="dash-panel p-5 sm:p-6"
                    aria-labelledby="trend-title"
                >
                    <h3 id="trend-title" class="font-semibold">
                        Evolução mensal
                    </h3>
                    <p class="mt-1 mb-5 text-sm text-dash-muted">
                        Últimos 6 meses até o fim do período escolhido.
                    </p>
                    <TrendChart
                        :labels="
                            dashboard.monthlyTrend.map((month) => month.label)
                        "
                        :series="trendSeries"
                        caption="Receita e despesas por mês"
                    />
                    <div class="mt-5 overflow-x-auto">
                        <table class="w-full min-w-[480px] text-sm">
                            <thead class="text-left text-xs text-dash-muted">
                                <tr>
                                    <th class="py-1.5 font-medium">Mês</th>
                                    <th class="py-1.5 text-right font-medium">
                                        Vendas
                                    </th>
                                    <th class="py-1.5 text-right font-medium">
                                        Receita
                                    </th>
                                    <th class="py-1.5 text-right font-medium">
                                        Despesas
                                    </th>
                                    <th class="py-1.5 text-right font-medium">
                                        Resultado
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-dash-line">
                                <tr
                                    v-for="month in dashboard.monthlyTrend"
                                    :key="month.month"
                                >
                                    <td class="py-2 capitalize">
                                        {{ month.label }}
                                    </td>
                                    <td class="py-2 text-right">
                                        {{ month.salesCount }}
                                    </td>
                                    <td class="py-2 text-right">
                                        {{ formatCurrency(month.revenue) }}
                                    </td>
                                    <td class="py-2 text-right">
                                        {{ formatCurrency(month.expenses) }}
                                    </td>
                                    <td
                                        class="py-2 text-right font-medium"
                                        :class="{
                                            'text-risk-critical':
                                                Number(month.result) < 0,
                                        }"
                                    >
                                        {{ formatCurrency(month.result) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section
                    class="dash-panel flex flex-col p-5 sm:p-6"
                    aria-labelledby="risk-title"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 id="risk-title" class="font-semibold">
                                Indicador de risco
                            </h3>
                            <p class="mt-1 text-sm text-dash-muted">
                                Despesas x receita, queda de vendas e quebras de
                                caixa.
                            </p>
                        </div>
                        <RiskBadge
                            :level="dashboard.risk.level"
                            :label="dashboard.risk.label"
                        />
                    </div>
                    <ul class="mt-5 grid gap-2 text-sm">
                        <li
                            v-for="reason in dashboard.risk.reasons"
                            :key="reason"
                            class="rounded-xl border border-dash-line bg-white/[0.02] px-3 py-2.5"
                        >
                            {{ reason }}
                        </li>
                    </ul>
                    <h4 class="mt-6 text-sm font-medium">
                        Onde a empresa está acertando e errando
                    </h4>
                    <ul
                        v-if="dashboard.insights.length"
                        class="mt-3 grid gap-2.5"
                    >
                        <li
                            v-for="insight in dashboard.insights"
                            :key="insight.title"
                            class="rounded-xl border border-dash-line bg-white/[0.02] px-3 py-3"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <RiskBadge
                                    :level="insightLevels[insight.tone].level"
                                    :label="insightLevels[insight.tone].label"
                                />
                                <p class="text-sm font-medium">
                                    {{ insight.title }}
                                </p>
                            </div>
                            <p class="mt-1.5 text-sm text-dash-muted">
                                {{ insight.detail }}
                            </p>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-dash-muted">
                        Nenhum ponto de atenção ou destaque identificado neste
                        período.
                    </p>
                </section>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <section
                    class="dash-panel p-5 sm:p-6"
                    aria-labelledby="sales-title"
                >
                    <h3 id="sales-title" class="font-semibold">Vendas</h3>
                    <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <dt class="text-dash-muted">Valor bruto</dt>
                        <dd class="text-right font-medium">
                            {{ formatCurrency(dashboard.sales.grossTotal) }}
                        </dd>
                        <dt class="text-dash-muted">Descontos concedidos</dt>
                        <dd class="text-right font-medium">
                            {{ formatCurrency(dashboard.sales.discountTotal) }}
                            <span class="text-dash-muted"
                                >({{
                                    formatPercent(dashboard.sales.discountRate)
                                }})</span
                            >
                        </dd>
                        <dt class="text-dash-muted">Receita líquida</dt>
                        <dd class="text-right font-semibold">
                            {{ formatCurrency(dashboard.sales.netTotal) }}
                        </dd>
                        <dt class="text-dash-muted">Vendas estornadas</dt>
                        <dd class="text-right font-medium">
                            {{ dashboard.sales.refundedCount }} ·
                            {{ formatCurrency(dashboard.sales.refundedTotal) }}
                            <span class="text-dash-muted"
                                >({{
                                    formatPercent(dashboard.sales.refundRate)
                                }})</span
                            >
                        </dd>
                    </dl>
                    <div class="mt-6">
                        <p class="text-sm font-medium">Meio de pagamento</p>
                        <div v-if="paymentSplit.hasData" class="mt-3">
                            <div
                                class="flex h-2.5 gap-0.5 overflow-hidden rounded-full"
                                role="img"
                                :aria-label="`Pix ${paymentSplit.pixShare.toFixed(0)}%, dinheiro ${paymentSplit.cashShare.toFixed(0)}%`"
                            >
                                <div
                                    class="rounded-full bg-dash-sales"
                                    :style="{
                                        width: `${paymentSplit.pixShare}%`,
                                    }"
                                />
                                <div
                                    class="rounded-full bg-cyan-300"
                                    :style="{
                                        width: `${paymentSplit.cashShare}%`,
                                    }"
                                />
                            </div>
                            <div
                                class="mt-2 flex justify-between text-xs text-dash-muted"
                            >
                                <span class="flex items-center gap-1.5"
                                    ><span
                                        class="size-2 rounded-sm bg-dash-sales"
                                    />Pix
                                    {{
                                        formatCurrency(
                                            dashboard.sales.byPaymentMethod.pix,
                                        )
                                    }}</span
                                >
                                <span class="flex items-center gap-1.5"
                                    ><span
                                        class="size-2 rounded-sm bg-cyan-300"
                                    />Dinheiro
                                    {{
                                        formatCurrency(
                                            dashboard.sales.byPaymentMethod
                                                .dinheiro,
                                        )
                                    }}</span
                                >
                            </div>
                        </div>
                        <p v-else class="mt-2 text-sm text-dash-muted">
                            Sem pagamentos confirmados no período.
                        </p>
                    </div>
                    <div class="mt-6">
                        <p class="text-sm font-medium">
                            Desempenho por operador
                        </p>
                        <table
                            v-if="dashboard.sales.byOperator.length"
                            class="mt-2 w-full text-sm"
                        >
                            <thead class="text-left text-xs text-dash-muted">
                                <tr>
                                    <th class="py-1.5 font-medium">Operador</th>
                                    <th class="py-1.5 text-right font-medium">
                                        Vendas
                                    </th>
                                    <th class="py-1.5 text-right font-medium">
                                        Total
                                    </th>
                                    <th class="py-1.5 text-right font-medium">
                                        Ticket médio
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-dash-line">
                                <tr
                                    v-for="row in dashboard.sales.byOperator"
                                    :key="row.operator"
                                >
                                    <td class="py-2">{{ row.operator }}</td>
                                    <td class="py-2 text-right">
                                        {{ row.salesCount }}
                                    </td>
                                    <td class="py-2 text-right">
                                        {{ formatCurrency(row.total) }}
                                    </td>
                                    <td class="py-2 text-right">
                                        {{ formatCurrency(row.averageTicket) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-else class="mt-2 text-sm text-dash-muted">
                            Sem vendas no período.
                        </p>
                    </div>
                </section>

                <section
                    class="dash-panel p-5 sm:p-6"
                    aria-labelledby="result-title"
                >
                    <h3 id="result-title" class="font-semibold">
                        Margem e resultado
                    </h3>
                    <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <dt class="text-dash-muted">
                            Receita de itens com custo
                        </dt>
                        <dd class="text-right font-medium">
                            {{
                                formatCurrency(dashboard.margin.revenueWithCost)
                            }}
                        </dd>
                        <dt class="text-dash-muted">
                            Custo dos produtos vendidos
                        </dt>
                        <dd class="text-right font-medium">
                            {{
                                formatCurrency(dashboard.margin.costOfGoodsSold)
                            }}
                        </dd>
                        <dt class="text-dash-muted">Lucro bruto</dt>
                        <dd class="text-right font-medium">
                            {{ formatCurrency(dashboard.margin.grossProfit) }}
                        </dd>
                        <dt class="text-dash-muted">Margem bruta</dt>
                        <dd class="text-right font-semibold">
                            {{ formatPercent(dashboard.margin.grossMargin) }}
                        </dd>
                        <dt class="text-dash-muted">Receita líquida</dt>
                        <dd class="text-right font-medium">
                            {{ formatCurrency(dashboard.result.revenue) }}
                        </dd>
                        <dt class="text-dash-muted">Despesas lançadas</dt>
                        <dd class="text-right font-medium">
                            − {{ formatCurrency(dashboard.result.expenses) }}
                        </dd>
                        <dt class="text-dash-muted">
                            Resultado após custo e despesas
                        </dt>
                        <dd
                            class="text-right font-semibold"
                            :class="{
                                'text-risk-critical':
                                    Number(dashboard.result.operatingResult) <
                                    0,
                            }"
                        >
                            {{
                                dashboard.result.operatingResult === null
                                    ? '—'
                                    : formatCurrency(
                                          dashboard.result.operatingResult,
                                      )
                            }}
                        </dd>
                    </dl>
                    <p class="mt-4 text-xs text-dash-muted">
                        A margem usa o valor dos itens antes do desconto da
                        venda e só considera itens com custo informado no
                        momento da venda.
                        <template
                            v-if="dashboard.result.operatingResult === null"
                            >O resultado final só é exibido quando todos os
                            itens vendidos têm custo informado.</template
                        >
                    </p>
                </section>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <section
                    class="dash-panel p-5 sm:p-6"
                    aria-labelledby="products-title"
                >
                    <h3 id="products-title" class="font-semibold">Produtos</h3>
                    <p class="mt-1 text-sm text-dash-muted">
                        {{ dashboard.products.activeCount }} ativo(s) ·
                        {{ dashboard.products.inactiveCount }} inativo(s) ·
                        {{ dashboard.products.activeWithoutCostCount }} ativo(s)
                        sem custo ·
                        {{ dashboard.products.unitsSold }} unidade(s) vendida(s)
                    </p>
                    <template v-if="dashboard.products.bestSellers.length">
                        <p class="mt-5 text-sm font-medium">Mais vendidos</p>
                        <ol class="mt-2 divide-y divide-dash-line text-sm">
                            <li
                                v-for="product in dashboard.products
                                    .bestSellers"
                                :key="`best-${product.productName}`"
                                class="flex justify-between gap-3 py-2"
                            >
                                <span>{{ product.productName }}</span>
                                <span class="text-dash-muted"
                                    >{{ product.quantity }} un. ·
                                    {{ formatCurrency(product.revenue) }} ·
                                    margem
                                    {{
                                        formatPercent(product.grossMargin)
                                    }}</span
                                >
                            </li>
                        </ol>
                    </template>
                    <p v-else class="mt-4 text-sm text-dash-muted">
                        Nenhum produto vendido no período.
                    </p>
                    <template v-if="dashboard.products.worstSellers.length">
                        <p class="mt-5 text-sm font-medium">
                            Menos vendidos (entre os que venderam)
                        </p>
                        <ol class="mt-2 divide-y divide-dash-line text-sm">
                            <li
                                v-for="product in dashboard.products
                                    .worstSellers"
                                :key="`worst-${product.productName}`"
                                class="flex justify-between gap-3 py-2"
                            >
                                <span>{{ product.productName }}</span>
                                <span class="text-dash-muted"
                                    >{{ product.quantity }} un. ·
                                    {{ formatCurrency(product.revenue) }}</span
                                >
                            </li>
                        </ol>
                    </template>
                    <template v-if="dashboard.products.lowMargin.length">
                        <p class="mt-5 text-sm font-medium">
                            Margem abaixo de 30%
                        </p>
                        <ul class="mt-2 divide-y divide-dash-line text-sm">
                            <li
                                v-for="product in dashboard.products.lowMargin"
                                :key="`margin-${product.productName}`"
                                class="flex justify-between gap-3 py-2"
                            >
                                <span>{{ product.productName }}</span>
                                <span class="font-medium text-risk-attention">{{
                                    formatPercent(product.grossMargin)
                                }}</span>
                            </li>
                        </ul>
                    </template>
                    <template v-if="dashboard.products.activeWithoutSalesCount">
                        <p class="mt-5 text-sm font-medium">
                            Ativos sem venda no período ({{
                                dashboard.products.activeWithoutSalesCount
                            }})
                        </p>
                        <p class="mt-1 text-sm text-dash-muted">
                            {{ dashboard.products.activeWithoutSales.join(', ')
                            }}<template
                                v-if="
                                    dashboard.products.activeWithoutSalesCount >
                                    dashboard.products.activeWithoutSales.length
                                "
                                >…</template
                            >
                        </p>
                    </template>
                </section>

                <section
                    class="dash-panel p-5 sm:p-6"
                    aria-labelledby="expenses-title"
                >
                    <h3 id="expenses-title" class="font-semibold">Gastos</h3>
                    <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <dt class="text-dash-muted">
                            Total ({{ dashboard.expenses.count }} lançamento(s))
                        </dt>
                        <dd class="text-right font-semibold">
                            {{ formatCurrency(dashboard.expenses.total) }}
                        </dd>
                        <dt class="text-dash-muted">Fixas</dt>
                        <dd class="text-right font-medium">
                            {{ formatCurrency(dashboard.expenses.fixedTotal) }}
                            <span class="text-dash-muted"
                                >({{
                                    formatPercent(
                                        dashboard.expenses.fixedShare,
                                    )
                                }})</span
                            >
                        </dd>
                        <dt class="text-dash-muted">Variáveis</dt>
                        <dd class="text-right font-medium">
                            {{
                                formatCurrency(dashboard.expenses.variableTotal)
                            }}
                        </dd>
                    </dl>
                    <template v-if="dashboard.expenses.topExpenses.length">
                        <p class="mt-5 text-sm font-medium">Maiores gastos</p>
                        <ol class="mt-2 grid gap-3 text-sm">
                            <li
                                v-for="expense in dashboard.expenses
                                    .topExpenses"
                                :key="expense.description"
                            >
                                <div class="flex justify-between gap-3">
                                    <span
                                        >{{ expense.description }}
                                        <span class="text-dash-muted"
                                            >· {{ expense.entries }}x</span
                                        ></span
                                    >
                                    <span class="text-dash-muted"
                                        >{{ formatCurrency(expense.total) }} ·
                                        {{ formatPercent(expense.share) }}</span
                                    >
                                </div>
                                <div
                                    class="mt-1.5 h-1 overflow-hidden rounded-full bg-white/[0.06]"
                                    aria-hidden="true"
                                >
                                    <div
                                        class="h-full rounded-full bg-dash-expenses"
                                        :style="{
                                            width: `${expense.share ?? 0}%`,
                                        }"
                                    />
                                </div>
                            </li>
                        </ol>
                    </template>
                    <p v-else class="mt-4 text-sm text-dash-muted">
                        Nenhuma despesa lançada no período.
                    </p>
                </section>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <section
                    class="dash-panel p-5 sm:p-6"
                    aria-labelledby="cash-flow-title"
                >
                    <h3 id="cash-flow-title" class="font-semibold">
                        Fluxo de caixa
                    </h3>
                    <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <dt class="text-dash-muted">
                            Entradas (Pix + dinheiro)
                        </dt>
                        <dd class="text-right font-medium">
                            {{ formatCurrency(dashboard.cashFlow.inflow) }}
                        </dd>
                        <dt class="text-dash-muted">Estornos devolvidos</dt>
                        <dd class="text-right font-medium">
                            −
                            {{
                                formatCurrency(dashboard.cashFlow.refundOutflow)
                            }}
                        </dd>
                        <dt class="text-dash-muted">Entrada líquida</dt>
                        <dd class="text-right font-semibold">
                            {{ formatCurrency(dashboard.cashFlow.netInflow) }}
                        </dd>
                        <dt class="text-dash-muted">
                            Retiradas de dinheiro do caixa
                        </dt>
                        <dd class="text-right font-medium">
                            {{ formatCurrency(dashboard.cashFlow.withdrawals) }}
                        </dd>
                        <dt class="text-dash-muted">Turnos no período</dt>
                        <dd class="text-right font-medium">
                            {{ dashboard.cashFlow.sessionCount }} ·
                            {{ dashboard.cashFlow.closedSessionCount }}
                            fechado(s)
                        </dd>
                        <dt class="text-dash-muted">
                            Diferença nos fechamentos
                        </dt>
                        <dd
                            class="text-right font-medium"
                            :class="{
                                'text-risk-critical':
                                    Number(dashboard.cashFlow.differenceTotal) <
                                    0,
                            }"
                        >
                            {{
                                formatCurrency(
                                    dashboard.cashFlow.differenceTotal,
                                )
                            }}
                        </dd>
                        <dt class="text-dash-muted">
                            Fechamentos com falta / sobra
                        </dt>
                        <dd class="text-right font-medium">
                            {{ dashboard.cashFlow.shortageCount }} /
                            {{ dashboard.cashFlow.surplusCount }}
                        </dd>
                        <dt class="text-dash-muted">Caixas abertos agora</dt>
                        <dd class="text-right font-medium">
                            {{ dashboard.cashFlow.openSessionCount }}
                        </dd>
                    </dl>
                    <p class="mt-4 text-xs text-dash-muted">
                        Retiradas movimentam dinheiro do caixa (ex.: sangria) e
                        não são despesas. Despesas não saem automaticamente do
                        caixa na V1.
                    </p>
                </section>

                <section
                    class="dash-panel border-dashed p-5 sm:p-6"
                    aria-labelledby="unavailable-title"
                >
                    <h3 id="unavailable-title" class="font-semibold">
                        Indicadores ainda indisponíveis
                    </h3>
                    <dl class="mt-4 grid gap-4 text-sm">
                        <div>
                            <dt class="font-medium">
                                Estoque
                                <span
                                    class="ml-1 rounded-full bg-white/5 px-2 py-0.5 text-xs text-dash-muted ring-1 ring-white/10 ring-inset"
                                    >Em breve</span
                                >
                            </dt>
                            <dd class="mt-1 text-dash-muted">
                                {{ dashboard.unavailable.stock }}
                            </dd>
                        </div>
                        <div>
                            <dt class="font-medium">
                                Inadimplência
                                <span
                                    class="ml-1 rounded-full bg-white/5 px-2 py-0.5 text-xs text-dash-muted ring-1 ring-white/10 ring-inset"
                                    >Não se aplica</span
                                >
                            </dt>
                            <dd class="mt-1 text-dash-muted">
                                {{ dashboard.unavailable.delinquency }}
                            </dd>
                        </div>
                    </dl>
                </section>
            </div>
        </div>
    </section>
</template>
