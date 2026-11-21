<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
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

function formatCurrency(value: string | number | null): string {
    if (value === null) {
        return '—';
    }

    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(Number(value));
}

function formatPercent(value: number | null): string {
    return value === null
        ? '—'
        : `${value.toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`;
}

function formatDate(value: string): string {
    const [year, month, day] = value.split('-');

    return `${day}/${month}/${year}`;
}

function variationLabel(value: number | null): string {
    if (value === null) {
        return 'Sem base no período anterior';
    }

    const sign = value > 0 ? '+' : '';

    return `${sign}${value.toLocaleString('pt-BR', { maximumFractionDigits: 1 })}% vs. período anterior`;
}

function variationClass(value: number | null, higherIsBetter = true): string {
    if (value === null || value === 0) {
        return 'text-slate-500';
    }

    return value > 0 === higherIsBetter
        ? 'text-emerald-700 dark:text-emerald-400'
        : 'text-red-700 dark:text-red-400';
}

const headlineMetrics = computed(() => [
    {
        label: 'Receita líquida',
        value: formatCurrency(props.dashboard.sales.netTotal),
        detail: variationLabel(props.dashboard.comparison.netTotal),
        detailClass: variationClass(props.dashboard.comparison.netTotal),
    },
    {
        label: 'Vendas finalizadas',
        value: props.dashboard.sales.count.toLocaleString('pt-BR'),
        detail: variationLabel(props.dashboard.comparison.salesCount),
        detailClass: variationClass(props.dashboard.comparison.salesCount),
    },
    {
        label: 'Ticket médio',
        value: props.dashboard.sales.count
            ? formatCurrency(props.dashboard.sales.averageTicket)
            : '—',
        detail: variationLabel(props.dashboard.comparison.averageTicket),
        detailClass: variationClass(props.dashboard.comparison.averageTicket),
    },
    {
        label: 'Despesas',
        value: formatCurrency(props.dashboard.expenses.total),
        detail: variationLabel(props.dashboard.comparison.expenseTotal),
        detailClass: variationClass(
            props.dashboard.comparison.expenseTotal,
            false,
        ),
    },
    {
        label: 'Margem bruta',
        value: formatPercent(props.dashboard.margin.grossMargin),
        detail:
            props.dashboard.margin.costCoverage === null
                ? 'Sem itens vendidos'
                : `Custo informado em ${formatPercent(props.dashboard.margin.costCoverage)} da receita de itens`,
        detailClass: 'text-slate-500',
    },
    {
        label: 'Receita − despesas',
        value: formatCurrency(props.dashboard.result.revenueMinusExpenses),
        detail: `Despesas = ${formatPercent(props.dashboard.result.expenseToRevenueRate)} da receita`,
        detailClass:
            Number(props.dashboard.result.revenueMinusExpenses) < 0
                ? 'text-red-700 dark:text-red-400'
                : 'text-slate-500',
    },
]);

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

const trendScale = computed(() =>
    Math.max(
        1,
        ...props.dashboard.monthlyTrend.flatMap((month) => [
            Number(month.revenue),
            Number(month.expenses),
        ]),
    ),
);

const toneClasses: Record<Tone, string> = {
    positive:
        'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100',
    warning:
        'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100',
    neutral:
        'border-slate-200 bg-slate-50 text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200',
};
const toneLabels: Record<Tone, string> = {
    positive: 'Acertando',
    warning: 'Atenção',
    neutral: 'Observação',
};
</script>

