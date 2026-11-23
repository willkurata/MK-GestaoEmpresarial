<script setup lang="ts">
import { api, errorMessage } from '@cc/api';
import FlashMessage from '@cc/components/FlashMessage.vue';
import PixelBlocks from '@cc/components/PixelBlocks.vue';
import PrintCounter from '@cc/components/PrintCounter.vue';
import ProductPicker from '@cc/components/ProductPicker.vue';
import SessionBill from '@cc/components/SessionBill.vue';
import SessionTicker from '@cc/components/SessionTicker.vue';
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
import { billTotals, elapsedSeconds } from '@cc/lib/billing';
import {
    formatDuration,
    formatMoney,
    formatRate,
    TIER_LABEL,
} from '@cc/lib/format';
import { machinePath } from '@cc/router';
import type {
    NewSessionItem,
    Plan,
    Product,
    Session,
    Station,
} from '@cc/types';
import {
    ArrowRight,
    CupSoda,
    InfinityIcon,
    Pause,
    Play,
    Plus,
    Power,
    Printer,
    TriangleAlert,
    Wallet,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';

type Panel = 'none' | 'extend' | 'products' | 'prints' | 'end';

const props = defineProps<{ stationId: number | null }>();

const station = ref<Station | null>(null);
const stations = ref<Station[]>([]);
const plans = ref<Plan[]>([]);
const products = ref<Product[] | null>(null);
const productsError = ref<string | null>(null);
const loadError = ref<string | null>(null);
const isBusy = ref(false);
const panel = ref<Panel>('none');
/** Sessão recém-encerrada nesta tela, para mostrar a conta final. */
const closedSession = ref<Session | null>(null);

const now = useServerNow();
const { flash, show, dismiss } = useFlash();

const session = computed(() => station.value?.session ?? null);
const isOpen = computed(() => session.value?.billing === 'open');
const remaining = computed(() =>
    session.value ? remainingSeconds(session.value, now.value) : 0,
);
const elapsed = computed(() =>
    session.value ? elapsedSeconds(session.value, now.value) : 0,
);
const bill = computed(() =>
    session.value ? billTotals(session.value, now.value) : null,
);
const status = computed(() =>
    station.value
        ? displayStatus(station.value.status, session.value, now.value)
        : 'offline',
);
const isPaused = computed(() => session.value?.status === 'paused');
const isEnding = computed(
    () =>
        !isOpen.value &&
        session.value?.status === 'active' &&
        remaining.value <= ENDING_THRESHOLD_SECONDS,
);
const canPause = computed(() => api.supportsPause && !isOpen.value);
const packagePlans = computed(() =>
    plans.value.filter((plan) => plan.billing === 'package'),
);
const currentPlan = computed(
    () =>
        plans.value.find((plan) => plan.id === session.value?.plan_id) ?? null,
);

const timer = computed(() => {
    const current = session.value;

    if (!current) {
        return null;
    }

    if (isOpen.value) {
        return {
            seconds: elapsed.value,
            // Cronômetro: o anel completa uma volta por hora.
            progress: (elapsed.value % 3600) / 3600,
            tone: 'neon' as const,
            caption: 'Tempo livre',
            footnote:
                current.hourly_rate_cents !== null
                    ? `${formatRate(current.hourly_rate_cents)} · por minuto`
                    : 'Cobrança por minuto',
        };
    }

    const progress =
        current.total_seconds > 0 ? remaining.value / current.total_seconds : 0;

    return {
        seconds: remaining.value,
        progress,
        tone: isPaused.value
            ? ('violet' as const)
            : isEnding.value
              ? ('amber' as const)
              : ('neon' as const),
        caption: isPaused.value ? 'Pausado' : 'Tempo restante',
        footnote: `${Math.round(Math.min(1, progress) * 100)}% restante`,
    };
});

async function loadProducts(): Promise<void> {
    try {
        products.value = (await api.listProducts()).data;
        productsError.value = null;
    } catch (error) {
        products.value = [];
        productsError.value = errorMessage(error);
    }
}

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

            if (products.value === null) {
                void loadProducts();
            }
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
        closedSession.value = null;
        panel.value = 'none';
        void load();
    },
);

