<script setup lang="ts">
import {
    CircleCheck,
    LoaderCircle,
    Printer,
    ScanLine,
    Smartphone,
    TriangleAlert,
    WifiOff,
} from '@lucide/vue';
import { api, errorMessage } from '@totem/api';
import BrandMark from '@totem/components/BrandMark.vue';
import QrCode from '@totem/components/QrCode.vue';
import { useNow } from '@totem/composables/useNow';
import { usePolling } from '@totem/composables/usePolling';
import { formatCountdown, formatMoney, secondsUntil } from '@totem/lib/format';
import { summarizePrinter } from '@totem/lib/printer';
import type { TotemDisplay } from '@totem/types';
import { computed, ref } from 'vue';

/**
 * Tela do totem: roda em Chromium `--kiosk`, sem toque. Só exibe:
 * QR rotativo, passo a passo, fila por código, impressora, preços e ajuda.
 */
const props = defineProps<{ totemCode: string }>();

const display = ref<TotemDisplay | null>(null);
const offlineSince = ref<number | null>(null);
const lastError = ref<string | null>(null);
const now = useNow();

/** Códigos que ficaram prontos agora, para destacar por alguns segundos. */
const freshReady = ref(new Set<string>());
const seenReady = new Set<string>();
let firstLoad = true;

usePolling(async () => {
    try {
        const fresh = await api.getDisplay(props.totemCode);

        fresh.queue
            .filter((entry) => entry.status === 'ready')
            .forEach((entry) => {
                if (!seenReady.has(entry.pickup_code)) {
                    seenReady.add(entry.pickup_code);

                    if (!firstLoad) {
                        freshReady.value.add(entry.pickup_code);
                        window.setTimeout(
                            () => freshReady.value.delete(entry.pickup_code),
                            10_000,
                        );
                    }
                }
            });

        firstLoad = false;
        display.value = fresh;
        offlineSince.value = null;
        lastError.value = null;
    } catch (error) {
        offlineSince.value ??= Date.now();
        lastError.value = errorMessage(error);
    }
}, 3000);

const printer = computed(() =>
    display.value ? summarizePrinter(display.value.printer) : null,
);
const isOffline = computed(
    () => offlineSince.value !== null && now.value - offlineSince.value > 5000,
);
const showQr = computed(
    () =>
        !!display.value && !!printer.value?.acceptingOrders && !isOffline.value,
);

const ready = computed(
    () =>
        display.value?.queue.filter((entry) => entry.status === 'ready') ?? [],
);
const printing = computed(
    () =>
        display.value?.queue.filter((entry) => entry.status === 'printing') ??
        [],
);
const queued = computed(
    () =>
        display.value?.queue.filter((entry) => entry.status === 'queued') ?? [],
);

const qrSeconds = computed(() =>
    display.value
        ? secondsUntil(display.value.session_qr.expires_at, now.value)
        : 0,
);

const clock = computed(() =>
    new Date(now.value).toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
    }),
);

const paperLevel = computed(() => display.value?.printer.paper_level ?? null);
const tonerLevel = computed(
    () => display.value?.printer.markers[0]?.level ?? null,
);

function percentOf(done: number, total: number): number {
    return total > 0 ? Math.round((done / total) * 100) : 0;
}
</script>

