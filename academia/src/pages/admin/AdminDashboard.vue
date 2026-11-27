<script setup lang="ts">
import {
    Activity,
    MessageCircle,
    Send,
    TriangleAlert,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import AccessLog from '../../components/AccessLog.vue';
import Card from '../../components/Card.vue';
import ColumnChart from '../../components/charts/ColumnChart.vue';
import OccupancyHeatmap from '../../components/charts/OccupancyHeatmap.vue';
import KpiCard from '../../components/KpiCard.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
import { HEATMAP_DAYS, HEATMAP_HOURS } from '../../data/demo';
import { formatCompactCurrency, formatCurrency } from '../../lib/format';
import {
    activeStudents,
    atRiskStudents,
    gymCapacity,
    occupancyGrid,
    overdueStudents,
    overdueTotalInCents,
    revenueMonths,
} from '../../lib/reports';
import { state } from '../../lib/store';
import { showToast } from '../../lib/toast';

const currentMonth = revenueMonths[revenueMonths.length - 1]!;
const receivedRatio = Math.round(
    (currentMonth.receivedInCents / currentMonth.expectedInCents) * 100,
);

const recentEvents = computed(() => state.accessEvents.slice(0, 7));

function charge(name: string): void {
    showToast({
        title: `Cobrança enviada para ${name}`,
        description: 'WhatsApp com link Pix (demonstração — nada foi enviado).',
        tone: 'ok',
    });
}

function reengage(name: string): void {
    showToast({
        title: `Mensagem "sentimos sua falta" para ${name}`,
        description: 'Demonstração — nada foi enviado.',
        tone: 'ok',
    });
}
</script>

<template>
    <AdminLayout
        title="Visão geral"
        :subtitle="`${state.gym.name} · mês atual`"
    >
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
            <KpiCard
                label="Recebido no mês"
                :value="formatCompactCurrency(currentMonth.receivedInCents)"
                :hint="`${receivedRatio}% de ${formatCompactCurrency(currentMonth.expectedInCents)} previsto`"
                :icon="Wallet"
            >
                <div class="mt-3 h-1.5 rounded-full bg-slate-100">
                    <div
                        class="bg-series-1 h-full rounded-full"
                        :style="{ width: `${receivedRatio}%` }"
                    />
                </div>
            </KpiCard>
            <KpiCard
                label="Alunos ativos"
                :value="String(activeStudents.length)"
                hint="matrículas não canceladas"
                :icon="Users"
            />
            <KpiCard
                label="Na academia agora"
                :value="String(state.occupancy)"
                :hint="`de ${state.gym.capacity} · ${Math.round((state.occupancy / state.gym.capacity) * 100)}% da capacidade`"
                :icon="Activity"
                tone="live"
            />
            <KpiCard
                label="Inadimplentes"
                :value="String(overdueStudents.length)"
                :hint="`${formatCurrency(overdueTotalInCents)} em aberto`"
                :icon="TriangleAlert"
                tone="warn"
            />
        </div>

        <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
            <Card
                class="lg:col-span-2"
                title="Faturamento: previsto x recebido"
                description="Últimos 6 meses"
            >
                <template #actions>
                    <a
                        href="#/admin/relatorios"
                        class="text-sm font-medium text-lime-700 hover:underline"
                        >Relatórios</a
                    >
                </template>
                <ColumnChart
                    label="Faturamento previsto e recebido por mês"
                    :categories="revenueMonths.map((month) => month.month)"
                    :series="[
                        {
                            name: 'Previsto',
                            values: revenueMonths.map(
                                (month) => month.expectedInCents,
                            ),
                            colorClass: 'fill-series-2',
                            swatchClass: 'bg-series-2',
                        },
                        {
                            name: 'Recebido',
                            values: revenueMonths.map(
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

            <Card title="Acesso em tempo real">
                <template #actions>
                    <a
                        href="#/admin/acesso"
                        class="text-sm font-medium text-lime-700 hover:underline"
                        >Ver tudo</a
                    >
                </template>
                <AccessLog :events="recentEvents" />
            </Card>
        </div>

        <Card
            title="Ocupação média"
            description="Pessoas na academia por dia e hora — use para escalar professores e planejar aulas"
        >
            <OccupancyHeatmap
                :days="HEATMAP_DAYS"
                :hours="HEATMAP_HOURS"
                :values="occupancyGrid"
                :capacity="gymCapacity"
            />
        </Card>

        <div class="grid gap-4 lg:grid-cols-2 lg:gap-6">
            <Card
                title="Inadimplentes"
                :description="`${overdueStudents.length} alunos · ${formatCurrency(overdueTotalInCents)}`"
            >
                <ul class="divide-y divide-slate-100">
                    <li
                        v-for="item in overdueStudents.slice(0, 5)"
                        :key="item.student.id"
                        class="flex items-center gap-3 py-2.5"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ item.student.name }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ item.days }} dias em atraso ·
                                {{ formatCurrency(item.amountInCents) }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="border-line flex min-h-9 items-center gap-1.5 rounded-lg border px-3 text-sm font-medium hover:bg-slate-50"
                            @click="charge(item.student.name)"
                        >
                            <MessageCircle class="size-4" /> Cobrar
                        </button>
                    </li>
                    <li
                        v-if="overdueStudents.length === 0"
                        class="py-6 text-center text-sm text-slate-500"
                    >
                        Nenhum aluno em atraso.
                    </li>
                </ul>
            </Card>

            <Card
                title="Risco de evasão"
                description="Sem treinar há 14+ dias (em dia com a mensalidade)"
            >
                <ul class="divide-y divide-slate-100">
                    <li
                        v-for="item in atRiskStudents.slice(0, 5)"
                        :key="item.student.id"
                        class="flex items-center gap-3 py-2.5"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ item.student.name }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{
                                    item.daysAway === null
                                        ? 'Nunca treinou'
                                        : `Último treino há ${item.daysAway} dias`
                                }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="border-line flex min-h-9 items-center gap-1.5 rounded-lg border px-3 text-sm font-medium hover:bg-slate-50"
                            @click="reengage(item.student.name)"
                        >
                            <Send class="size-4" /> Reengajar
                        </button>
                    </li>
                    <li
                        v-if="atRiskStudents.length === 0"
                        class="py-6 text-center text-sm text-slate-500"
                    >
                        Ninguém em risco agora.
                    </li>
                </ul>
            </Card>
        </div>
    </AdminLayout>
</template>