// Quando o pacote zera, pede ao servidor o estado oficial.
watch(remaining, (value, previous) => {
    if (!isOpen.value && value === 0 && previous > 0) {
        void load();
    }
});

// Nova sessão iniciada (ex.: check-in em outra tela): some a conta antiga.
watch(session, (current) => {
    if (current) {
        closedSession.value = null;
    }
});

function togglePanel(target: Panel): void {
    panel.value = panel.value === target ? 'none' : target;
}

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

function withSession(
    action: (current: Session) => Promise<unknown>,
    successMessage: string,
): void {
    const current = session.value;

    if (current) {
        void run(() => action(current), successMessage);
    }
}

function togglePause(): void {
    const paused = isPaused.value;

    withSession(
        (current) =>
            paused
                ? api.resumeSession(current.id)
                : api.pauseSession(current.id),
        paused ? 'Sessão retomada.' : 'Sessão pausada.',
    );
}

function extend(plan: Plan): void {
    panel.value = 'none';
    withSession(
        (current) => api.extendSession(current.id, plan.id, 'credit'),
        `+${formatDuration(plan.minutes)} adicionados.`,
    );
}

function addItem(item: NewSessionItem): void {
    const name =
        'product_id' in item
            ? (products.value?.find((product) => product.id === item.product_id)
                  ?.name ?? 'Produto')
            : item.name;

    withSession(
        (current) => api.addSessionItem(current.id, item),
        `${item.quantity}× ${name} na conta.`,
    );
}

function removeItem(itemId: number): void {
    withSession(
        (current) => api.removeSessionItem(current.id, itemId),
        'Produto removido da conta.',
    );
}

function addPrints(pages: number): void {
    withSession(
        (current) => api.addPrints(current.id, pages),
        `${pages} ${pages === 1 ? 'página lançada' : 'páginas lançadas'}.`,
    );
}

function removePrint(entryId: number): void {
    withSession(
        (current) => api.removePrints(current.id, entryId),
        'Impressão removida da conta.',
    );
}

async function endSession(): Promise<void> {
    const current = session.value;

    if (!current) {
        return;
    }

    panel.value = 'none';
    isBusy.value = true;

    try {
        const response = await api.endSession(current.id);
        syncServerClock(response.server_time);
        closedSession.value = response.data;
        await load();
    } catch (error) {
        show('error', errorMessage(error));
    } finally {
        isBusy.value = false;
    }
}

