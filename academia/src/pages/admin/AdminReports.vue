<script setup lang="ts">
import { Download, MessageCircle } from '@lucide/vue';
import { computed, ref } from 'vue';
import BarList from '../../components/charts/BarList.vue';
import ColumnChart from '../../components/charts/ColumnChart.vue';
import OccupancyHeatmap from '../../components/charts/OccupancyHeatmap.vue';
import Card from '../../components/Card.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
import { HEATMAP_DAYS, HEATMAP_HOURS } from '../../data/demo';
import { formatCompactCurrency, formatCurrency } from '../../lib/format';
import {
    agingSummary,
    gymCapacity,
    occupancyGrid,
    overdueStudents,
    overdueTotalInCents,
    pixAutomaticoShare,
    revenueByPlan,
    revenueMonths,
} from '../../lib/reports';
import { showToast } from '../../lib/toast';

type Tab = 'faturamento' | 'ocupacao' | 'inadimplencia';

const tab = ref<Tab>('faturamento');
const period = ref<3 | 6>(6);

const tabs: { value: Tab; label: string }[] = [
    { value: 'faturamento', label: 'Faturamento' },
    { value: 'ocupacao', label: 'Ocupação' },
    { value: 'inadimplencia', label: 'Inadimplência' },
];

const months = computed(() => revenueMonths.slice(-period.value));
const totals = computed(() => ({
    expected: months.value.reduce(
        (sum, month) => sum + month.expectedInCents,
        0,
    ),
    received: months.value.reduce(
        (sum, month) => sum + month.receivedInCents,
        0,
    ),
}));

/** Média por hora (todos os dias) para destacar os horários de pico. */
const hourlyAverage = computed(() =>
    HEATMAP_HOURS.map((hour, index) => ({
        label: `${hour}h`,
        value: Math.round(
            occupancyGrid.reduce((sum, row) => sum + (row[index] ?? 0), 0) /
                occupancyGrid.length,
        ),
    }))
        .sort((a, b) => b.value - a.value)
        .slice(0, 5),
);

function bulkCharge(): void {
    showToast({
        title: `Cobrança em lote para ${overdueStudents.value.length} alunos`,
        description: 'Demonstração — nenhuma mensagem foi enviada.',
        tone: 'ok',
    });
}

function exportReport(): void {
    showToast({
        title: 'Exportação disponível com a API',
        description: 'O PDF é gerado sob demanda a partir dos registros.',
        tone: 'info',
    });
}
</script>

