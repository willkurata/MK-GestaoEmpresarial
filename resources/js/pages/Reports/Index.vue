<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Printer } from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import PeriodFilter from '@/components/PeriodFilter.vue';
import { index as reportsIndex } from '@/routes/reports';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Relatórios', href: reportsIndex() }],
    },
});

const props = defineProps<{
    company: { name: string };
    filters: { period: string; startDate: string; endDate: string };
    summary: {
        salesCount: number;
        salesTotal: string;
        cashTotal: string;
        pixTotal: string;
        refundedCount: number;
        refundedTotal: string;
    };
    productsSold: Array<{
        productName: string;
        quantity: number;
        grossTotal: string;
    }>;
    expenseSummary: {
        expenseCount: number;
        total: string;
        fixedTotal: string;
        variableTotal: string;
    };
    recentExpenses: Array<{
        id: number;
        description: string;
        type: 'fixa' | 'variavel';
        date: string;
        amount: string;
    }>;
    cashSummary: {
        sessionCount: number;
        closedCount: number;
        openCount: number;
        expectedTotal: string;
        countedTotal: string;
        differenceTotal: string;
    };
    recentSales: Array<{
        id: number;
        occurredAt: string;
        operator: string;
        paymentMethod: string;
        total: string;
        status: 'finalizada' | 'estornada';
    }>;
    recentCashSessions: Array<{
        id: number;
        operator: string;
        openedAt: string;
        closedAt: string | null;
        openingAmount: string;
        expectedAmount: string | null;
        countedAmount: string | null;
        differenceAmount: string | null;
        status: 'aberto' | 'fechado';
    }>;
}>();

const metrics = computed(() => [
    {
        label: 'Vendas finalizadas',
        value: props.summary.salesCount.toString(),
        detail: formatCurrency(props.summary.salesTotal),
    },
    {
        label: 'Recebido em dinheiro',
        value: formatCurrency(props.summary.cashTotal),
        detail: 'Pagamentos confirmados',
    },
    {
        label: 'Recebido via Pix',
        value: formatCurrency(props.summary.pixTotal),
        detail: 'Pagamentos confirmados',
    },
    {
        label: 'Vendas estornadas',
        value: props.summary.refundedCount.toString(),
        detail: formatCurrency(props.summary.refundedTotal),
    },
]);

const cashMetrics = computed(() => [
    {
        label: 'Turnos iniciados',
        value: props.cashSummary.sessionCount.toString(),
        detail: `${props.cashSummary.closedCount} fechado(s) · ${props.cashSummary.openCount} aberto(s)`,
    },
    {
        label: 'Total esperado',
        value: formatCurrency(props.cashSummary.expectedTotal),
        detail: 'Turnos fechados no período',
    },
    {
        label: 'Total contado',
        value: formatCurrency(props.cashSummary.countedTotal),
        detail: 'Turnos fechados no período',
    },
    {
        label: 'Diferença total',
        value: formatCurrency(props.cashSummary.differenceTotal),
        detail: 'Contado menos esperado',
    },
]);

const expenseMetrics = computed(() => [
    {
        label: 'Total de despesas',
        value: formatCurrency(props.expenseSummary.total),
        detail: `${props.expenseSummary.expenseCount} lançamento(s)`,
    },
    {
        label: 'Despesas fixas',
        value: formatCurrency(props.expenseSummary.fixedTotal),
        detail: 'No período selecionado',
    },
    {
        label: 'Despesas variáveis',
        value: formatCurrency(props.expenseSummary.variableTotal),
        detail: 'No período selecionado',
    },
]);

function formatCurrency(value: string | number): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(Number(value));
}

function formatDate(value: string): string {
    const [year, month, day] = value.split('-');

    return `${day}/${month}/${year}`;
}

function printReport(): void {
    window.print();
}
</script>