const closedTotals = computed(() =>
    closedSession.value ? billTotals(closedSession.value, now.value) : null,
);
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
                <div class="flex items-center gap-2">
                    <span
                        v-if="isOpen"
                        class="text-neon bg-neon/10 ring-neon/40 inline-flex items-center gap-1.5 px-2 py-1 text-xs font-semibold tracking-wider uppercase ring-1 ring-inset"
                    >
                        <InfinityIcon class="size-3.5" /> Tempo livre
                    </span>
                    <StatusBadge :status="status" class="text-xs" />
                </div>
            </header>

            <!-- Conta final logo após encerrar -->
            <section
                v-if="!session && closedSession && closedTotals"
                class="animate-rise grid items-start gap-8 lg:grid-cols-[1fr_380px]"
            >
                <div
                    class="panel pixel-corners relative flex flex-col items-center gap-6 overflow-hidden px-6 py-14 text-center"
                >
                    <PixelBlocks
                        class="absolute top-6 left-6"
                        :columns="6"
                        :rows="3"
                        :seed="31"
                    />
                    <p class="eyebrow">Sessão encerrada</p>
                    <p class="text-dim max-w-md">
                        {{
                            closedSession.billing === 'open'
                                ? `Você usou ${formatDuration(Math.max(1, Math.ceil(elapsedSeconds(closedSession, now) / 60)))} de tempo livre.`
                                : 'Obrigado por jogar com a gente.'
                        }}
                    </p>
                    <div>
                        <p class="eyebrow">
                            {{
                                closedTotals.balance > 0
                                    ? 'Pague no balcão'
                                    : 'Conta quitada'
                            }}
                        </p>
                        <p
                            class="tabular text-neon mt-2 text-6xl font-bold tracking-tight sm:text-7xl"
                            style="
                                text-shadow: 0 0 28px
                                    color-mix(
                                        in srgb,
                                        var(--color-neon) 45%,
                                        transparent
                                    );
                            "
                        >
                            {{ formatMoney(closedTotals.balance) }}
                        </p>
                    </div>
                    <p class="text-dim max-w-sm text-sm">
                        Mostre esta tela ao atendente. Pix ou dinheiro.
                    </p>
                    <div class="flex flex-wrap justify-center gap-2">
                        <a
                            v-if="station.status === 'free'"
                            :href="`#/checkin/${station.id}`"
                            class="btn btn-neon"
                        >
                            Nova sessão <ArrowRight class="size-4" />
                        </a>
                        <button
                            type="button"
                            class="btn btn-ghost"
                            @click="closedSession = null"
                        >
                            Fechar
                        </button>
                    </div>
                </div>
                <SessionBill
                    :session="closedSession"
                    :now="now"
                    title="Conta final"
                />
            </section>

            <!-- Sem sessão: tela bloqueada -->
            <section
                v-else-if="!session"
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
            <div
                v-else-if="session && timer && bill"
                class="grid items-start gap-8 lg:grid-cols-[1fr_360px]"
            >
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
                        :seconds="timer.seconds"
                        :progress="timer.progress"
                        :tone="timer.tone"
                        :caption="timer.caption"
                        :footnote="timer.footnote"
                    />

                    <!-- Custo em tempo real (tempo livre) -->
                    <div
                        v-if="isOpen"
                        class="pixel-corners bg-neon/8 ring-neon/30 flex w-full max-w-lg items-center justify-between gap-4 px-5 py-4 ring-1 ring-inset"
                        aria-live="off"
                    >
                        <div>
                            <span class="eyebrow">Tempo até agora</span>
                            <p class="tabular text-neon text-3xl font-bold">
                                {{ formatMoney(bill.time) }}
                            </p>
                        </div>
                        <div class="text-right">
                            <span class="eyebrow">Conta total</span>
                            <p class="tabular text-2xl font-semibold">
                                {{ formatMoney(bill.total) }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="grid w-full max-w-lg grid-cols-2 gap-2 sm:auto-cols-fr sm:grid-flow-col sm:gap-3"
                    >
                        <button
                            v-if="canPause"
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
                            v-if="!isOpen"
                            type="button"
                            class="btn btn-sky h-16 flex-col gap-1 px-2 text-xs"
                            :disabled="isBusy"
                            :aria-expanded="panel === 'extend'"
                            aria-controls="action-panel"
                            @click="togglePanel('extend')"
                        >
                            <Plus class="size-5" />
                            Tempo
                        </button>
                        <button
                            type="button"
                            class="btn btn-ghost h-16 flex-col gap-1 px-2 text-xs"
                            :class="{
                                'text-neon ring-neon': panel === 'products',
                            }"
                            :disabled="isBusy"
                            :aria-expanded="panel === 'products'"
                            aria-controls="action-panel"
                            @click="togglePanel('products')"
                        >
                            <CupSoda class="size-5" />
                            Produto
                        </button>
                        <button
                            type="button"
                            class="btn btn-ghost h-16 flex-col gap-1 px-2 text-xs"
                            :class="{ 'text-sky ring-sky': panel === 'prints' }"
                            :disabled="isBusy"
                            :aria-expanded="panel === 'prints'"
                            aria-controls="action-panel"
                            @click="togglePanel('prints')"
                        >
                            <Printer class="size-5" />
                            Impressão
                        </button>
                        <button
                            type="button"
                            class="btn btn-danger h-16 flex-col gap-1 px-2 text-xs max-sm:odd:last:col-span-2"
                            :disabled="isBusy"
                            @click="panel = 'end'"
                        >
                            <Power class="size-5" />
                            {{ isOpen ? 'Encerrar e pagar' : 'Encerrar' }}
                        </button>
                    </div>

                    <div
                        v-if="panel !== 'none' && panel !== 'end'"
                        id="action-panel"
                        class="animate-rise w-full max-w-lg"
                    >
                        <div class="mb-3 flex items-center justify-between">
                            <p class="eyebrow">
                                {{
                                    panel === 'extend'
                                        ? 'Adicionar tempo · pagar com crédito'
                                        : panel === 'products'
                                          ? 'Adicionar produto à conta'
                                          : 'Contador de impressões'
                                }}
                            </p>
                            <button
                                type="button"
                                class="text-dim hover:text-ink"
                                aria-label="Fechar"
                                @click="panel = 'none'"
                            >
                                <X class="size-4" />
                            </button>
                        </div>

                        <div
                            v-if="panel === 'extend'"
                            class="grid grid-cols-2 gap-2"
                        >
                            <button
                                v-for="plan in packagePlans"
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

                        <template v-else-if="panel === 'products'">
                            <p
                                v-if="productsError"
                                role="alert"
                                class="text-amber mb-3 text-xs"
                            >
                                Catálogo indisponível ({{ productsError }}).
                                Lance com preço manual.
                            </p>
                            <div
                                v-if="products === null"
                                class="grid grid-cols-3 gap-2"
                            >
                                <div
                                    v-for="index in 6"
                                    :key="index"
                                    class="bg-panel-2 h-16 animate-pulse"
                                />
                            </div>
                            <ProductPicker
                                v-else
                                :products="products"
                                :busy="isBusy"
                                :is-demo="api.isDemo"
                                @add="addItem"
                            />
                        </template>

                        <PrintCounter
                            v-else
                            :session="session"
                            :busy="isBusy"
                            :is-demo="api.isDemo"
                            @add="addPrints"
                        />
                    </div>

                    <!-- Confirmação de encerramento -->
                    <div
                        v-if="panel === 'end'"
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
                        <p
                            class="mt-3 flex items-baseline justify-between text-sm"
                        >
                            <span>
                                {{
                                    isOpen
                                        ? 'Conta até agora (prévia)'
                                        : 'A pagar no balcão'
                                }}
                            </span>
                            <span class="tabular text-ink text-xl font-bold">
                                {{
                                    formatMoney(
                                        isOpen ? bill.total : bill.balance,
                                    )
                                }}
                            </span>
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
                                @click="panel = 'none'"
                            >
                                Continuar
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
                    <SessionBill
                        :session="session"
                        :now="now"
                        editable
                        :busy="isBusy"
                        @remove-item="removeItem"
                        @remove-print="removePrint"
                    />

                    <article
                        v-if="!isOpen"
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
                                    {{
                                        currentPlan?.name ??
                                        (isOpen ? 'Tempo livre' : '—')
                                    }}
                                </dd>
                            </div>
                            <div
                                v-if="!isOpen"
                                class="flex justify-between gap-2"
                            >
                                <dt class="text-dim">Tempo comprado</dt>
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

            <!-- Barrinha de tempo e valor, sempre visível (WIL-51) -->
            <SessionTicker
                v-if="session && session.status !== 'ended'"
                :session="session"
                :now="now"
                class="fixed right-3 bottom-3 z-40"
            />
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