<template>
    <div
        class="theme-kiosk bg-paper text-ink h-dvh cursor-none overflow-hidden font-sans"
    >
        <div
            v-if="!display"
            class="grid h-full place-items-center text-[2vw]"
            aria-busy="true"
        >
            <div class="flex flex-col items-center gap-6">
                <BrandMark size="lg" />
                <p class="text-ink-soft flex items-center gap-3">
                    <WifiOff v-if="lastError" class="size-[2vw]" />
                    <LoaderCircle v-else class="size-[2vw] animate-spin" />
                    {{
                        lastError
                            ? 'Sem conexão. Tentando de novo…'
                            : 'Iniciando…'
                    }}
                </p>
            </div>
        </div>

        <div
            v-else
            class="grid h-full grid-cols-[minmax(0,5fr)_minmax(0,7fr)] gap-[2.5vw] p-[2.5vw]"
        >
            <!-- Coluna esquerda: entrada pelo celular -->
            <section class="flex min-h-0 flex-col gap-[2vw]">
                <div class="flex items-center justify-between">
                    <BrandMark size="lg" />
                </div>

                <div
                    v-if="showQr"
                    class="bg-surface ring-line flex min-h-0 flex-1 flex-col items-center justify-center gap-[1.4vw] rounded-[2vw] p-[2vw] ring-1"
                >
                    <p
                        class="font-display text-center text-[2.6vw] leading-tight font-extrabold tracking-tight"
                    >
                        Imprima pelo celular
                    </p>
                    <div class="aspect-square h-full max-h-[46vh] min-h-0">
                        <QrCode
                            :key="display.session_qr.url"
                            class="animate-rise"
                            :text="display.session_qr.url"
                            :svg="display.session_qr.svg"
                            label="QR Code para começar a imprimir"
                        />
                    </div>
                    <p
                        class="text-ink-soft flex items-center gap-[0.6vw] text-[1.3vw]"
                    >
                        <ScanLine class="text-process size-[1.6vw]" />
                        Aponte a câmera · sem app, sem cadastro
                    </p>
                    <p class="text-ink-soft font-mono text-[0.9vw]">
                        novo QR em {{ formatCountdown(qrSeconds) }}
                        <a
                            v-if="api.isDemo"
                            :href="display.session_qr.url"
                            target="_blank"
                            rel="noopener"
                            class="text-process ml-[1vw] cursor-pointer underline"
                            >abrir no navegador (demo)</a
                        >
                    </p>
                </div>

                <div
                    v-else
                    class="bg-warn-soft ring-warn/40 flex flex-1 flex-col items-center justify-center gap-[1.5vw] rounded-[2vw] p-[3vw] text-center ring-1"
                    role="status"
                >
                    <WifiOff v-if="isOffline" class="text-warn size-[6vw]" />
                    <TriangleAlert v-else class="text-warn size-[6vw]" />
                    <p
                        class="font-display text-[3vw] leading-tight font-extrabold"
                    >
                        {{ isOffline ? 'Sem conexão' : printer?.title }}
                    </p>
                    <p class="text-ink-soft text-[1.5vw]">
                        {{
                            isOffline
                                ? 'Tentando reconectar. Pedidos pagos não se perdem.'
                                : printer?.detail
                        }}
                    </p>
                </div>

                <ol class="grid grid-cols-3 gap-[1vw]">
                    <li
                        v-for="(stepText, index) in [
                            'Escaneie o QR',
                            'Envie o arquivo e escolha as opções',
                            'Pague com Pix e retire aqui',
                        ]"
                        :key="stepText"
                        class="bg-surface ring-line rounded-[1.2vw] p-[1vw] ring-1"
                    >
                        <span
                            class="font-display text-process text-[2vw] font-extrabold"
                            >{{ index + 1 }}</span
                        >
                        <p class="text-[1.15vw] leading-snug font-semibold">
                            {{ stepText }}
                        </p>
                    </li>
                </ol>
            </section>

            <!-- Coluna direita: fila, impressora, preços -->
            <section class="flex min-h-0 flex-col gap-[1.5vw]">
                <div
                    v-if="isOffline"
                    class="bg-warn-soft text-warn flex items-center gap-[0.8vw] rounded-[1vw] px-[1.2vw] py-[0.7vw] text-[1.1vw] font-semibold"
                    role="status"
                >
                    <WifiOff class="size-[1.4vw]" />
                    Sem conexão com o servidor. Mostrando a última informação.
                </div>

                <div
                    class="bg-surface ring-line flex min-h-0 flex-[3] flex-col rounded-[2vw] p-[1.8vw] ring-1"
                >
                    <h2
                        class="text-ok flex items-center gap-[0.7vw] text-[1.4vw] font-bold"
                    >
                        <CircleCheck class="size-[1.7vw]" />
                        Pronto para retirar
                    </h2>
                    <div
                        v-if="ready.length"
                        class="mt-[1.2vw] flex flex-wrap content-start gap-[1vw]"
                        aria-live="polite"
                    >
                        <span
                            v-for="entry in ready"
                            :key="entry.pickup_code"
                            :class="[
                                'font-display rounded-[1.2vw] px-[1.6vw] py-[0.6vw] text-[4.6vw] leading-none font-extrabold tracking-wider',
                                freshReady.has(entry.pickup_code)
                                    ? 'animate-pop bg-ok text-paper'
                                    : 'bg-ok-soft text-ok',
                            ]"
                            >{{ entry.pickup_code }}</span
                        >
                    </div>
                    <p
                        v-else
                        class="text-ink-soft mt-[1.2vw] flex flex-1 items-center text-[1.4vw]"
                    >
                        Nenhum pedido esperando retirada.
                    </p>
                </div>

                <div class="grid min-h-0 flex-[2] grid-cols-2 gap-[1.5vw]">
                    <div
                        class="bg-surface ring-line min-h-0 rounded-[2vw] p-[1.8vw] ring-1"
                    >
                        <h2
                            class="text-process flex items-center gap-[0.7vw] text-[1.4vw] font-bold"
                        >
                            <Printer class="size-[1.7vw]" />
                            Imprimindo
                        </h2>
                        <ul
                            v-if="printing.length"
                            class="mt-[1vw] space-y-[1vw]"
                        >
                            <li
                                v-for="entry in printing"
                                :key="entry.pickup_code"
                            >
                                <div
                                    class="flex items-baseline justify-between"
                                >
                                    <span
                                        class="font-display text-[3.2vw] leading-none font-extrabold tracking-wider"
                                        >{{ entry.pickup_code }}</span
                                    >
                                    <span
                                        v-if="entry.progress"
                                        class="text-ink-soft font-mono text-[1.1vw]"
                                        >{{ entry.progress.sheets_done }}/{{
                                            entry.progress.sheets_total
                                        }}
                                        folhas</span
                                    >
                                </div>
                                <div
                                    class="bg-surface-2 mt-[0.5vw] h-[0.6vw] overflow-hidden rounded-full"
                                >
                                    <div
                                        class="bg-process h-full rounded-full transition-[width] duration-1000"
                                        :style="{
                                            width: `${entry.progress ? percentOf(entry.progress.sheets_done, entry.progress.sheets_total) : 0}%`,
                                        }"
                                    />
                                </div>
                            </li>
                        </ul>
                        <p v-else class="text-ink-soft mt-[1vw] text-[1.2vw]">
                            Impressora livre.
                        </p>
                    </div>

                    <div
                        class="bg-surface ring-line min-h-0 rounded-[2vw] p-[1.8vw] ring-1"
                    >
                        <h2
                            class="text-ink-soft flex items-center gap-[0.7vw] text-[1.4vw] font-bold"
                        >
                            <Smartphone class="size-[1.7vw]" />
                            Na fila
                        </h2>
                        <div
                            v-if="queued.length"
                            class="mt-[1vw] flex flex-wrap gap-[0.7vw]"
                        >
                            <span
                                v-for="(entry, index) in queued"
                                :key="entry.pickup_code"
                                class="bg-surface-2 font-display rounded-[0.8vw] px-[1vw] py-[0.4vw] text-[2.2vw] font-extrabold tracking-wider"
                                :aria-label="`${index + 1}º na fila: ${entry.pickup_code}`"
                                >{{ entry.pickup_code }}</span
                            >
                        </div>
                        <p v-else class="text-ink-soft mt-[1vw] text-[1.2vw]">
                            Ninguém na fila.
                        </p>
                    </div>
                </div>

                <footer
                    class="bg-surface ring-line grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-[2vw] rounded-[2vw] px-[1.8vw] py-[1.2vw] ring-1"
                >
                    <dl class="flex gap-[2vw]">
                        <div>
                            <dt class="text-ink-soft text-[0.95vw]">
                                Preto e branco
                            </dt>
                            <dd
                                class="font-display text-[1.8vw] font-extrabold"
                            >
                                {{
                                    formatMoney(
                                        display.pricing.bw_per_side_cents,
                                    )
                                }}
                                <span
                                    class="text-ink-soft text-[0.9vw] font-normal"
                                    >/face</span
                                >
                            </dd>
                        </div>
                        <div
                            v-if="display.pricing.color_per_side_cents !== null"
                        >
                            <dt class="text-ink-soft text-[0.95vw]">
                                Colorido
                            </dt>
                            <dd
                                class="font-display text-[1.8vw] font-extrabold"
                            >
                                {{
                                    formatMoney(
                                        display.pricing.color_per_side_cents,
                                    )
                                }}
                                <span
                                    class="text-ink-soft text-[0.9vw] font-normal"
                                    >/face</span
                                >
                            </dd>
                        </div>
                    </dl>

                    <div class="min-w-0 text-[1vw]">
                        <p
                            :class="[
                                'flex items-center gap-[0.5vw] font-semibold',
                                printer?.health === 'ok'
                                    ? 'text-ok'
                                    : printer?.health === 'warning'
                                      ? 'text-warn'
                                      : 'text-danger',
                            ]"
                        >
                            <span
                                class="size-[0.7vw] rounded-full bg-current"
                                aria-hidden="true"
                            />
                            {{ printer?.title }}
                            <template v-if="printer?.health === 'warning'">
                                · {{ printer.detail }}</template
                            >
                        </p>
                        <div
                            class="text-ink-soft mt-[0.5vw] flex flex-wrap gap-x-[1.2vw] whitespace-nowrap"
                        >
                            <span v-if="paperLevel !== null"
                                >Papel {{ paperLevel }}%</span
                            >
                            <span v-if="tonerLevel !== null"
                                >Toner {{ tonerLevel }}%</span
                            >
                            <span>Ajuda: {{ display.totem.help_contact }}</span>
                        </div>
                    </div>

                    <div class="text-right">
                        <p class="font-mono text-[2vw] font-bold">
                            {{ clock }}
                        </p>
                        <p class="text-ink-soft text-[0.9vw]">
                            {{ display.totem.location }}
                        </p>
                    </div>
                </footer>

                <p
                    v-if="api.isDemo"
                    class="text-ink-soft text-center font-mono text-[0.8vw]"
                >
                    MODO DEMONSTRAÇÃO · sem cobrança real
                </p>
            </section>
        </div>
    </div>
</template>