<template>
    <Head title="Relatórios" />

    <main
        class="report-page mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8"
    >
        <PageHeader eyebrow="Financeiro" title="Relatórios">
            <template #description
                >{{ company.name }} · {{ formatDate(filters.startDate) }} a
                {{ formatDate(filters.endDate) }}</template
            >
            <template #actions>
                <PeriodFilter
                    :url="reportsIndex().url"
                    :filters="filters"
                    label="Período do relatório"
                />
                <button
                    type="button"
                    class="inline-flex min-h-11 items-center gap-2 rounded-lg border bg-card px-4 text-sm font-semibold hover:bg-muted"
                    @click="printReport"
                >
                    <Printer class="size-4" aria-hidden="true" />Imprimir / PDF
                </button>
            </template>
        </PageHeader>

        <section
            class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
            aria-label="Resumo de vendas"
        >
            <article
                v-for="metric in metrics"
                :key="metric.label"
                class="rounded-xl border bg-card p-5 text-card-foreground"
            >
                <p class="text-sm font-medium text-muted-foreground">
                    {{ metric.label }}
                </p>
                <p class="mt-3 text-2xl font-semibold tracking-tight">
                    {{ metric.value }}
                </p>
                <p class="mt-2 text-xs text-muted-foreground">
                    {{ metric.detail }}
                </p>
            </article>
        </section>

        <section
            class="overflow-hidden rounded-xl border bg-card text-card-foreground"
            aria-labelledby="products-sold-title"
        >
            <div class="border-b px-5 py-4 sm:px-6">
                <h2 id="products-sold-title" class="font-semibold">
                    Produtos vendidos
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Até 20 produtos mais vendidos. O valor bruto dos itens não
                    desconta descontos aplicados à venda.
                </p>
            </div>
            <div
                v-if="productsSold.length === 0"
                class="px-5 py-10 text-center sm:px-6"
            >
                <p class="font-medium">Nenhum item de venda neste período</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Os produtos aparecerão aqui depois das vendas finalizadas.
                </p>
            </div>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[520px] text-left text-sm">
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th
                                scope="col"
                                class="px-5 py-3 font-medium sm:px-6"
                            >
                                Produto
                            </th>
                            <th
                                scope="col"
                                class="px-5 py-3 text-right font-medium"
                            >
                                Unidades vendidas
                            </th>
                            <th
                                scope="col"
                                class="px-5 py-3 text-right font-medium"
                            >
                                Valor bruto dos itens
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="product in productsSold"
                            :key="product.productName"
                            class="border-t"
                        >
                            <th
                                scope="row"
                                class="px-5 py-4 font-medium sm:px-6"
                            >
                                {{ product.productName }}
                            </th>
                            <td class="px-5 py-4 text-right">
                                {{ product.quantity }}
                            </td>
                            <td class="px-5 py-4 text-right font-medium">
                                {{ formatCurrency(product.grossTotal) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section
            class="overflow-hidden rounded-xl border bg-card text-card-foreground"
            aria-labelledby="sales-list-title"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4 sm:px-6"
            >
                <div>
                    <h2 id="sales-list-title" class="font-semibold">
                        Vendas recentes
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Até 25 movimentações no período filtrado.
                    </p>
                </div>
                <span class="text-sm text-muted-foreground"
                    >{{ formatDate(filters.startDate) }} —
                    {{ formatDate(filters.endDate) }}</span
                >
            </div>
            <div
                v-if="recentSales.length === 0"
                class="px-5 py-12 text-center sm:px-6"
            >
                <p class="font-medium">Nenhuma venda neste período</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Quando houver vendas, elas aparecerão neste relatório.
                </p>
            </div>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[700px] text-left text-sm">
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th
                                scope="col"
                                class="px-5 py-3 font-medium sm:px-6"
                            >
                                Data e hora
                            </th>
                            <th scope="col" class="px-5 py-3 font-medium">
                                Venda
                            </th>
                            <th scope="col" class="px-5 py-3 font-medium">
                                Operador
                            </th>
                            <th scope="col" class="px-5 py-3 font-medium">
                                Pagamento
                            </th>
                            <th
                                scope="col"
                                class="px-5 py-3 text-right font-medium"
                            >
                                Total
                            </th>
                            <th
                                scope="col"
                                class="px-5 py-3 text-right font-medium"
                            >
                                Situação
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="sale in recentSales"
                            :key="sale.id"
                            class="border-t"
                        >
                            <td class="px-5 py-4 sm:px-6">
                                {{ sale.occurredAt }}
                            </td>
                            <th scope="row" class="px-5 py-4 font-medium">
                                #{{ sale.id }}
                            </th>
                            <td class="px-5 py-4">{{ sale.operator }}</td>
                            <td class="px-5 py-4">{{ sale.paymentMethod }}</td>
                            <td class="px-5 py-4 text-right font-medium">
                                {{ formatCurrency(sale.total) }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        sale.status === 'estornada'
                                            ? 'bg-warning/15 text-warning'
                                            : 'bg-success/10 text-success'
                                    "
                                >
                                    {{
                                        sale.status === 'estornada'
                                            ? 'Estornada'
                                            : 'Finalizada'
                                    }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section
            class="flex flex-col gap-4"
            aria-labelledby="expense-report-title"
        >
            <div>
                <h2
                    id="expense-report-title"
                    class="text-xl font-semibold tracking-tight"
                >
                    Despesas do período
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Totais separados entre despesas fixas e variáveis.
                </p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="metric in expenseMetrics"
                    :key="metric.label"
                    class="rounded-xl border bg-card p-5 text-card-foreground"
                >
                    <p class="text-sm font-medium text-muted-foreground">
                        {{ metric.label }}
                    </p>
                    <p class="mt-3 text-2xl font-semibold tracking-tight">
                        {{ metric.value }}
                    </p>
                    <p class="mt-2 text-xs text-muted-foreground">
                        {{ metric.detail }}
                    </p>
                </article>
            </div>
            <div
                class="overflow-hidden rounded-xl border bg-card text-card-foreground"
            >
                <div class="border-b px-5 py-4 sm:px-6">
                    <h3 class="font-semibold">Despesas recentes</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Até 25 lançamentos no período selecionado.
                    </p>
                </div>
                <div
                    v-if="recentExpenses.length === 0"
                    class="px-5 py-10 text-center sm:px-6"
                >
                    <p class="font-medium">Nenhuma despesa neste período</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Os lançamentos aparecerão aqui depois de registrados.
                    </p>
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead class="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th
                                    scope="col"
                                    class="px-5 py-3 font-medium sm:px-6"
                                >
                                    Data
                                </th>
                                <th scope="col" class="px-5 py-3 font-medium">
                                    Descrição
                                </th>
                                <th scope="col" class="px-5 py-3 font-medium">
                                    Tipo
                                </th>
                                <th
                                    scope="col"
                                    class="px-5 py-3 text-right font-medium"
                                >
                                    Valor
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="expense in recentExpenses"
                                :key="expense.id"
                                class="border-t"
                            >
                                <td class="px-5 py-4 sm:px-6">
                                    {{ expense.date }}
                                </td>
                                <th scope="row" class="px-5 py-4 font-medium">
                                    {{ expense.description }}
                                </th>
                                <td class="px-5 py-4">
                                    {{
                                        expense.type === 'fixa'
                                            ? 'Fixa'
                                            : 'Variável'
                                    }}
                                </td>
                                <td class="px-5 py-4 text-right font-medium">
                                    {{ formatCurrency(expense.amount) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section
            class="flex flex-col gap-4"
            aria-labelledby="cash-report-title"
        >
            <div>
                <h2
                    id="cash-report-title"
                    class="text-xl font-semibold tracking-tight"
                >
                    Abertura e fechamento do caixa
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Turnos iniciados no período. Os totais de conferência
                    consideram apenas turnos fechados.
                </p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="metric in cashMetrics"
                    :key="metric.label"
                    class="rounded-xl border bg-card p-5 text-card-foreground"
                >
                    <p class="text-sm font-medium text-muted-foreground">
                        {{ metric.label }}
                    </p>
                    <p class="mt-3 text-2xl font-semibold tracking-tight">
                        {{ metric.value }}
                    </p>
                    <p class="mt-2 text-xs text-muted-foreground">
                        {{ metric.detail }}
                    </p>
                </article>
            </div>
            <div
                class="overflow-hidden rounded-xl border bg-card text-card-foreground"
            >
                <div class="border-b px-5 py-4 sm:px-6">
                    <h3 class="font-semibold">Turnos de caixa recentes</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Até 25 turnos iniciados no período selecionado.
                    </p>
                </div>
                <div
                    v-if="recentCashSessions.length === 0"
                    class="px-5 py-12 text-center sm:px-6"
                >
                    <p class="font-medium">
                        Nenhum turno de caixa neste período
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        As aberturas e os fechamentos aparecerão aqui.
                    </p>
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left text-sm">
                        <thead class="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th
                                    scope="col"
                                    class="px-5 py-3 font-medium sm:px-6"
                                >
                                    Abertura
                                </th>
                                <th scope="col" class="px-5 py-3 font-medium">
                                    Operador
                                </th>
                                <th
                                    scope="col"
                                    class="px-5 py-3 text-right font-medium"
                                >
                                    Fundo inicial
                                </th>
                                <th
                                    scope="col"
                                    class="px-5 py-3 text-right font-medium"
                                >
                                    Esperado
                                </th>
                                <th
                                    scope="col"
                                    class="px-5 py-3 text-right font-medium"
                                >
                                    Contado
                                </th>
                                <th
                                    scope="col"
                                    class="px-5 py-3 text-right font-medium"
                                >
                                    Diferença
                                </th>
                                <th
                                    scope="col"
                                    class="px-5 py-3 text-right font-medium"
                                >
                                    Situação
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="session in recentCashSessions"
                                :key="session.id"
                                class="border-t"
                            >
                                <td class="px-5 py-4 sm:px-6">
                                    <span class="block">{{
                                        session.openedAt
                                    }}</span
                                    ><span
                                        class="mt-1 block text-xs text-muted-foreground"
                                        >Fechamento:
                                        {{
                                            session.closedAt ?? 'em aberto'
                                        }}</span
                                    >
                                </td>
                                <th scope="row" class="px-5 py-4 font-medium">
                                    {{ session.operator }}
                                </th>
                                <td class="px-5 py-4 text-right">
                                    {{ formatCurrency(session.openingAmount) }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    {{
                                        session.expectedAmount === null
                                            ? '—'
                                            : formatCurrency(
                                                  session.expectedAmount,
                                              )
                                    }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    {{
                                        session.countedAmount === null
                                            ? '—'
                                            : formatCurrency(
                                                  session.countedAmount,
                                              )
                                    }}
                                </td>
                                <td
                                    class="px-5 py-4 text-right font-medium"
                                    :class="
                                        Number(session.differenceAmount ?? 0) <
                                        0
                                            ? 'text-destructive'
                                            : ''
                                    "
                                >
                                    {{
                                        session.differenceAmount === null
                                            ? '—'
                                            : formatCurrency(
                                                  session.differenceAmount,
                                              )
                                    }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="
                                            session.status === 'aberto'
                                                ? 'bg-primary/10 text-primary'
                                                : 'bg-muted text-muted-foreground'
                                        "
                                    >
                                        {{
                                            session.status === 'aberto'
                                                ? 'Aberto'
                                                : 'Fechado'
                                        }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</template>

<style>
@page {
    margin: 12mm;
}

@media print {
    body {
        background: white !important;
        color: #111827 !important;
    }

    .report-page {
        max-width: none !important;
        padding: 0 !important;
        color: #111827 !important;
    }

    .report-page section,
    .report-page article,
    .report-page tr {
        break-inside: avoid;
    }

    .report-page .overflow-x-auto {
        overflow: visible !important;
    }

    .report-page table {
        min-width: 0 !important;
        width: 100% !important;
    }
}
</style>
