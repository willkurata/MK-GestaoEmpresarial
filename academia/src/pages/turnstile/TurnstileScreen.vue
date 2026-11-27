<script setup lang="ts">
import {
    CircleCheck,
    CircleX,
    DoorOpen,
    Flame,
    LogOut,
    QrCode,
    ScanFace,
    Settings,
    Users,
    Wifi,
    WifiOff,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import CountdownRing from '../../components/CountdownRing.vue';
import DemoBadge from '../../components/DemoBadge.vue';
import { useCountdown } from '../../composables/useCountdown';
import { useNow } from '../../composables/useNow';
import { publicDenialMessage } from '../../lib/access';
import { firstName, formatTime, initials } from '../../lib/format';
import type { AccessAttempt } from '../../lib/store';
import { registerAccess, state } from '../../lib/store';
import { weeklyStreak } from '../../lib/training';
import type { AccessDirection, IdentificationMethod } from '../../lib/types';

/**
 * Tela da catraca (tablet/mini-PC em modo kiosk ao lado do equipamento).
 * Fluxo: espera → identificação → liberado com timer (ou negado discreto)
 * → volta para espera quando o tempo acaba.
 */
const grantSeconds = ref(15);
const denySeconds = 6;
const direction = ref<AccessDirection>('entrada');
const showSettings = ref(false);
const lastAttempt = ref<AccessAttempt | null>(null);
const timedOut = ref(false);

const now = useNow(1000);

const countdown = useCountdown(() => {
    timedOut.value = lastAttempt.value?.decision.allowed ?? false;
    lastAttempt.value = null;
    window.setTimeout(() => (timedOut.value = false), 2500);
});

const phase = computed<'idle' | 'granted' | 'denied'>(() => {
    if (lastAttempt.value === null) {
        return 'idle';
    }

    return lastAttempt.value.decision.allowed ? 'granted' : 'denied';
});

const denialMessage = computed(() => {
    const decision = lastAttempt.value?.decision;

    return decision && !decision.allowed
        ? publicDenialMessage(decision.reason)
        : '';
});

const occupancyRatio = computed(() => state.occupancy / state.gym.capacity);

const nextClass = computed(
    () =>
        state.classes
            .filter(
                (gymClass) =>
                    new Date(gymClass.startsAt).getTime() > now.value.getTime(),
            )
            .sort((a, b) => a.startsAt.localeCompare(b.startsAt))[0],
);

const streak = computed(() => weeklyStreak(state.checkInHistory, now.value, 3));

function identify(
    studentId: string | null,
    method: IdentificationMethod,
): void {
    const attempt = registerAccess(studentId, method, direction.value);
    lastAttempt.value = attempt;
    timedOut.value = false;
    countdown.start(
        attempt.decision.allowed ? grantSeconds.value : denySeconds,
    );
}

function passedThrough(): void {
    countdown.stop();
    lastAttempt.value = null;
}

/** Atalhos de teclado para o operador testar sem hardware. */
function onKeydown(event: KeyboardEvent): void {
    const actions: Record<string, () => void> = {
        '1': () => identify('s1', 'facial'),
        '2': () => identify('s2', 'qr'),
        '3': () => identify('s3', 'rfid'),
        '4': () => identify(null, 'facial'),
        ' ': passedThrough,
    };

    if (actions[event.key] && !(event.target instanceof HTMLInputElement)) {
        event.preventDefault();
        actions[event.key]!();
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

const simulations: {
    label: string;
    studentId: string | null;
    method: IdentificationMethod;
    key: string;
}[] = [
    {
        label: 'Aluno em dia (facial)',
        studentId: 's1',
        method: 'facial',
        key: '1',
    },
    {
        label: 'Mensalidade atrasada (QR)',
        studentId: 's2',
        method: 'qr',
        key: '2',
    },
    { label: 'Plano diurno (RFID)', studentId: 's3', method: 'rfid', key: '3' },
    {
        label: 'Rosto desconhecido',
        studentId: null,
        method: 'facial',
        key: '4',
    },
];
</script>

<template>
    <div
        class="relative flex min-h-dvh flex-col overflow-hidden transition-colors duration-500"
        :class="{
            'bg-ink-950': phase === 'idle',
            'bg-[#0f1a0a]': phase === 'granted',
            'bg-[#1c0f11]': phase === 'denied',
        }"
    >
        <!-- Barra de status do equipamento -->
        <header
            class="flex items-center justify-between gap-4 px-6 py-4 text-sm sm:px-10"
        >
            <div class="flex items-center gap-3">
                <span class="font-display text-base font-bold tracking-wide">{{
                    state.gym.name
                }}</span>
                <span
                    class="flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                    :class="
                        direction === 'entrada'
                            ? 'bg-ink-800 text-ink-100'
                            : 'bg-ink-800 text-warn'
                    "
                >
                    <DoorOpen v-if="direction === 'entrada'" class="size-3.5" />
                    <LogOut v-else class="size-3.5" />
                    Catraca de {{ direction }}
                </span>
            </div>
            <div class="flex items-center gap-3">
                <span
                    class="flex items-center gap-1.5 text-xs"
                    :class="state.gatewayOnline ? 'text-ink-300' : 'text-warn'"
                >
                    <Wifi v-if="state.gatewayOnline" class="size-4" />
                    <WifiOff v-else class="size-4" />
                    {{
                        state.gatewayOnline
                            ? 'Online'
                            : 'Offline · liberando pela lista local'
                    }}
                </span>
                <DemoBadge />
                <button
                    type="button"
                    class="text-ink-400 hover:bg-ink-800 hover:text-ink-100 rounded-full p-2"
                    aria-label="Configurações da catraca"
                    @click="showSettings = !showSettings"
                >
                    <Settings class="size-5" />
                </button>
            </div>
        </header>

        <!-- Conteúdo principal -->
        <main
            class="flex flex-1 flex-col items-center justify-center px-6 text-center"
            aria-live="assertive"
        >
            <!-- ESPERA -->
            <div
                v-if="phase === 'idle'"
                class="animate-fade-up flex flex-col items-center"
            >
                <p class="font-display tabular text-7xl font-bold sm:text-8xl">
                    {{
                        now.toLocaleTimeString('pt-BR', {
                            hour: '2-digit',
                            minute: '2-digit',
                        })
                    }}
                </p>
                <div class="text-ink-300 mt-10 flex items-center gap-6">
                    <ScanFace
                        class="size-16 text-lime-400"
                        stroke-width="1.5"
                    />
                    <span class="text-ink-600 text-2xl">ou</span>
                    <QrCode class="size-16 text-lime-400" stroke-width="1.5" />
                </div>
                <p class="font-display mt-6 text-3xl font-semibold sm:text-4xl">
                    {{
                        direction === 'entrada'
                            ? 'Aproxime o rosto ou o QR do app'
                            : 'Até a próxima! Aproxime para sair'
                    }}
                </p>
                <p v-if="timedOut" class="text-warn mt-4 text-lg">
                    Tempo esgotado — a catraca travou novamente.
                </p>
                <p v-else class="text-ink-400 mt-4 text-lg">
                    Também aceita cartão ou pulseira
                </p>
            </div>

            <!-- LIBERADO -->
            <div
                v-else-if="phase === 'granted' && lastAttempt"
                class="animate-pop flex flex-col items-center"
            >
                <CountdownRing
                    :progress="countdown.progress.value"
                    :seconds="countdown.secondsLeft.value"
                    :size="300"
                >
                    <div class="flex flex-col items-center">
                        <span
                            class="font-display text-ink-950 grid size-24 place-items-center rounded-full bg-lime-400 text-4xl font-bold"
                            aria-hidden="true"
                        >
                            {{ initials(lastAttempt.student?.name ?? '') }}
                        </span>
                        <span
                            class="font-display tabular mt-3 text-5xl font-bold"
                            >{{ countdown.secondsLeft.value }}s</span
                        >
                    </div>
                </CountdownRing>
                <p
                    class="font-display mt-8 flex items-center gap-3 text-4xl font-bold text-lime-400 sm:text-5xl"
                >
                    <CircleCheck class="size-10" />
                    Acesso liberado
                </p>
                <p class="mt-3 text-3xl font-semibold">
                    {{ direction === 'entrada' ? 'Bom treino' : 'Até logo' }},
                    {{ firstName(lastAttempt.student?.name ?? '') }}!
                </p>
                <p
                    v-if="
                        direction === 'entrada' &&
                        lastAttempt.student?.id === state.currentStudentId &&
                        streak > 0
                    "
                    class="text-ink-300 mt-4 flex items-center gap-2 text-lg"
                >
                    <Flame class="text-warn size-5" /> {{ streak }} semanas
                    seguidas batendo a meta
                </p>
                <p class="text-ink-400 mt-2 text-base">
                    Passe pela catraca antes do tempo acabar
                </p>
            </div>

            <!-- NEGADO (discreto, sem expor dívida) -->
            <div
                v-else-if="phase === 'denied'"
                class="animate-pop flex flex-col items-center"
            >
                <CountdownRing
                    :progress="countdown.progress.value"
                    :seconds="countdown.secondsLeft.value"
                    :size="200"
                    :stroke="10"
                    tone="danger"
                >
                    <CircleX class="text-danger size-20" stroke-width="1.5" />
                </CountdownRing>
                <p class="font-display mt-8 text-4xl font-bold sm:text-5xl">
                    {{
                        lastAttempt?.student
                            ? `Olá, ${firstName(lastAttempt.student.name)}`
                            : 'Não foi possível liberar'
                    }}
                </p>
                <p class="text-ink-300 mt-4 max-w-xl text-2xl">
                    {{ denialMessage }}
                </p>
            </div>
        </main>

        <!-- Rodapé informativo -->
        <footer class="grid gap-3 px-6 pb-6 sm:grid-cols-2 sm:px-10">
            <div class="bg-ink-900/70 flex items-center gap-4 rounded-2xl p-4">
                <Users class="text-ink-400 size-7" />
                <div class="flex-1 text-left">
                    <div class="flex items-baseline justify-between">
                        <span class="text-ink-400 text-sm"
                            >Na academia agora</span
                        >
                        <span class="font-display tabular text-2xl font-bold"
                            >{{ state.occupancy
                            }}<span class="text-ink-400 text-base"
                                >/{{ state.gym.capacity }}</span
                            ></span
                        >
                    </div>
                    <div class="bg-ink-700 mt-2 h-1.5 rounded-full">
                        <div
                            class="h-full rounded-full transition-[width] duration-700"
                            :class="
                                occupancyRatio > 0.85
                                    ? 'bg-warn'
                                    : 'bg-lime-400'
                            "
                            :style="{
                                width: `${Math.min(100, occupancyRatio * 100)}%`,
                            }"
                        />
                    </div>
                </div>
            </div>
            <div
                v-if="nextClass"
                class="bg-ink-900/70 flex items-center gap-4 rounded-2xl p-4 text-left"
            >
                <span
                    class="font-display tabular text-2xl font-bold text-lime-400"
                    >{{ formatTime(nextClass.startsAt) }}</span
                >
                <div>
                    <p class="font-semibold">
                        Próxima aula: {{ nextClass.name }}
                    </p>
                    <p class="text-ink-400 text-sm">
                        {{ nextClass.room }} · {{ nextClass.coach }}
                    </p>
                </div>
            </div>
        </footer>

        <!-- Painel do operador (configuração + simulação sem hardware) -->
        <aside
            v-if="showSettings"
            class="animate-fade-up border-ink-700 bg-ink-900 absolute top-16 right-4 z-20 w-[min(22rem,calc(100vw-2rem))] rounded-2xl border p-5 text-left shadow-2xl"
            aria-label="Configurações da catraca"
        >
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-display font-bold">Configurar catraca</h2>
                <button
                    type="button"
                    class="text-ink-400 hover:text-ink-100 rounded-full p-1"
                    aria-label="Fechar"
                    @click="showSettings = false"
                >
                    <X class="size-5" />
                </button>
            </div>

            <label class="text-ink-300 block text-sm" for="grant-seconds">
                Tempo para passar:
                <strong class="text-ink-100 tabular"
                    >{{ grantSeconds }}s</strong
                >
            </label>
            <input
                id="grant-seconds"
                v-model.number="grantSeconds"
                type="range"
                min="5"
                max="60"
                step="5"
                class="mt-2 w-full accent-lime-400"
            />

            <fieldset class="mt-4">
                <legend class="text-ink-300 text-sm">
                    Sentido deste equipamento
                </legend>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <button
                        v-for="option in ['entrada', 'saida'] as const"
                        :key="option"
                        type="button"
                        class="rounded-xl border px-3 py-2 text-sm font-medium capitalize"
                        :class="
                            direction === option
                                ? 'border-lime-400 bg-lime-400/10 text-lime-400'
                                : 'border-ink-700 text-ink-300'
                        "
                        :aria-pressed="direction === option"
                        @click="direction = option"
                    >
                        {{ option === 'saida' ? 'Saída' : 'Entrada' }}
                    </button>
                </div>
            </fieldset>

            <label
                class="text-ink-300 mt-4 flex items-center justify-between text-sm"
            >
                Gateway online
                <input
                    v-model="state.gatewayOnline"
                    type="checkbox"
                    class="size-5 accent-lime-400"
                />
            </label>

            <div class="border-ink-700 mt-5 border-t pt-4">
                <p
                    class="text-ink-400 mb-2 text-xs font-semibold tracking-wider uppercase"
                >
                    Simular identificação
                </p>
                <div class="grid gap-2">
                    <button
                        v-for="simulation in simulations"
                        :key="simulation.key"
                        type="button"
                        class="bg-ink-800 hover:bg-ink-700 flex items-center justify-between rounded-xl px-3 py-2.5 text-left text-sm"
                        @click="
                            identify(simulation.studentId, simulation.method)
                        "
                    >
                        {{ simulation.label }}
                        <kbd
                            class="bg-ink-950 text-ink-400 rounded px-1.5 text-xs"
                            >{{ simulation.key }}</kbd
                        >
                    </button>
                    <button
                        type="button"
                        class="bg-ink-800 hover:bg-ink-700 flex items-center justify-between rounded-xl px-3 py-2.5 text-left text-sm"
                        @click="passedThrough"
                    >
                        Aluno passou pela catraca
                        <kbd
                            class="bg-ink-950 text-ink-400 rounded px-1.5 text-xs"
                            >espaço</kbd
                        >
                    </button>
                </div>
            </div>
        </aside>
    </div>
</template>
