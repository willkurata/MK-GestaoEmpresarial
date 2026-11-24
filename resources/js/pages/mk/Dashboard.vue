<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRight,
    Building2,
    HandCoins,
    Package,
    Receipt,
    ShoppingCart,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import MetricCard from '@/components/dashboard/MetricCard.vue';
import PeriodTabs from '@/components/dashboard/PeriodTabs.vue';
import RiskBadge from '@/components/dashboard/RiskBadge.vue';
import TrendChart from '@/components/dashboard/TrendChart.vue';
import OpenCashSessionAlert from '@/components/OpenCashSessionAlert.vue';
import type { RiskAssessment, RiskLevel } from '@/components/dashboard/types';
import { formatCurrency, formatDate, formatVariation } from '@/lib/format';
import { logout } from '@/routes';
import {
    create as createCompany,
    show as showCompany,
} from '@/routes/mk/companies';
import type { OpenCashSession } from '@/types';

defineOptions({
    layout: {
        title: 'Administração MK',
        description: 'Visão master das empresas cadastradas.',
    },
});

type CompanyCard = {
    id: number;
    cnpj: string;
    name: string;
    status: string;
    created_at: string;
    metrics: {
        revenue: string;
        previousRevenue: string;
        revenueVariation: number | null;
        salesCount: number;
        expenses: string;
        activeUsersCount: number;
        usersCount: number;
        openSessionsCount: number;
        lastSaleAt: string | null;
    };
    risk: RiskAssessment;
};

const props = defineProps<{
    period: {
        startDate: string;
        endDate: string;
        previousStartDate: string;
        previousEndDate: string;
    };
    totals: {
        companiesCount: number;
        activeCompaniesCount: number;
        revenue: string;
        revenueVariation: number | null;
        salesCount: number;
        expenses: string;
        activeUsersCount: number;
        openSessionsCount: number;
        riskCounts: Record<RiskLevel, number>;
    };
    trend: Array<{
        month: string;
        label: string;
        revenue: string;
        expenses: string;
    }>;
    companies: CompanyCard[];
    openCashSessions: OpenCashSession[];
}>();

const riskOrder: RiskLevel[] = ['critical', 'high', 'attention', 'ok', 'none'];
const riskLabels: Record<RiskLevel, string> = {
    critical: 'Crítico',
    high: 'Risco alto',
    attention: 'Atenção',
    ok: 'Saudável',
    none: 'Sem dados',
};

const sortMode = ref('risk');
const sortOptions = [
    { value: 'risk', label: 'Maior risco' },
    { value: 'revenue', label: 'Maior receita' },
    { value: 'name', label: 'Nome' },
];

const sortedCompanies = computed(() => {
    const list = [...props.companies];

    if (sortMode.value === 'risk') {
        return list.sort(
            (a, b) =>
                riskOrder.indexOf(a.risk.level) -
                    riskOrder.indexOf(b.risk.level) ||
                a.name.localeCompare(b.name, 'pt-BR'),
        );
    }

    if (sortMode.value === 'revenue') {
        return list.sort(
            (a, b) => Number(b.metrics.revenue) - Number(a.metrics.revenue),
        );
    }

    return list.sort((a, b) => a.name.localeCompare(b.name, 'pt-BR'));
});

const trendSeries = computed(() => [
    {
        key: 'revenue',
        label: 'Vendas',
        colorClass: 'text-dash-sales',
        values: props.trend.map((month) => Number(month.revenue)),
    },
    {
        key: 'expenses',
        label: 'Despesas',
        colorClass: 'text-dash-expenses',
        values: props.trend.map((month) => Number(month.expenses)),
    },
]);

const riskStripe: Record<RiskLevel, string> = {
    none: 'bg-risk-none/50',
    ok: 'bg-risk-ok',
    attention: 'bg-risk-attention',
    high: 'bg-risk-high',
    critical: 'bg-risk-critical',
};

function expenseRatio(company: CompanyCard): number | null {
    const revenue = Number(company.metrics.revenue);

    return revenue > 0
        ? Math.min(100, (Number(company.metrics.expenses) / revenue) * 100)
        : null;
}

function variationClass(value: number | null): string {
    if (value === null || value === 0) {
        return 'text-dash-muted';
    }

    return value > 0 ? 'text-risk-ok' : 'text-risk-critical';
}
</script>

