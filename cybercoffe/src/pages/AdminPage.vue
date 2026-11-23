<script setup lang="ts">
import { api, errorMessage } from '@cc/api';
import FlashMessage from '@cc/components/FlashMessage.vue';
import MachineTile from '@cc/components/MachineTile.vue';
import MetricCard from '@cc/components/MetricCard.vue';
import PixelBlocks from '@cc/components/PixelBlocks.vue';
import { useFlash } from '@cc/composables/useFlash';
import { usePolling } from '@cc/composables/usePolling';
import {
    displayStatus,
    syncServerClock,
    useServerNow,
} from '@cc/composables/useServerClock';
import { formatMoney, STATUS_LABEL } from '@cc/lib/format';
import type { DashboardMetrics, Plan, Station, StationStatus } from '@cc/types';
import RevenueBreakdown from '@cc/components/RevenueBreakdown.vue';
import {
    Clock,
    Coins,
    CupSoda,
    InfinityIcon,
    Monitor,
    Printer,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';

type Filter = 'all' | StationStatus | 'ending';

const stations = ref<Station[]>([]);
const plans = ref<Plan[]>([]);
const metrics = ref<DashboardMetrics | null>(null);
const loadError = ref<string | null>(null);
const busyStationId = ref<number | null>(null);
const pendingEnd = ref<Station | null>(null);
const filter = ref<Filter>('all');

const now = useServerNow();
const { flash, show, dismiss } = useFlash();

const shortestPlan = computed(
    () =>
        plans.value
            .filter((plan) => plan.billing === 'package')
            .sort((first, second) => first.minutes - second.minutes)[0] ?? null,
);

const statusOf = (station: Station) =>
    displayStatus(station.status, station.session, now.value);

const filters = computed(() => {
    const options: Filter[] = [
        'all',
        'free',
        'in_use',
        'ending',
        ...(api.supportsPause ? (['paused'] as const) : []),
        'maintenance',
    ];

    return options.map((value) => ({
        value,
        label: value === 'all' ? 'Todas' : STATUS_LABEL[value],
        count:
            value === 'all'
                ? stations.value.length
                : stations.value.filter(
                      (station) => statusOf(station) === value,
                  ).length,
    }));
});

const visibleStations = computed(() =>
    filter.value === 'all'
        ? stations.value
        : stations.value.filter(
              (station) => statusOf(station) === filter.value,
          ),
);

async function load(): Promise<void> {
    try {
        const [stationResponse, metricResponse] = await Promise.all([
            api.listStations(),
            api.getDashboard(),
        ]);

        if (plans.value.length === 0) {
            plans.value = (await api.listPlans()).data;
        }

        syncServerClock(stationResponse.server_time);
        stations.value = stationResponse.data;
        metrics.value = metricResponse.data;
        loadError.value = null;
    } catch (error) {
        loadError.value = errorMessage(error);
    }
}

usePolling(load, 5000);

async function run(
    station: Station,
    action: () => Promise<unknown>,
    successMessage: string,
): Promise<void> {
    busyStationId.value = station.id;

    try {
        await action();
        show('success', successMessage);
        await load();
    } catch (error) {
        show('error', errorMessage(error));
    } finally {
        busyStationId.value = null;
    }
}

function extend(station: Station): void {
    const session = station.session;
    const plan = shortestPlan.value;

    if (!session || !plan) {
        return;
    }

    void run(
        station,
        () => api.extendSession(session.id, plan.id, 'counter'),
        `${station.name}: +${plan.minutes} min (pago no balcão).`,
    );
}

function togglePause(station: Station): void {
    const session = station.session;

    if (!session) {
        return;
    }

    void run(
        station,
        () =>
            session.status === 'paused'
                ? api.resumeSession(session.id)
                : api.pauseSession(session.id),
        `${station.name}: sessão ${session.status === 'paused' ? 'retomada' : 'pausada'}.`,
    );
}

function confirmEnd(): void {
    const station = pendingEnd.value;
    const session = station?.session;
    pendingEnd.value = null;

    if (!station || !session) {
        return;
    }

    void run(
        station,
        () => api.endSession(session.id),
        `${station.name}: sessão encerrada.`,
    );
}

function toggleMaintenance(station: Station): void {
    const enable = station.status !== 'maintenance';

    void run(
        station,
        () => api.setMaintenance(station.id, enable),
        `${station.name}: ${enable ? 'em manutenção' : 'liberada'}.`,
    );
}

const occupancy = computed(() =>
    metrics.value && metrics.value.total_stations > 0
        ? Math.round(
              (metrics.value.active_stations / metrics.value.total_stations) *
                  100,
          )
        : 0,
);
</script>

<template>
    <div class="mx-auto max-w-7xl px-4 pt-8 pb-32 sm:px-6 lg:pb-16">
        <header
            class="animate-rise mb-8 flex flex-wrap items-end justify-between gap-4"
        >
            <div>
                <p class="eyebrow">Painel administrativo · Hoje</p>
                <h1 class="mt-2 text-4xl font-bold tracking-tight">
                    Salão ao vivo
                </h1>
            </div>
            <div class="text-dim flex items-center gap-3 text-xs">
                <span class="relative flex size-2">
                    <span
                        class="bg-neon absolute inline-flex size-full animate-ping opacity-60"
                    />
                    <span class="bg-neon relative inline-flex size-2" />
                </span>
                Atualiza a cada 5 s
            </div>
        </header>

        <div
            v-if="loadError"
            role="alert"
            class="panel pixel-corners text-danger mb-6 p-4"
        >
            {{ loadError }}
        </div>

        <!-- Métricas -->
        <section
            aria-label="Métricas do dia"
            class="grid grid-cols-2 gap-3 lg:grid-cols-4"
        >
            <MetricCard
                label="Receita hoje"
                :value="
                    metrics ? formatMoney(metrics.revenue_today_cents) : '—'
                "
                :hint="
                    api.isDemo
                        ? 'Só vendas feitas nesta demonstração'
                        : 'Pagamentos recebidos hoje'
                "
                :icon="Coins"
            />
            <MetricCard
                label="Máquinas ativas"
                :value="
                    metrics
                        ? `${metrics.active_stations}/${metrics.total_stations}`
                        : '—'
                "
                :hint="metrics ? `${occupancy}% de ocupação` : undefined"
                :icon="Monitor"
                tone="sky"
            />
            <MetricCard
                label="Clientes hoje"
                :value="
                    metrics?.customers_today != null
                        ? String(metrics.customers_today)
                        : '—'
                "
                hint="Check-ins do dia"
                :icon="Users"
            />
            <MetricCard
                label="Tempo médio"
                :value="
                    metrics?.average_session_minutes != null
                        ? `${metrics.average_session_minutes} min`
                        : '—'
                "
                :hint="
                    metrics?.average_session_minutes != null
                        ? 'Sessões encerradas hoje'
                        : 'Sem sessões encerradas ainda'
                "
                :icon="Clock"
                tone="sky"
            />
        </section>

        <!-- Consumo do dia: tempo livre, impressões e produtos (WIL-36) -->
        <section
            aria-labelledby="consumption-title"
            class="mt-10 grid gap-3 lg:grid-cols-[1fr_1fr_1fr]"
        >
            <h2 id="consumption-title" class="sr-only">Consumo de hoje</h2>
            <MetricCard
                label="Tempo livre agora"
                :value="metrics ? String(metrics.open_sessions) : '—'"
                :hint="
                    metrics
                        ? metrics.open_sessions > 0
                            ? `${formatMoney(metrics.open_sessions_running_cents)} correndo, a cobrar ao encerrar`
                            : 'Nenhuma sessão em tempo livre'
                        : undefined
                "
                :icon="InfinityIcon"
            />
            <MetricCard
                label="Páginas impressas"
                :value="
                    metrics?.pages_printed_today != null
                        ? String(metrics.pages_printed_today)
                        : '—'
                "
                :hint="
                    metrics?.revenue_breakdown
                        ? `${formatMoney(metrics.revenue_breakdown.prints_cents)} em impressões`
                        : 'Aguardando dados do servidor'
                "
                :icon="Printer"
                tone="sky"
            />
            <MetricCard
                label="Produtos vendidos"
                :value="
                    metrics?.products_sold_today != null
                        ? String(metrics.products_sold_today)
                        : '—'
                "
                :hint="
                    metrics?.revenue_breakdown
                        ? `${formatMoney(metrics.revenue_breakdown.products_cents)} em produtos`
                        : 'Aguardando dados do servidor'
                "
                :icon="CupSoda"
            />
        </section>

        <section
            aria-label="Receita por origem e produtos mais vendidos"
            class="mt-3 grid gap-3 lg:grid-cols-[2fr_1fr]"
        >
            <RevenueBreakdown :breakdown="metrics?.revenue_breakdown ?? null" />
            <article class="panel pixel-corners p-5">
                <span class="eyebrow">Mais vendidos hoje</span>
                <ol
                    v-if="metrics?.top_products?.length"
                    class="mt-4 grid gap-2 text-sm"
                >
                    <li
                        v-for="(product, index) in metrics.top_products"
                        :key="product.name"
                        class="flex items-baseline gap-3"
                    >
                        <span class="tabular text-dim w-4 text-xs">{{
                            index + 1
                        }}</span>
                        <span class="flex-1 truncate font-medium">{{
                            product.name
                        }}</span>
                        <span class="tabular text-dim text-xs"
                            >{{ product.quantity }}×</span
                        >
                        <span class="tabular w-20 text-right font-semibold">{{
                            formatMoney(product.total_cents)
                        }}</span>
                    </li>
                </ol>
                <p v-else class="text-dim mt-4 text-sm">
                    {{
                        metrics?.top_products === null
                            ? 'O servidor ainda não informa vendas de produtos.'
                            : 'Nenhum produto vendido hoje.'
                    }}
                </p>
            </article>
        </section>

        <!-- Grade de máquinas -->
        <section aria-labelledby="grid-title" class="mt-10">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h2
                        id="grid-title"
                        class="text-lg font-semibold tracking-wide"
                    >
                        Máquinas
                    </h2>
                    <PixelBlocks :columns="6" :rows="1" :size="6" :seed="13" />
                </div>
                <div
                    role="group"
                    aria-label="Filtrar por status"
                    class="-mx-4 flex gap-1 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0"
                >
                    <button
                        v-for="option in filters"
                        :key="option.value"
                        type="button"
                        :aria-pressed="filter === option.value"
                        class="flex shrink-0 items-center gap-1.5 px-3 py-1.5 text-xs font-medium tracking-wide uppercase transition-colors"
                        :class="
                            filter === option.value
                                ? 'bg-ink text-void'
                                : 'text-dim ring-line-strong hover:text-ink ring-1 ring-inset'
                        "
                        @click="filter = option.value"
                    >
                        {{ option.label }}
                        <span class="tabular opacity-70">{{
                            option.count
                        }}</span>
                    </button>
                </div>
            </div>

            <FlashMessage
                v-if="flash"
                class="mb-4"
                :tone="flash.tone"
                :message="flash.message"
                @dismiss="dismiss"
            />

            <div
                v-if="pendingEnd"
                role="alertdialog"
                aria-labelledby="admin-end-title"
                class="pixel-corners animate-rise bg-danger/10 ring-danger/40 mb-4 flex flex-wrap items-center gap-3 p-4 ring-1 ring-inset"
            >
                <p id="admin-end-title" class="flex-1 text-sm">
                    Encerrar a sessão da
                    <strong>{{ pendingEnd.name }}</strong
                    >? A máquina será bloqueada.
                </p>
                <button
                    type="button"
                    class="btn btn-danger"
                    @click="confirmEnd"
                >
                    Encerrar
                </button>
                <button
                    type="button"
                    class="btn btn-ghost"
                    @click="pendingEnd = null"
                >
                    Cancelar
                </button>
            </div>

            <div
                v-if="stations.length === 0 && !loadError"
                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            >
                <div
                    v-for="index in 8"
                    :key="index"
                    class="pixel-corners bg-panel-2 h-44 animate-pulse"
                />
            </div>
            <p
                v-else-if="visibleStations.length === 0"
                class="panel pixel-corners text-dim p-8 text-center"
            >
                Nenhuma máquina com esse status agora.
            </p>
            <div
                v-else
                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            >
                <MachineTile
                    v-for="station in visibleStations"
                    :key="station.id"
                    :station="station"
                    :now="now"
                    :busy="busyStationId === station.id"
                    :can-pause="api.supportsPause"
                    @extend="extend"
                    @toggle-pause="togglePause"
                    @end="pendingEnd = $event"
                    @toggle-maintenance="toggleMaintenance"
                />
            </div>
        </section>
    </div>
</template>
