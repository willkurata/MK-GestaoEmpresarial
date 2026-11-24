<script setup lang="ts">
import { api, createTerminalApi, errorMessage } from '@cc/api';
import type { CustomerProfile, TerminalConfig } from '@cc/api/terminal';
import {
    isTerminalConfigured,
    readTerminalConfig,
    saveTerminalConfig,
} from '@cc/api/terminal';
import BrandMark from '@cc/components/BrandMark.vue';
import FlashMessage from '@cc/components/FlashMessage.vue';
import PixelBlocks from '@cc/components/PixelBlocks.vue';
import TimerRing from '@cc/components/TimerRing.vue';
import { useFlash } from '@cc/composables/useFlash';
import { usePolling } from '@cc/composables/usePolling';
import {
    remainingSeconds,
    syncServerClock,
    useServerNow,
} from '@cc/composables/useServerClock';
import { billTotals, elapsedSeconds } from '@cc/lib/billing';
import { formatMoney, formatRate } from '@cc/lib/format';
import type { LockReason, TerminalSnapshot } from '@cc/lib/terminal';
import { lockWarning, terminalState } from '@cc/lib/terminal';
import { navigate } from '@cc/router';
import type { Station } from '@cc/types';
import {
    Lock,
    LogIn,
    LogOut,
    Maximize,
    TriangleAlert,
    Wallet,
    WifiOff,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';

/**
 * Programa cliente da máquina (WIL-49), em tela cheia no PC da estação.
 *
 * - Bloqueada: sem sessão, pacote acabado ou manutenção. O cliente pode
 *   entrar com telefone e senha para ver o saldo; quem libera a máquina é o
 *   check-in no balcão.
 * - Liberada: timer do tempo usado (tempo livre) ou restante (pacote), conta
 *   em tempo real e saldo. Bloqueia sozinha quando o pacote zera.
 *
 * O servidor decide (`agent/heartbeat` a cada 5 s); o relógio local só
 * interpola entre um heartbeat e outro.
 */
const props = defineProps<{ setup: boolean }>();

const HEARTBEAT_MS = 5000;
/** Saldo do cliente: a cada 6 heartbeats (~30 s). */
const PROFILE_EVERY_TICKS = 6;

const config = ref<TerminalConfig | null>(readTerminalConfig());
const isConfigured = computed(() =>
    isTerminalConfigured(config.value, api.isDemo),
);
const terminal = computed(() =>
    isConfigured.value && config.value ? createTerminalApi(config.value) : null,
);

const now = useServerNow();
const { flash, show, dismiss } = useFlash();

const snapshot = ref<TerminalSnapshot | null>(null);
const connectionError = ref<string | null>(null);
const customer = ref<CustomerProfile | null>(null);
let ticks = 0;

const state = computed(() =>
    snapshot.value ? terminalState(snapshot.value, now.value) : null,
);
const activeSession = computed(() =>
    state.value && !state.value.locked ? state.value.session : null,
);
const isOpen = computed(() => activeSession.value?.billing === 'open');
const bill = computed(() =>
    activeSession.value ? billTotals(activeSession.value, now.value) : null,
);
const warning = computed(() =>
    activeSession.value ? lockWarning(activeSession.value, now.value) : null,
);
const creditCents = computed(
    () =>
        customer.value?.credit_cents ??
        (activeSession.value?.customer_name
            ? activeSession.value.credit_cents
            : null),
);

const timer = computed(() => {
    const current = activeSession.value;

    if (!current) {
        return null;
    }

    if (current.billing === 'open') {
        const elapsed = elapsedSeconds(current, now.value);

        return {
            seconds: elapsed,
            progress: (elapsed % 3600) / 3600,
            tone: 'neon' as const,
            caption: 'Tempo usado',
            footnote:
                current.hourly_rate_cents !== null
                    ? `Tempo livre · ${formatRate(current.hourly_rate_cents)}`
                    : 'Tempo livre',
        };
    }

    const remaining = remainingSeconds(current, now.value);
    const progress =
        current.total_seconds > 0 ? remaining / current.total_seconds : 0;

    return {
        seconds: remaining,
        progress,
        tone: warning.value ? ('amber' as const) : ('neon' as const),
        caption: 'Tempo restante',
        footnote: `Usado: ${Math.floor(elapsedSeconds(current, now.value) / 60)} min`,
    };
});

const LOCK_COPY: Record<LockReason, { title: string; hint: string }> = {
    no_session: {
        title: 'Máquina bloqueada',
        hint: 'Faça o check-in no balcão para liberar esta máquina.',
    },
    time_up: {
        title: 'Seu tempo acabou',
        hint: 'Adicione mais tempo no balcão para continuar.',
    },
    maintenance: {
        title: 'Em manutenção',
        hint: 'Esta máquina está fora de uso. Fale com o atendente.',
    },
};

const lockCopy = computed(() =>
    state.value?.locked ? LOCK_COPY[state.value.reason] : LOCK_COPY.no_session,
);

const clockFormatter = new Intl.DateTimeFormat('pt-BR', {
    hour: '2-digit',
    minute: '2-digit',
});
const wallClock = computed(() => clockFormatter.format(new Date(now.value)));

async function refreshProfile(): Promise<void> {
    if (!terminal.value || !customer.value) {
        return;
    }

    try {
        customer.value = await terminal.value.customerProfile();
    } catch {
        customer.value = null;
    }
}

async function heartbeat(): Promise<void> {
    if (!terminal.value || props.setup) {
        return;
    }

    try {
        const response = await terminal.value.heartbeat();
        syncServerClock(response.server_time);
        snapshot.value = response;
        connectionError.value = null;
    } catch (error) {
        // Sem rede, mantém o último estado: o pacote continua contando e
        // bloqueia no horário previsto mesmo offline.
        connectionError.value = errorMessage(error);
    }

    ticks += 1;

    if (ticks % PROFILE_EVERY_TICKS === 0) {
        await refreshProfile();
    }
}

usePolling(heartbeat, HEARTBEAT_MS);

watch(terminal, () => {
    snapshot.value = null;
    customer.value = null;
    void heartbeat();
});

/** Máquina compartilhada: ao bloquear depois do uso, desconecta o cliente. */
watch(
    () => state.value?.locked,
    (locked, wasLocked) => {
        if (locked && wasLocked === false) {
            void logout();
        }
    },
);

/* ---------------------------- Login do cliente --------------------------- */

const phone = ref('');
const password = ref('');
const isLoggingIn = ref(false);

async function login(): Promise<void> {
    if (!terminal.value || isLoggingIn.value) {
        return;
    }

    isLoggingIn.value = true;

    try {
        customer.value = await terminal.value.customerLogin(
            phone.value,
            password.value,
        );
        password.value = '';
        dismiss();
    } catch (error) {
        show('error', errorMessage(error));
    } finally {
        isLoggingIn.value = false;
    }
}

async function logout(): Promise<void> {
    const current = terminal.value;
    customer.value = null;
    phone.value = '';
    password.value = '';

    if (current) {
        await current.customerLogout();
    }
}

/* ------------------------------ Configuração ----------------------------- */

const setupStoreCode = ref(config.value?.store_code ?? '');
const setupAgentToken = ref('');
const setupStationId = ref<number | null>(config.value?.station_id ?? null);
const demoStations = ref<Station[]>([]);

watch(
    () => props.setup || !isConfigured.value,
    async (needsSetup) => {
        if (needsSetup && api.isDemo && demoStations.value.length === 0) {
            try {
                demoStations.value = (await api.listStations()).data;
            } catch (error) {
                show('error', errorMessage(error));
            }
        }
    },
    { immediate: true },
);

function saveSetup(): void {
    const next: TerminalConfig = {
        store_code: setupStoreCode.value.trim(),
        agent_token:
            setupAgentToken.value.trim() || config.value?.agent_token || '',
        station_id: setupStationId.value,
    };

    if (!isTerminalConfigured(next, api.isDemo)) {
        show(
            'error',
            api.isDemo
                ? 'Escolha a máquina deste PC.'
                : 'Informe o código da loja e o token da estação.',
        );

        return;
    }

    saveTerminalConfig(next);
    config.value = next;
    setupAgentToken.value = '';
    enterFullscreen();
    navigate('/terminal');
}

function enterFullscreen(): void {
    void document.documentElement.requestFullscreen?.().catch(() => undefined);
}
</script>

<template>
    <div class="bg-void text-ink relative min-h-dvh overflow-hidden">
        <!-- Configuração do PC da estação -->
        <section
            v-if="setup || !isConfigured"
            class="mx-auto flex min-h-dvh max-w-md flex-col justify-center gap-6 px-4 py-12"
        >
            <BrandMark />
            <div>
                <p class="eyebrow">Programa cliente</p>
                <h1 class="mt-2 text-3xl font-bold">Configurar esta máquina</h1>
                <p class="text-dim mt-2 text-sm">
                    {{
                        api.isDemo
                            ? 'Modo demonstração: escolha qual máquina este navegador representa.'
                            : 'O gerente gera o token da estação no painel. Ele fica salvo só neste PC.'
                    }}
                </p>
            </div>

            <FlashMessage
                v-if="flash"
                :tone="flash.tone"
                :message="flash.message"
                @dismiss="dismiss"
            />

            <form class="flex flex-col gap-4" @submit.prevent="saveSetup">
                <label v-if="api.isDemo" class="flex flex-col gap-1.5">
                    <span class="eyebrow">Máquina</span>
                    <select
                        v-model="setupStationId"
                        class="bg-void text-ink ring-line-strong focus:ring-neon h-11 border-0 px-3 text-sm ring-1 outline-none ring-inset focus:ring-2"
                    >
                        <option :value="null" disabled>Escolha…</option>
                        <option
                            v-for="station in demoStations"
                            :key="station.id"
                            :value="station.id"
                        >
                            {{ station.name }}
                        </option>
                    </select>
                </label>
                <template v-else>
                    <label class="flex flex-col gap-1.5">
                        <span class="eyebrow">Código da loja</span>
                        <input
                            v-model="setupStoreCode"
                            type="text"
                            required
                            maxlength="40"
                            autocomplete="off"
                            class="bg-void text-ink ring-line-strong focus:ring-neon h-11 border-0 px-3 text-sm ring-1 outline-none ring-inset focus:ring-2"
                        />
                    </label>
                    <label class="flex flex-col gap-1.5">
                        <span class="eyebrow">Token da estação</span>
                        <input
                            v-model="setupAgentToken"
                            type="password"
                            :required="!config?.agent_token"
                            autocomplete="off"
                            :placeholder="
                                config?.agent_token
                                    ? 'Deixe vazio para manter o atual'
                                    : ''
                            "
                            class="bg-void text-ink ring-line-strong placeholder:text-dim/70 focus:ring-neon h-11 border-0 px-3 text-sm ring-1 outline-none ring-inset focus:ring-2"
                        />
                    </label>
                </template>
                <button type="submit" class="btn btn-neon h-12">
                    Salvar e abrir o terminal
                </button>
            </form>
        </section>

        <!-- Máquina bloqueada -->
        <section
            v-else-if="!state || state.locked"
            class="flex min-h-dvh flex-col items-center justify-center gap-10 px-4 py-12 text-center"
            aria-live="polite"
        >
            <PixelBlocks
                class="absolute top-8 left-8"
                :columns="8"
                :rows="3"
                :seed="5"
            />
            <PixelBlocks
                class="absolute right-8 bottom-8 rotate-180"
                :columns="8"
                :rows="3"
                :seed="9"
            />

            <div class="flex flex-col items-center gap-3">
                <Lock class="text-dim size-8" aria-hidden="true" />
                <p class="eyebrow">
                    {{ state ? lockCopy.title : 'Conectando ao servidor…' }}
                </p>
                <p
                    class="tabular text-7xl font-bold tracking-tight sm:text-8xl"
                >
                    {{ wallClock }}
                </p>
                <p class="text-2xl font-semibold">
                    {{ snapshot?.station.name ?? '—' }}
                </p>
                <p v-if="state" class="text-dim max-w-md">
                    {{ lockCopy.hint }}
                </p>
            </div>

            <!-- Cliente identificado: saldo -->
            <article
                v-if="customer"
                class="panel pixel-corners flex w-full max-w-sm flex-col gap-4 p-6 text-left"
            >
                <div class="flex items-center justify-between">
                    <span class="eyebrow">Olá, {{ customer.name }}</span>
                    <Wallet class="text-sky size-4" />
                </div>
                <div>
                    <span class="text-dim text-xs">Saldo de créditos</span>
                    <p class="tabular text-sky text-4xl font-bold">
                        {{ formatMoney(customer.credit_cents) }}
                    </p>
                </div>
                <p class="text-dim text-xs">
                    Use seu saldo no check-in do balcão ou recarregue por Pix ou
                    dinheiro.
                </p>
                <button
                    type="button"
                    class="btn btn-ghost h-11"
                    @click="logout"
                >
                    <LogOut class="size-4" /> Sair
                </button>
            </article>

            <!-- Login do cliente -->
            <form
                v-else-if="state && state.reason !== 'maintenance'"
                class="panel pixel-corners flex w-full max-w-sm flex-col gap-4 p-6 text-left"
                @submit.prevent="login"
            >
                <span class="eyebrow">Entrar na sua conta</span>
                <FlashMessage
                    v-if="flash"
                    :tone="flash.tone"
                    :message="flash.message"
                    @dismiss="dismiss"
                />
                <label class="flex flex-col gap-1.5">
                    <span class="text-dim text-xs">Telefone</span>
                    <input
                        v-model="phone"
                        type="tel"
                        inputmode="tel"
                        required
                        maxlength="20"
                        autocomplete="off"
                        placeholder="(11) 91234-5678"
                        class="bg-void text-ink ring-line-strong placeholder:text-dim/70 focus:ring-neon h-11 border-0 px-3 text-sm ring-1 outline-none ring-inset focus:ring-2"
                    />
                </label>
                <label class="flex flex-col gap-1.5">
                    <span class="text-dim text-xs">Senha</span>
                    <input
                        v-model="password"
                        type="password"
                        required
                        autocomplete="off"
                        class="bg-void text-ink ring-line-strong focus:ring-neon h-11 border-0 px-3 text-sm ring-1 outline-none ring-inset focus:ring-2"
                    />
                </label>
                <button
                    type="submit"
                    class="btn btn-neon h-12"
                    :disabled="isLoggingIn"
                >
                    <LogIn class="size-4" />
                    {{ isLoggingIn ? 'Entrando…' : 'Ver meu saldo' }}
                </button>
                <p v-if="api.isDemo" class="text-dim text-xs">
                    Demonstração: qualquer telefone com DDD e senha entram.
                </p>
            </form>
        </section>

        <!-- Máquina liberada -->
        <section
            v-else-if="activeSession && timer && bill"
            class="mx-auto flex min-h-dvh max-w-3xl flex-col gap-8 px-4 py-8"
        >
            <header class="flex items-center justify-between gap-4">
                <BrandMark />
                <div class="text-right">
                    <p class="font-semibold">{{ snapshot?.station.name }}</p>
                    <p class="text-dim text-xs">
                        {{
                            customer?.name ??
                            activeSession.customer_name ??
                            'Cliente avulso'
                        }}
                    </p>
                </div>
            </header>

            <div
                v-if="warning"
                role="alert"
                class="pixel-corners bg-amber/12 text-amber ring-amber/40 flex items-center gap-3 px-4 py-3 text-sm ring-1 ring-inset"
            >
                <TriangleAlert class="size-4 shrink-0" />
                <span class="text-ink flex-1">
                    {{
                        warning === 'one_minute'
                            ? 'Falta menos de 1 minuto. Salve seus arquivos: a máquina vai bloquear.'
                            : 'Faltam 5 minutos. Adicione mais tempo no balcão para não perder o progresso.'
                    }}
                </span>
            </div>

            <div
                class="panel pixel-corners flex flex-col items-center gap-8 px-4 py-10"
            >
                <TimerRing
                    :seconds="timer.seconds"
                    :progress="timer.progress"
                    :tone="timer.tone"
                    :caption="timer.caption"
                    :footnote="timer.footnote"
                />

                <dl class="grid w-full max-w-lg grid-cols-2 gap-3">
                    <div
                        class="pixel-corners bg-neon/8 ring-neon/30 px-5 py-4 ring-1 ring-inset"
                    >
                        <dt class="eyebrow">
                            {{ isOpen ? 'Tempo até agora' : 'Tempo pago' }}
                        </dt>
                        <dd class="tabular text-neon text-3xl font-bold">
                            {{ formatMoney(bill.time) }}
                        </dd>
                    </div>
                    <div
                        class="pixel-corners bg-panel-2 ring-line px-5 py-4 ring-1 ring-inset"
                    >
                        <dt class="eyebrow">A pagar</dt>
                        <dd class="tabular text-3xl font-bold">
                            {{ formatMoney(bill.balance) }}
                        </dd>
                    </div>
                    <div
                        v-if="creditCents !== null"
                        class="pixel-corners bg-sky/8 ring-sky/30 col-span-2 flex items-center justify-between px-5 py-4 ring-1 ring-inset"
                    >
                        <dt class="eyebrow flex items-center gap-2">
                            <Wallet class="size-4" /> Saldo de créditos
                        </dt>
                        <dd class="tabular text-sky text-2xl font-bold">
                            {{ formatMoney(creditCents) }}
                        </dd>
                    </div>
                </dl>

                <p class="text-dim max-w-md text-center text-sm">
                    {{
                        isOpen
                            ? 'Tempo livre: você paga só os minutos usados. Para encerrar, chame o atendente no balcão.'
                            : 'Quando o tempo acabar, a máquina bloqueia sozinha.'
                    }}
                </p>
            </div>
        </section>

        <!-- Rodapé: conexão e tela cheia -->
        <div
            v-if="!setup && isConfigured"
            class="absolute top-3 right-3 flex items-center gap-2"
        >
            <span
                v-if="connectionError"
                role="status"
                class="pixel-corners bg-danger/12 text-danger flex items-center gap-1.5 px-2.5 py-1 text-xs"
                :title="connectionError"
            >
                <WifiOff class="size-3.5" /> Sem conexão
            </span>
            <span
                v-if="api.isDemo"
                class="pixel-corners bg-amber/15 text-amber px-2.5 py-1 text-[11px] font-semibold tracking-widest uppercase"
            >
                Demonstração
            </span>
            <button
                type="button"
                class="text-dim hover:text-ink p-1.5"
                aria-label="Tela cheia"
                @click="enterFullscreen"
            >
                <Maximize class="size-4" />
            </button>
        </div>
    </div>
</template>