<template>
    <Head title="Administração MK" />
    <main class="mk-dash dark min-h-screen px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 lg:gap-8">
            <header
                class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end"
            >
                <div>
                    <p
                        class="text-xs font-semibold tracking-[0.2em] text-dash-accent"
                    >
                        MK GESTÃO · MASTER
                    </p>
                    <h1
                        class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl"
                    >
                        Central das empresas
                    </h1>
                    <p class="mt-2 text-sm text-dash-muted">
                        Mês atual: {{ formatDate(period.startDate) }} a
                        {{ formatDate(period.endDate) }} · comparado com
                        {{ formatDate(period.previousStartDate) }} a
                        {{ formatDate(period.previousEndDate) }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link
                        :href="createCompany()"
                        class="inline-flex h-10 items-center rounded-xl bg-dash-accent-strong px-4 text-sm font-semibold text-white shadow-[0_10px_30px_-14px_var(--dash-accent)]"
                        >Cadastrar empresa</Link
                    >
                    <Form v-bind="logout.form()">
                        <button
                            class="h-10 rounded-xl border border-dash-line px-4 text-sm text-dash-muted hover:text-dash-text"
                        >
                            Sair
                        </button>
                    </Form>
                </div>
            </header>

            <OpenCashSessionAlert
                :sessions="openCashSessions"
                show-company
                :company-href="
                    (session) => showCompany({ company: session.company.id })
                "
                hint="Turnos de dias anteriores indicam caixa esquecido: confirme com a empresa antes do próximo expediente."
            />

            <section
                class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5"
                aria-label="Indicadores da plataforma"
            >
                <MetricCard
                    label="Vendas (todas as empresas)"
                    :value="formatCurrency(totals.revenue)"
                    :icon="ShoppingCart"
                    :variation="totals.revenueVariation"
                    :hint="`${totals.salesCount} venda(s)`"
                    accent
                />
                <MetricCard
                    label="Despesas lançadas"
                    :value="formatCurrency(totals.expenses)"
                    :icon="Receipt"
                    :hint="`${totals.openSessionsCount} caixa(s) aberto(s) agora`"
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
                    :value="String(totals.activeUsersCount)"
                    :icon="Users"
                    :hint="`${totals.activeCompaniesCount} de ${totals.companiesCount} empresa(s) ativa(s)`"
                />
            </section>

            <section
                class="grid gap-6 xl:grid-cols-[minmax(0,1.75fr)_minmax(0,1fr)]"
            >
                <article
                    class="dash-panel p-5 sm:p-6"
                    aria-labelledby="platform-trend-title"
                >
                    <h2 id="platform-trend-title" class="font-semibold">
                        Tendência da plataforma
                    </h2>
                    <p class="mt-1 mb-5 text-sm text-dash-muted">
                        Vendas finalizadas e despesas somadas, últimos 6 meses
                    </p>
                    <TrendChart
                        :labels="trend.map((month) => month.label)"
                        :series="trendSeries"
                        caption="Vendas e despesas de todas as empresas por mês"
                    />
                </article>

                <article
                    class="dash-panel p-5 sm:p-6"
                    aria-labelledby="risk-overview-title"
                >
                    <h2
                        id="risk-overview-title"
                        class="flex items-center gap-2 font-semibold"
                    >
                        <Activity
                            class="size-4 text-dash-accent"
                            aria-hidden="true"
                        />Saúde das empresas
                    </h2>
                    <p class="mt-1 text-sm text-dash-muted">
                        Empresas por nível de risco no mês
                    </p>
                    <div
                        v-if="totals.companiesCount"
                        class="mt-6 flex h-3 gap-0.5 overflow-hidden rounded-full"
                        role="img"
                        :aria-label="
                            riskOrder
                                .map(
                                    (level) =>
                                        `${riskLabels[level]}: ${totals.riskCounts[level] ?? 0}`,
                                )
                                .join(', ')
                        "
                    >
                        <div
                            v-for="level in riskOrder"
                            v-show="totals.riskCounts[level]"
                            :key="level"
                            class="rounded-full"
                            :class="riskStripe[level]"
                            :style="{ flexGrow: totals.riskCounts[level] ?? 0 }"
                        />
                    </div>
                    <ul class="mt-5 grid gap-2.5">
                        <li
                            v-for="level in riskOrder"
                            :key="level"
                            class="flex items-center justify-between gap-3"
                        >
                            <RiskBadge
                                :level="level"
                                :label="riskLabels[level]"
                            />
                            <span class="text-sm font-semibold">{{
                                totals.riskCounts[level] ?? 0
                            }}</span>
                        </li>
                    </ul>
                </article>
            </section>

            <section aria-labelledby="companies-title" class="grid gap-4">
                <header class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2
                            id="companies-title"
                            class="flex items-center gap-2 text-lg font-semibold"
                        >
                            <Building2
                                class="size-5 text-dash-accent"
                                aria-hidden="true"
                            />Empresas
                        </h2>
                        <p class="mt-1 text-sm text-dash-muted">
                            {{ companies.length }} empresa(s) cadastrada(s)
                        </p>
                    </div>
                    <PeriodTabs
                        :periods="sortOptions"
                        :model-value="sortMode"
                        label="Ordenar empresas"
                        @select="sortMode = $event"
                    />
                </header>

                <ul
                    v-if="companies.length"
                    class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                >
                    <li v-for="company in sortedCompanies" :key="company.id">
                        <Link
                            :href="showCompany({ company: company.id })"
                            class="dash-panel group relative flex h-full flex-col gap-5 overflow-hidden p-5 hover:border-dash-accent/40 focus-visible:outline-2 focus-visible:outline-dash-accent"
                        >
                            <span
                                class="absolute inset-y-0 left-0 w-1"
                                :class="riskStripe[company.risk.level]"
                                aria-hidden="true"
                            />
                            <header
                                class="flex items-start justify-between gap-3"
                            >
                                <div class="min-w-0">
                                    <p class="truncate font-semibold">
                                        {{ company.name }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-dash-muted">
                                        CNPJ {{ company.cnpj }}
                                    </p>
                                </div>
                                <RiskBadge
                                    :level="company.risk.level"
                                    :label="company.risk.label"
                                />
                            </header>

                            <div>
                                <p class="text-xs text-dash-muted">
                                    Vendas no mês
                                </p>
                                <p
                                    class="mt-1 flex flex-wrap items-baseline gap-x-2 text-2xl font-semibold tracking-tight"
                                >
                                    {{
                                        formatCurrency(company.metrics.revenue)
                                    }}
                                    <span
                                        class="text-xs font-medium"
                                        :class="
                                            variationClass(
                                                company.metrics
                                                    .revenueVariation,
                                            )
                                        "
                                        >{{
                                            formatVariation(
                                                company.metrics
                                                    .revenueVariation,
                                            )
                                        }}</span
                                    >
                                </p>
                            </div>

                            <div>
                                <div
                                    class="flex justify-between text-xs text-dash-muted"
                                >
                                    <span
                                        >Despesas ·
                                        {{
                                            formatCurrency(
                                                company.metrics.expenses,
                                            )
                                        }}</span
                                    >
                                    <span>{{
                                        expenseRatio(company) === null
                                            ? '—'
                                            : `${Math.round(expenseRatio(company) ?? 0)}% da receita`
                                    }}</span>
                                </div>
                                <div
                                    class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/[0.06]"
                                >
                                    <div
                                        class="h-full rounded-full"
                                        :class="riskStripe[company.risk.level]"
                                        :style="{
                                            width: `${expenseRatio(company) ?? 0}%`,
                                        }"
                                    />
                                </div>
                            </div>

                            <dl
                                class="grid grid-cols-3 gap-3 border-t border-dash-line pt-4 text-xs"
                            >
                                <div>
                                    <dt class="text-dash-muted">Vendas</dt>
                                    <dd class="mt-0.5 text-sm font-semibold">
                                        {{ company.metrics.salesCount }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-dash-muted">Usuários</dt>
                                    <dd class="mt-0.5 text-sm font-semibold">
                                        {{ company.metrics.activeUsersCount
                                        }}<span
                                            class="font-normal text-dash-muted"
                                            >/{{
                                                company.metrics.usersCount
                                            }}</span
                                        >
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-dash-muted">
                                        Caixas abertos
                                    </dt>
                                    <dd class="mt-0.5 text-sm font-semibold">
                                        {{ company.metrics.openSessionsCount }}
                                    </dd>
                                </div>
                            </dl>

                            <footer
                                class="mt-auto flex items-end justify-between gap-3 text-xs text-dash-muted"
                            >
                                <div class="min-w-0">
                                    <p class="line-clamp-2 text-dash-text/80">
                                        {{ company.risk.reasons[0] }}
                                    </p>
                                    <p class="mt-1">
                                        {{
                                            company.status === 'ativa'
                                                ? 'Ativa'
                                                : 'Inativa'
                                        }}
                                        · Última venda:
                                        {{
                                            company.metrics.lastSaleAt ??
                                            'nenhuma'
                                        }}
                                    </p>
                                </div>
                                <ArrowRight
                                    class="size-4 shrink-0 text-dash-accent transition-transform group-hover:translate-x-0.5"
                                    aria-hidden="true"
                                />
                            </footer>
                        </Link>
                    </li>
                </ul>
                <p
                    v-else
                    class="dash-panel px-5 py-12 text-center text-sm text-dash-muted"
                >
                    Nenhuma empresa cadastrada ainda.
                </p>
            </section>
        </div>
    </main>
</template>
