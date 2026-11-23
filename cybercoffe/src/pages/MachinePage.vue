<script setup lang="ts">
import { api, errorMessage } from '@cc/api';
import FlashMessage from '@cc/components/FlashMessage.vue';
import PixelBlocks from '@cc/components/PixelBlocks.vue';
import StatusBadge from '@cc/components/StatusBadge.vue';
import TimerRing from '@cc/components/TimerRing.vue';
import { useFlash } from '@cc/composables/useFlash';
import { usePolling } from '@cc/composables/usePolling';
import {
    displayStatus,
    ENDING_THRESHOLD_SECONDS,
    remainingSeconds,
    syncServerClock,
    useServerNow,
} from '@cc/composables/useServerClock';
import { formatDuration, formatMoney, TIER_LABEL } from '@cc/lib/format';
import { machinePath } from '@cc/router';
import type { Plan, Station } from '@cc/types';
import {
    ArrowRight,
    Pause,
    Play,
    Plus,
    Power,
    TriangleAlert,
    Wallet,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = defineProps<{ stationId: number | null }>();

const station = ref<Station | null>(null);
const stations = ref<Station[]>([]);
const plans = ref<Plan[]>([]);
const loadError = ref<string | null>(null);
const isBusy = ref(false);
const isExtendOpen = ref(false);
const isConfirmingEnd = ref(false);

const now = useServerNow();
const { flash, show, dismiss } = useFlash();

const session = computed(() => station.value?.session ?? null);
const remaining = computed(() =>
    session.value ? remainingSeconds(session.value, now.value) : 0,
);
const status = computed(() =>
    station.value
        ? displayStatus(station.value.status, session.value, now.value)
        : 'offline',
);
const isPaused = computed(() => session.value?.status === 'paused');
const isEnding = computed(
    () =>
        session.value?.status === 'active' &&
        remaining.value <= ENDING_THRESHOLD_SECONDS,
);
const ringTone = computed(() =>
    isPaused.value ? 'violet' : isEnding.value ? 'amber' : 'neon',
);
const currentPlan = computed(
    () =>
        plans.value.find((plan) => plan.id === session.value?.plan_id) ?? null,
);

async function load(): Promise<void> {
    try {
        if (plans.value.length === 0) {
            plans.value = (await api.listPlans()).data;
        }

        if (props.stationId === null) {
            const response = await api.listStations();
            syncServerClock(response.server_time);
            stations.value = response.data;
            station.value = null;
        } else {
            const response = await api.getStation(props.stationId);
            syncServerClock(response.server_time);
            station.value = response.data;
        }

        loadError.value = null;
    } catch (error) {
        loadError.value = errorMessage(error);
    }
}

usePolling(load, 5000);

watch(
    () => props.stationId,
    () => {
        station.value = null;
        isExtendOpen.value = false;
        isConfirmingEnd.value = false;
        void load();
    },
);

// Quando o tempo zera, pede ao servidor o estado oficial (ele encerra a sessão).
watch(remaining, (value, previous) => {
    if (value === 0 && previous > 0) {
        void load();
    }
});

async function run(
    action: () => Promise<unknown>,
    successMessage: string,
): Promise<void> {
    isBusy.value = true;

    try {
        await action();
        show('success', successMessage);
        await load();
    } catch (error) {
        show('error', errorMessage(error));
    } finally {
        isBusy.value = false;
    }
}

function togglePause(): void {
    const current = session.value;

    if (!current) {
        return;
    }

    void run(
        () =>
            current.status === 'paused'
                ? api.resumeSession(current.id)
                : api.pauseSession(current.id),
        current.status === 'paused' ? 'Sessão retomada.' : 'Sessão pausada.',
    );
}

function extend(plan: Plan): void {
    const current = session.value;

    if (!current) {
        return;
    }

    isExtendOpen.value = false;
    void run(
        () => api.extendSession(current.id, plan.id, 'credit'),
        `+${formatDuration(plan.minutes)} adicionados.`,
    );
}

function endSession(): void {
    const current = session.value;

    if (!current) {
        return;
    }

    isConfirmingEnd.value = false;
    void run(
        () => api.endSession(current.id),
        'Sessão encerrada. Até a próxima!',
    );
}
</script>

<template>
    <div class="mx-auto max-w-6xl px-4 pt-8 pb-32 sm:px-6 lg:pb-16">
        <div
            v-if="loadError"
            role="alert"
            class="panel pixel-corners text-danger mb-6 flex items-center gap-3 p-4"
        >
            <TriangleAlert class="size-5 shrink-0" />
            {{ loadError }}
        </div>

        <!-- Escolha de máquina (rota #/maquina sem id) -->
        <section v-if="stationId === null" class="animate-rise">
            <p class="eyebrow">Tela da máquina</p>
            <h1 class="mt-3 text-4xl font-bold tracking-tight">
                Qual máquina é esta?
            </h1>
            <p class="text-dim mt-2 max-w-xl">
                No salão, cada PC abre direto na própria tela. Aqui você escolhe
                qual visualizar.
            </p>
            <ul
                class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"
            >
                <li v-for="item in stations" :key="item.id">
                    <a
                        :href="`#${machinePath(item.id)}`"
                        class="panel pixel-corners hover:bg-panel-2 flex h-full flex-col gap-3 p-4 transition-colors"
                    >
                        <StatusBadge
                            class="self-start"
                            :status="
                                displayStatus(item.status, item.session, now)
                            "
                        />
                        <span class="text-xl font-bold">{{ item.name }}</span>
                    </a>
                </li>
            </ul>
        </section>

        <template v-else-if="station">
            <header
                class="mb-8 flex flex-wrap items-center justify-between gap-4"
            >
                <div>
                    <p class="eyebrow">
                        {{ TIER_LABEL[station.tier] }} · {{ station.specs }}
                    </p>
                    <h1 class="mt-1 text-4xl font-bold tracking-tight">
                        {{ station.name }}
                    </h1>
                </div>
                <StatusBadge :status="status" class="text-xs" />
            </header>

            <!-- Sem sessão: tela bloqueada -->
            <section
                v-if="!session"
                class="panel pixel-corners relative flex flex-col items-center gap-6 overflow-hidden px-6 py-16 text-center"
            >
                <PixelBlocks
                    class="absolute top-6 left-6"
                    :columns="6"
                    :rows="3"
                    :seed="5"
                />
                <PixelBlocks
                    class="absolute right-6 bottom-6 rotate-180"
                    :columns="6"
                    :rows="3"
                    :seed="9"
                />
                <p class="eyebrow">
                    {{
                        station.status === 'maintenance'
                            ? 'Em manutenção'
                            : 'Máquina bloqueada'
                    }}
                </p>
                <p class="text-6xl font-bold tracking-tight sm:text-7xl">
                    {{ station.name }}
                </p>
                <p class="text-dim max-w-md">
                    {{
                        station.status === 'maintenance'
                            ? 'Esta estação está fora de uso no momento. Fale com o atendente.'
                            : 'Faça o check-in para liberar esta estação.'
                    }}
                </p>
                <a
                    v-if="station.status === 'free'"
                    :href="`#/checkin/${station.id}`"
                    class="btn btn-neon h-14 px-8 text-base"
                >
                    Fazer check-in <ArrowRight class="size-5" />
                </a>
            </section>

            <!-- Sessão ativa ou pausada -->
            <div v-else class="grid items-start gap-8 lg:grid-cols-[1fr_340px]">
                <section
                    class="panel pixel-corners relative flex flex-col items-center gap-8 overflow-hidden px-4 py-10 sm:px-10"
                >
                    <div
                        v-if="isEnding"
                        role="status"
                        class="pixel-corners bg-amber/12 text-amber ring-amber/40 flex w-full max-w-lg items-center gap-3 px-4 py-3 text-sm ring-1 ring-inset"
                    >
                        <TriangleAlert class="size-4 shrink-0" />
                        <span class="text-ink flex-1">
                            Seu tempo está acabando. Adicione mais para não
                            perder o progresso.
                        </span>
                    </div>

                    <TimerRing
                        :remaining-seconds="remaining"
                        :total-seconds="session.total_seconds"
                        :tone="ringTone"
                        :caption="isPaused ? 'Pausado' : 'Tempo restante'"
                    />

                    <div
                        class="grid w-full max-w-lg grid-cols-3 gap-2 sm:gap-3"
                    >
                        <button
                            type="button"
                            class="btn btn-ghost h-16 flex-col gap-1 px-2 text-xs"
                            :disabled="isBusy"
                            @click="togglePause"
                        >
                            <Play v-if="isPaused" class="text-violet size-5" />
                            <Pause v-else class="size-5" />
                            {{ isPaused ? 'Retomar' : 'Pausar' }}
                        </button>
                        <button
                            type="button"
                            class="btn btn-sky h-16 flex-col gap-1 px-2 text-xs"
                            :disabled="isBusy"
                            :aria-expanded="isExtendOpen"
                            aria-controls="extend-panel"
                            @click="
                                isExtendOpen = !isExtendOpen;
                                isConfirmingEnd = false;
                            "
                        >
                            <Plus class="size-5" />
                            Adicionar tempo
                        </button>
                        <button
                            type="button"
                            class="btn btn-danger h-16 flex-col gap-1 px-2 text-xs"
                            :disabled="isBusy"
                            @click="
                                isConfirmingEnd = true;
                                isExtendOpen = false;
                            "
                        >
                            <Power class="size-5" />
                            Encerrar
                        </button>
                    </div>

                    <!-- Adicionar tempo -->
                    <div
                        v-if="isExtendOpen"
                        id="extend-panel"
                        class="animate-rise w-full max-w-lg"
                    >
                        <div class="mb-3 flex items-center justify-between">
                            <p class="eyebrow">Pagar com crédito</p>
                            <button
                                type="button"
                                class="text-dim hover:text-ink"
                                aria-label="Fechar"
                                @click="isExtendOpen = false"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                v-for="plan in plans"
                                :key="plan.id"
                                type="button"
                                class="pixel-corners bg-panel-2 ring-line-strong hover:bg-sky/10 hover:ring-sky flex flex-col items-start gap-0.5 p-3 text-left ring-1 transition-colors ring-inset disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="
                                    isBusy ||
                                    plan.price_cents > session.credit_cents
                                "
                                @click="extend(plan)"
                            >
                                <span class="tabular font-bold">
                                    +{{ formatDuration(plan.minutes) }}
                                </span>
                                <span class="tabular text-dim text-xs">
                                    {{ formatMoney(plan.price_cents) }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Confirmação de encerramento -->
                    <div
                        v-if="isConfirmingEnd"
                        role="alertdialog"
                        aria-labelledby="end-title"
                        class="pixel-corners animate-rise bg-danger/10 ring-danger/40 w-full max-w-lg p-4 ring-1 ring-inset"
                    >
                        <p id="end-title" class="font-semibold">
                            Encerrar a sessão agora?
                        </p>
                        <p class="text-dim mt-1 text-sm">
                            A máquina será bloqueada. Salve seus jogos antes.
                        </p>
                        <div class="mt-4 flex gap-2">
                            <button
                                type="button"
                                class="btn btn-danger flex-1"
                                :disabled="isBusy"
                                @click="endSession"
                            >
                                Sim, encerrar
                            </button>
                            <button
                                type="button"
                                class="btn btn-ghost flex-1"
                                @click="isConfirmingEnd = false"
                            >
                                Continuar jogando
                            </button>
                        </div>
                    </div>

                    <FlashMessage
                        v-if="flash"
                        class="w-full max-w-lg"
                        :tone="flash.tone"
                        :message="flash.message"
                        @dismiss="dismiss"
                    />
                </section>

                <aside class="flex flex-col gap-4">
                    <article
                        class="panel pixel-corners relative overflow-hidden p-5"
                    >
                        <div class="flex items-center justify-between">
                            <span class="eyebrow">Crédito disponível</span>
                            <Wallet class="text-sky size-4" />
                        </div>
                        <p class="tabular text-sky mt-3 text-4xl font-bold">
                            {{ formatMoney(session.credit_cents) }}
                        </p>
                        <p class="text-dim mt-1 text-xs">
                            {{
                                api.isDemo
                                    ? 'Saldo de demonstração.'
                                    : 'Recarregue no balcão por Pix ou dinheiro.'
                            }}
                        </p>
                        <PixelBlocks
                            class="absolute right-4 bottom-4"
                            :columns="4"
                            :rows="2"
                            :size="6"
                            tone="sky"
                            :seed="21"
                        />
                    </article>

                    <article class="panel pixel-corners p-5">
                        <span class="eyebrow">Sessão</span>
                        <dl class="mt-4 grid gap-3 text-sm">
                            <div class="flex justify-between gap-2">
                                <dt class="text-dim">Cliente</dt>
                                <dd class="font-medium">
                                    {{ session.customer_name ?? 'Avulso' }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-2">
                                <dt class="text-dim">Plano</dt>
                                <dd class="font-medium">
                                    {{ currentPlan?.name ?? '—' }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-2">
                                <dt class="text-dim">Tempo total</dt>
                                <dd class="tabular font-medium">
                                    {{
                                        formatDuration(
                                            Math.round(
                                                session.total_seconds / 60,
                                            ),
                                        )
                                    }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-2">
                                <dt class="text-dim">Início</dt>
                                <dd class="tabular font-medium">
                                    {{
                                        new Date(
                                            session.started_at,
                                        ).toLocaleTimeString('pt-BR', {
                                            hour: '2-digit',
                                            minute: '2-digit',
                                        })
                                    }}
                                </dd>
                            </div>
                        </dl>
                    </article>
                </aside>
            </div>
        </template>

        <div v-else-if="!loadError" class="flex justify-center py-24">
            <PixelBlocks
                class="animate-pulse"
                :columns="6"
                :rows="2"
                :seed="2"
            />
        </div>
    </div>
</template>