<template>
    <AdminLayout
        title="Relatórios"
        subtitle="Gerados sob demanda a partir dos registros"
    >
        <template #actions>
            <button
                type="button"
                class="border-line hidden min-h-9 items-center gap-1.5 rounded-lg border bg-white px-3 text-sm font-medium sm:flex"
                @click="exportReport"
            >
                <Download class="size-4" /> Exportar PDF
            </button>
        </template>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div
                class="ring-line flex rounded-xl bg-white p-1 ring-1"
                role="tablist"
            >
                <button
                    v-for="item in tabs"
                    :key="item.value"
                    type="button"
                    role="tab"
                    class="min-h-9 rounded-lg px-4 text-sm font-medium"
                    :class="
                        tab === item.value
                            ? 'bg-ink-950 text-white'
                            : 'text-slate-600 hover:text-slate-900'
                    "
                    :aria-selected="tab === item.value"
                    @click="tab = item.value"
                >
                    {{ item.label }}
                </button>
            </div>
            <div
                v-if="tab === 'faturamento'"
                class="ring-line flex rounded-xl bg-white p-1 ring-1"
            >
                <button
                    v-for="option in [3, 6] as const"
                    :key="option"
                    type="button"
                    class="min-h-8 rounded-lg px-3 text-sm"
                    :class="
                        period === option
                            ? 'bg-slate-100 font-semibold'
                            : 'text-slate-500'
                    "
                    :aria-pressed="period === option"
                    @click="period = option"
                >
                    {{ option }} meses
                </button>
            </div>
        </div>

        <!-- FATURAMENTO -->
        <template v-if="tab === 'faturamento'">
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
                <div class="rounded-card border-line border bg-white p-4">
                    <p class="text-sm text-slate-500">Previsto</p>
                    <p class="font-display tabular mt-2 text-2xl font-bold">
                        {{ formatCompactCurrency(totals.expected) }}
                    </p>
                </div>
                <div class="rounded-card border-line border bg-white p-4">
                    <p class="text-sm text-slate-500">Recebido</p>
                    <p class="font-display tabular mt-2 text-2xl font-bold">
                        {{ formatCompactCurrency(totals.received) }}
                    </p>
                </div>
                <div class="rounded-card border-line border bg-white p-4">
                    <p class="text-sm text-slate-500">Taxa de recebimento</p>
                    <p class="font-display tabular mt-2 text-2xl font-bold">
                        {{
                            Math.round(
                                (totals.received / totals.expected) * 100,
                            )
                        }}%
                    </p>
                </div>
                <div class="rounded-card border-line border bg-white p-4">
                    <p class="text-sm text-slate-500">
                        Alunos no Pix Automático
                    </p>
                    <p class="font-display tabular mt-2 text-2xl font-bold">
                        {{ Math.round(pixAutomaticoShare * 100) }}%
                    </p>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
                <Card class="lg:col-span-2" title="Previsto x recebido por mês">
                    <ColumnChart
                        label="Faturamento previsto e recebido por mês"
                        :categories="months.map((month) => month.month)"
                        :series="[
                            {
                                name: 'Previsto',
                                values: months.map(
                                    (month) => month.expectedInCents,
                                ),
                                colorClass: 'fill-series-2',
                                swatchClass: 'bg-series-2',
                            },
                            {
                                name: 'Recebido',
                                values: months.map(
                                    (month) => month.receivedInCents,
                                ),
                                colorClass: 'fill-series-1',
                                swatchClass: 'bg-series-1',
                            },
                        ]"
                        :format="formatCurrency"
                        :compact-format="formatCompactCurrency"
                    />
                </Card>
                <Card
                    title="Receita mensal por plano"
                    description="Alunos ativos × preço do plano"
                >
                    <BarList
                        :items="
                            revenueByPlan.map((plan) => ({
                                label: plan.label,
                                value: plan.value,
                                detail: `· ${plan.count}`,
                            }))
                        "
                        :format="formatCompactCurrency"
                    />
                </Card>
            </div>
        </template>

        <!-- OCUPAÇÃO -->
        <template v-else-if="tab === 'ocupacao'">
            <Card
                title="Ocupação média por dia e hora"
                description="Passe o mouse ou toque nas células para ver o valor"
            >
                <OccupancyHeatmap
                    :days="HEATMAP_DAYS"
                    :hours="HEATMAP_HOURS"
                    :values="occupancyGrid"
                    :capacity="gymCapacity"
                />
            </Card>
            <Card
                title="Horários de pico"
                description="Média de pessoas por hora, todos os dias"
            >
                <BarList
                    :items="hourlyAverage"
                    :format="(value) => `${value} pessoas`"
                />
            </Card>
        </template>

        <!-- INADIMPLÊNCIA -->
        <template v-else>
            <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
                <Card
                    title="Valor em aberto por atraso"
                    :description="`${formatCurrency(overdueTotalInCents)} no total`"
                >
                    <BarList
                        :items="
                            agingSummary.map((bucket) => ({
                                label: bucket.label,
                                value: bucket.value,
                                detail: `· ${bucket.count} alunos`,
                            }))
                        "
                        :format="formatCurrency"
                        bar-class="bg-warn"
                    />
                </Card>
                <Card
                    class="lg:col-span-2"
                    title="Alunos inadimplentes"
                    :description="`${overdueStudents.length} alunos`"
                >
                    <template #actions>
                        <button
                            type="button"
                            class="bg-ink-950 flex min-h-9 items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-white disabled:opacity-40"
                            :disabled="overdueStudents.length === 0"
                            @click="bulkCharge"
                        >
                            <MessageCircle class="size-4" /> Cobrar todos
                        </button>
                    </template>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[28rem] text-sm">
                            <thead>
                                <tr
                                    class="border-line border-b text-left text-xs text-slate-500"
                                >
                                    <th class="py-2 font-medium">Aluno</th>
                                    <th class="py-2 text-right font-medium">
                                        Dias em atraso
                                    </th>
                                    <th class="py-2 text-right font-medium">
                                        Valor
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in overdueStudents"
                                    :key="item.student.id"
                                    class="border-b border-slate-100"
                                >
                                    <td class="py-2">
                                        {{ item.student.name }}
                                    </td>
                                    <td
                                        class="tabular py-2 text-right"
                                        :class="
                                            item.days > 30
                                                ? 'text-danger-ink font-semibold'
                                                : ''
                                        "
                                    >
                                        {{ item.days }}
                                    </td>
                                    <td class="tabular py-2 text-right">
                                        {{ formatCurrency(item.amountInCents) }}
                                    </td>
                                </tr>
                                <tr v-if="overdueStudents.length === 0">
                                    <td
                                        colspan="3"
                                        class="py-6 text-center text-slate-500"
                                    >
                                        Nenhum aluno em atraso.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </template>
    </AdminLayout>
</template>