<template>
    <section
        class="mb-7 grid gap-5"
        aria-labelledby="company-dashboard-title"
        :aria-busy="loading"
    >
        <header
            class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end"
        >
            <div>
                <h2 id="company-dashboard-title" class="text-xl font-semibold">
                    Resumo para o contador
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ formatDate(dashboard.filters.startDate) }} a
                    {{ formatDate(dashboard.filters.endDate) }} · comparado com
                    {{ formatDate(dashboard.comparison.previousStartDate) }} a
                    {{ formatDate(dashboard.comparison.previousEndDate) }}
                </p>
            </div>
            <form
                class="flex flex-wrap items-end gap-2"
                aria-label="Período do resumo"
                @submit.prevent="applyFilters"
            >
                <button
                    v-for="period in [
                        ...quickPeriods,
                        { value: 'custom', label: 'Personalizado' },
                    ]"
                    :key="period.value"
                    type="button"
                    class="min-h-9 rounded-lg border px-3 text-sm font-medium transition-colors dark:border-slate-700"
                    :class="
                        filters.period === period.value
                            ? 'border-blue-700 bg-blue-700 text-white dark:border-blue-600 dark:bg-blue-600'
                            : 'bg-white text-slate-600 hover:border-blue-700 dark:bg-slate-900 dark:text-slate-300'
                    "
                    :aria-pressed="filters.period === period.value"
                    @click="selectPeriod(period.value)"
                >
                    {{ period.label }}
                </button>
                <template v-if="filters.period === 'custom'">
                    <label class="grid gap-1 text-xs text-slate-500"
                        >De
                        <input
                            v-model="filters.start_date"
                            type="date"
                            required
                            class="h-9 rounded-md border bg-white px-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                        />
                        <span v-if="errors.start_date" class="text-red-600">{{
                            errors.start_date
                        }}</span>
                    </label>
                    <label class="grid gap-1 text-xs text-slate-500"
                        >Até
                        <input
                            v-model="filters.end_date"
                            type="date"
                            required
                            class="h-9 rounded-md border bg-white px-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                        />
                        <span v-if="errors.end_date" class="text-red-600">{{
                            errors.end_date
                        }}</span>
                    </label>
                    <button
                        type="submit"
                        :disabled="loading"
                        class="min-h-9 rounded-md bg-blue-700 px-4 text-sm font-semibold text-white disabled:opacity-60"
                    >
                        {{ loading ? 'Aplicando…' : 'Aplicar' }}
                    </button>
                </template>
            </form>
        </header>

        <div
            class="grid gap-3 transition-opacity sm:grid-cols-2 xl:grid-cols-3"
            :class="{ 'opacity-60': loading }"
        >
            <article
                v-for="metric in headlineMetrics"
                :key="metric.label"
                class="rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
            >
                <p class="text-sm font-medium text-slate-500">
                    {{ metric.label }}
                </p>
                <p class="mt-2 text-2xl font-semibold tracking-tight">
                    {{ metric.value }}
                </p>
                <p class="mt-2 text-xs" :class="metric.detailClass">
                    {{ metric.detail }}
                </p>
            </article>
        </div>

        <section
            class="rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
            aria-labelledby="insights-title"
        >
            <h3 id="insights-title" class="font-semibold">
                Onde a empresa está acertando e errando
            </h3>
            <p class="mt-1 text-sm text-slate-500">
                Sinais calculados a partir dos dados registrados no período.
            </p>
            <ul
                v-if="dashboard.insights.length"
                class="mt-4 grid gap-3 md:grid-cols-2"
            >
                <li
                    v-for="insight in dashboard.insights"
                    :key="insight.title"
                    class="rounded-lg border px-4 py-3"
                    :class="toneClasses[insight.tone]"
                >
                    <p
                        class="text-xs font-semibold tracking-wide uppercase opacity-75"
                    >
                        {{ toneLabels[insight.tone] }}
                    </p>
                    <p class="mt-1 font-medium">{{ insight.title }}</p>
                    <p class="mt-1 text-sm">{{ insight.detail }}</p>
                </li>
            </ul>
            <p v-else class="mt-4 text-sm text-slate-500">
                Nenhum ponto de atenção ou destaque identificado neste período.
            </p>
        </section>

        <div class="grid gap-5 lg:grid-cols-2">
            <section
                class="rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
                aria-labelledby="sales-title"
            >
                <h3 id="sales-title" class="font-semibold">Vendas</h3>
                <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <dt class="text-slate-500">Valor bruto</dt>
                    <dd class="text-right font-medium">
                        {{ formatCurrency(dashboard.sales.grossTotal) }}
                    </dd>
                    <dt class="text-slate-500">Descontos concedidos</dt>
                    <dd class="text-right font-medium">
                        {{ formatCurrency(dashboard.sales.discountTotal) }}
                        <span class="text-slate-500"
                            >({{
                                formatPercent(dashboard.sales.discountRate)
                            }})</span
                        >
                    </dd>
                    <dt class="text-slate-500">Receita líquida</dt>
                    <dd class="text-right font-semibold">
                        {{ formatCurrency(dashboard.sales.netTotal) }}
                    </dd>
                    <dt class="text-slate-500">Vendas estornadas</dt>
                    <dd class="text-right font-medium">
                        {{ dashboard.sales.refundedCount }} ·
                        {{ formatCurrency(dashboard.sales.refundedTotal) }}
                        <span class="text-slate-500"
                            >({{
                                formatPercent(dashboard.sales.refundRate)
                            }})</span
                        >
                    </dd>
                </dl>
                <div class="mt-5">
                    <p class="text-sm font-medium">Meio de pagamento</p>
                    <div v-if="paymentSplit.hasData" class="mt-2">
                        <div
                            class="flex h-3 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                            role="img"
                            :aria-label="`Pix ${paymentSplit.pixShare.toFixed(0)}%, dinheiro ${paymentSplit.cashShare.toFixed(0)}%`"
                        >
                            <div
                                class="bg-blue-600"
                                :style="{ width: `${paymentSplit.pixShare}%` }"
                            />
                            <div
                                class="bg-sky-300 dark:bg-sky-700"
                                :style="{ width: `${paymentSplit.cashShare}%` }"
                            />
                        </div>
                        <div
                            class="mt-2 flex justify-between text-xs text-slate-500"
                        >
                            <span
                                ><span
                                    class="mr-1 inline-block size-2 rounded-full bg-blue-600"
                                />Pix
                                {{
                                    formatCurrency(
                                        dashboard.sales.byPaymentMethod.pix,
                                    )
                                }}</span
                            >
                            <span
                                ><span
                                    class="mr-1 inline-block size-2 rounded-full bg-sky-300 dark:bg-sky-700"
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
                    <p v-else class="mt-2 text-sm text-slate-500">
                        Sem pagamentos confirmados no período.
                    </p>
                </div>
                <div class="mt-5">
                    <p class="text-sm font-medium">Desempenho por operador</p>
                    <table
                        v-if="dashboard.sales.byOperator.length"
                        class="mt-2 w-full text-sm"
                    >
                        <thead class="text-left text-xs text-slate-500">
                            <tr>
                                <th class="py-1 font-medium">Operador</th>
                                <th class="py-1 text-right font-medium">
                                    Vendas
                                </th>
                                <th class="py-1 text-right font-medium">
                                    Total
                                </th>
                                <th class="py-1 text-right font-medium">
                                    Ticket médio
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y dark:divide-slate-800">
                            <tr
                                v-for="row in dashboard.sales.byOperator"
                                :key="row.operator"
                            >
                                <td class="py-1.5">{{ row.operator }}</td>
                                <td class="py-1.5 text-right">
                                    {{ row.salesCount }}
                                </td>
                                <td class="py-1.5 text-right">
                                    {{ formatCurrency(row.total) }}
                                </td>
                                <td class="py-1.5 text-right">
                                    {{ formatCurrency(row.averageTicket) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="mt-2 text-sm text-slate-500">
                        Sem vendas no período.
                    </p>
                </div>
            </section>

            <section
                class="rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
                aria-labelledby="result-title"
            >
                <h3 id="result-title" class="font-semibold">
                    Margem e resultado
                </h3>
                <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <dt class="text-slate-500">Receita de itens com custo</dt>
                    <dd class="text-right font-medium">
                        {{ formatCurrency(dashboard.margin.revenueWithCost) }}
                    </dd>
                    <dt class="text-slate-500">Custo dos produtos vendidos</dt>
                    <dd class="text-right font-medium">
                        {{ formatCurrency(dashboard.margin.costOfGoodsSold) }}
                    </dd>
                    <dt class="text-slate-500">Lucro bruto</dt>
                    <dd class="text-right font-medium">
                        {{ formatCurrency(dashboard.margin.grossProfit) }}
                    </dd>
                    <dt class="text-slate-500">Margem bruta</dt>
                    <dd class="text-right font-semibold">
                        {{ formatPercent(dashboard.margin.grossMargin) }}
                    </dd>
                    <dt class="text-slate-500">Receita líquida</dt>
                    <dd class="text-right font-medium">
                        {{ formatCurrency(dashboard.result.revenue) }}
                    </dd>
                    <dt class="text-slate-500">Despesas lançadas</dt>
                    <dd class="text-right font-medium">
                        − {{ formatCurrency(dashboard.result.expenses) }}
                    </dd>
                    <dt class="text-slate-500">
                        Resultado após custo e despesas
                    </dt>
                    <dd class="text-right font-semibold">
                        {{
                            dashboard.result.operatingResult === null
                                ? '—'
                                : formatCurrency(
                                      dashboard.result.operatingResult,
                                  )
                        }}
                    </dd>
                </dl>
                <p class="mt-4 text-xs text-slate-500">
                    A margem usa o valor dos itens antes do desconto da venda e
                    só considera itens com custo informado no momento da venda.
                    <template v-if="dashboard.result.operatingResult === null"
                        >O resultado final só é exibido quando todos os itens
                        vendidos têm custo informado.</template
                    >
                </p>
            </section>
        </div>

        <section
            class="rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
            aria-labelledby="trend-title"
        >
            <h3 id="trend-title" class="font-semibold">Evolução mensal</h3>
            <p class="mt-1 text-sm text-slate-500">
                Últimos 6 meses até o fim do período escolhido.
            </p>
            <div class="mt-4 flex gap-4 text-xs text-slate-500">
                <span
                    ><span
                        class="mr-1 inline-block size-2 rounded-full bg-blue-600"
                    />Receita</span
                >
                <span
                    ><span
                        class="mr-1 inline-block size-2 rounded-full bg-amber-500"
                    />Despesas</span
                >
            </div>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[560px] text-sm">
                    <thead class="text-left text-xs text-slate-500">
                        <tr>
                            <th class="py-1 font-medium">Mês</th>
                            <th class="w-2/5 py-1 font-medium">
                                <span class="sr-only">Gráfico</span>
                            </th>
                            <th class="py-1 text-right font-medium">Vendas</th>
                            <th class="py-1 text-right font-medium">Receita</th>
                            <th class="py-1 text-right font-medium">
                                Despesas
                            </th>
                            <th class="py-1 text-right font-medium">
                                Resultado
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-slate-800">
                        <tr
                            v-for="month in dashboard.monthlyTrend"
                            :key="month.month"
                        >
                            <td class="py-2 capitalize">{{ month.label }}</td>
                            <td class="py-2 pr-4" aria-hidden="true">
                                <div class="grid gap-1">
                                    <div
                                        class="h-2 rounded-full bg-blue-600"
                                        :style="{
                                            width: `${(Number(month.revenue) / trendScale) * 100}%`,
                                        }"
                                    />
                                    <div
                                        class="h-2 rounded-full bg-amber-500"
                                        :style="{
                                            width: `${(Number(month.expenses) / trendScale) * 100}%`,
                                        }"
                                    />
                                </div>
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
                                :class="
                                    Number(month.result) < 0
                                        ? 'text-red-700 dark:text-red-400'
                                        : ''
                                "
                            >
                                {{ formatCurrency(month.result) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="grid gap-5 lg:grid-cols-2">
            <section
                class="rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
                aria-labelledby="products-title"
            >
                <h3 id="products-title" class="font-semibold">Produtos</h3>
                <p class="mt-1 text-sm text-slate-500">
                    {{ dashboard.products.activeCount }} ativo(s) ·
                    {{ dashboard.products.inactiveCount }} inativo(s) ·
                    {{ dashboard.products.activeWithoutCostCount }} ativo(s) sem
                    custo · {{ dashboard.products.unitsSold }} unidade(s)
                    vendida(s)
                </p>
                <template v-if="dashboard.products.bestSellers.length">
                    <p class="mt-4 text-sm font-medium">Mais vendidos</p>
                    <ol class="mt-2 divide-y text-sm dark:divide-slate-800">
                        <li
                            v-for="product in dashboard.products.bestSellers"
                            :key="`best-${product.productName}`"
                            class="flex justify-between gap-3 py-1.5"
                        >
                            <span>{{ product.productName }}</span>
                            <span class="text-slate-500"
                                >{{ product.quantity }} un. ·
                                {{ formatCurrency(product.revenue) }} · margem
                                {{ formatPercent(product.grossMargin) }}</span
                            >
                        </li>
                    </ol>
                </template>
                <p v-else class="mt-4 text-sm text-slate-500">
                    Nenhum produto vendido no período.
                </p>
                <template v-if="dashboard.products.worstSellers.length">
                    <p class="mt-4 text-sm font-medium">
                        Menos vendidos (entre os que venderam)
                    </p>
                    <ol class="mt-2 divide-y text-sm dark:divide-slate-800">
                        <li
                            v-for="product in dashboard.products.worstSellers"
                            :key="`worst-${product.productName}`"
                            class="flex justify-between gap-3 py-1.5"
                        >
                            <span>{{ product.productName }}</span>
                            <span class="text-slate-500"
                                >{{ product.quantity }} un. ·
                                {{ formatCurrency(product.revenue) }}</span
                            >
                        </li>
                    </ol>
                </template>
                <template v-if="dashboard.products.lowMargin.length">
                    <p class="mt-4 text-sm font-medium">Margem abaixo de 30%</p>
                    <ul class="mt-2 divide-y text-sm dark:divide-slate-800">
                        <li
                            v-for="product in dashboard.products.lowMargin"
                            :key="`margin-${product.productName}`"
                            class="flex justify-between gap-3 py-1.5"
                        >
                            <span>{{ product.productName }}</span>
                            <span class="text-amber-700 dark:text-amber-400">{{
                                formatPercent(product.grossMargin)
                            }}</span>
                        </li>
                    </ul>
                </template>
                <template v-if="dashboard.products.activeWithoutSalesCount">
                    <p class="mt-4 text-sm font-medium">
                        Ativos sem venda no período ({{
                            dashboard.products.activeWithoutSalesCount
                        }})
                    </p>
                    <p class="mt-1 text-sm text-slate-500">
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
                class="rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
                aria-labelledby="expenses-title"
            >
                <h3 id="expenses-title" class="font-semibold">Gastos</h3>
                <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <dt class="text-slate-500">
                        Total ({{ dashboard.expenses.count }} lançamento(s))
                    </dt>
                    <dd class="text-right font-semibold">
                        {{ formatCurrency(dashboard.expenses.total) }}
                    </dd>
                    <dt class="text-slate-500">Fixas</dt>
                    <dd class="text-right font-medium">
                        {{ formatCurrency(dashboard.expenses.fixedTotal) }}
                        <span class="text-slate-500"
                            >({{
                                formatPercent(dashboard.expenses.fixedShare)
                            }})</span
                        >
                    </dd>
                    <dt class="text-slate-500">Variáveis</dt>
                    <dd class="text-right font-medium">
                        {{ formatCurrency(dashboard.expenses.variableTotal) }}
                    </dd>
                </dl>
                <template v-if="dashboard.expenses.topExpenses.length">
                    <p class="mt-5 text-sm font-medium">Maiores gastos</p>
                    <ol class="mt-2 divide-y text-sm dark:divide-slate-800">
                        <li
                            v-for="expense in dashboard.expenses.topExpenses"
                            :key="expense.description"
                            class="flex justify-between gap-3 py-1.5"
                        >
                            <span
                                >{{ expense.description }}
                                <span class="text-slate-500"
                                    >· {{ expense.entries }}x</span
                                ></span
                            >
                            <span class="text-slate-500"
                                >{{ formatCurrency(expense.total) }} ·
                                {{ formatPercent(expense.share) }}</span
                            >
                        </li>
                    </ol>
                </template>
                <p v-else class="mt-4 text-sm text-slate-500">
                    Nenhuma despesa lançada no período.
                </p>
            </section>
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <section
                class="rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
                aria-labelledby="cash-flow-title"
            >
                <h3 id="cash-flow-title" class="font-semibold">
                    Fluxo de caixa
                </h3>
                <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <dt class="text-slate-500">Entradas (Pix + dinheiro)</dt>
                    <dd class="text-right font-medium">
                        {{ formatCurrency(dashboard.cashFlow.inflow) }}
                    </dd>
                    <dt class="text-slate-500">Estornos devolvidos</dt>
                    <dd class="text-right font-medium">
                        − {{ formatCurrency(dashboard.cashFlow.refundOutflow) }}
                    </dd>
                    <dt class="text-slate-500">Entrada líquida</dt>
                    <dd class="text-right font-semibold">
                        {{ formatCurrency(dashboard.cashFlow.netInflow) }}
                    </dd>
                    <dt class="text-slate-500">
                        Retiradas de dinheiro do caixa
                    </dt>
                    <dd class="text-right font-medium">
                        {{ formatCurrency(dashboard.cashFlow.withdrawals) }}
                    </dd>
                    <dt class="text-slate-500">Turnos no período</dt>
                    <dd class="text-right font-medium">
                        {{ dashboard.cashFlow.sessionCount }} ·
                        {{ dashboard.cashFlow.closedSessionCount }} fechado(s)
                    </dd>
                    <dt class="text-slate-500">Diferença nos fechamentos</dt>
                    <dd
                        class="text-right font-medium"
                        :class="
                            Number(dashboard.cashFlow.differenceTotal) < 0
                                ? 'text-red-700 dark:text-red-400'
                                : ''
                        "
                    >
                        {{ formatCurrency(dashboard.cashFlow.differenceTotal) }}
                    </dd>
                    <dt class="text-slate-500">
                        Fechamentos com falta / sobra
                    </dt>
                    <dd class="text-right font-medium">
                        {{ dashboard.cashFlow.shortageCount }} /
                        {{ dashboard.cashFlow.surplusCount }}
                    </dd>
                    <dt class="text-slate-500">Caixas abertos agora</dt>
                    <dd class="text-right font-medium">
                        {{ dashboard.cashFlow.openSessionCount }}
                    </dd>
                </dl>
                <p class="mt-4 text-xs text-slate-500">
                    Retiradas movimentam dinheiro do caixa (ex.: sangria) e não
                    são despesas. Despesas não saem automaticamente do caixa na
                    V1.
                </p>
            </section>

            <section
                class="rounded-xl border border-dashed bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
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
                                class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                >Em breve</span
                            >
                        </dt>
                        <dd class="mt-1 text-slate-500">
                            {{ dashboard.unavailable.stock }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium">
                            Inadimplência
                            <span
                                class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                >Não se aplica</span
                            >
                        </dt>
                        <dd class="mt-1 text-slate-500">
                            {{ dashboard.unavailable.delinquency }}
                        </dd>
                    </div>
                </dl>
            </section>
        </div>
    </section>
</template>
