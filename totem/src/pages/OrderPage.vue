<script setup lang="ts">
import { LoaderCircle, ScanLine, X } from '@lucide/vue';
import { api, ApiError, errorMessage, recallHandle } from '@totem/api';
import BrandMark from '@totem/components/BrandMark.vue';
import { useCompletionAlert } from '@totem/composables/useCompletionAlert';
import { usePolling } from '@totem/composables/usePolling';
import { formatMoney } from '@totem/lib/format';
import { summarizePrinter } from '@totem/lib/printer';
import { quotePrice } from '@totem/lib/pricing';
import LayoutStep from '@totem/pages/mobile/LayoutStep.vue';
import OutcomeStep from '@totem/pages/mobile/OutcomeStep.vue';
import PaymentStep from '@totem/pages/mobile/PaymentStep.vue';
import TrackingStep from '@totem/pages/mobile/TrackingStep.vue';
import UploadStep from '@totem/pages/mobile/UploadStep.vue';
import type {
    Order,
    OrderStatus,
    PrintOptions,
    TotemDisplay,
    UploadProgress,
} from '@totem/types';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{ orderId: string }>();

const handle = recallHandle(props.orderId);
const order = ref<Order | null>(null);
const display = ref<TotemDisplay | null>(null);
const loadError = ref<string | null>(
    handle ? null : 'Este pedido não foi aberto neste celular.',
);

const options = ref<PrintOptions | null>(null);
const uploading = ref(false);
const uploadProgress = ref<UploadProgress | null>(null);
const uploadName = ref<string | null>(null);
const uploadError = ref<string | null>(null);
const replacing = ref(false);
const imageUrl = ref<string | null>(null);
const saving = ref(false);
const saveError = ref<string | null>(null);
const busy = ref(false);
const actionError = ref<string | null>(null);

const alerts = useCompletionAlert();
const alertsOn = ref(false);

const ACTIVE: OrderStatus[] = ['awaiting_payment', 'queued', 'printing'];
const ENDED: OrderStatus[] = ['failed', 'refunded', 'expired', 'canceled'];

type Step = 'upload' | 'layout' | 'payment' | 'tracking' | 'outcome';

const step = computed<Step | null>(() => {
    const current = order.value;

    if (!current) {
        return null;
    }

    if (current.status === 'draft') {
        return !current.file || replacing.value || uploading.value
            ? 'upload'
            : 'layout';
    }

    if (current.status === 'awaiting_payment') {
        return 'payment';
    }

    return ENDED.includes(current.status) ? 'outcome' : 'tracking';
});

const stepNumber = computed(
    () =>
        ({ upload: 1, layout: 2, payment: 3, tracking: 4, outcome: 4 })[
            step.value ?? 'upload'
        ],
);

const pricing = computed(() => display.value?.pricing ?? null);
const printer = computed(() => display.value?.printer ?? null);
const printerSummary = computed(() =>
    printer.value ? summarizePrinter(printer.value) : null,
);

/** Prévia local (instantânea); o servidor confirma em `order.quote`. */
const quote = computed(() => {
    const pages = order.value?.file?.page_count;

    if (!options.value || !pricing.value || !pages) {
        return null;
    }

    return quotePrice(options.value, pages, pricing.value);
});

async function refresh(): Promise<void> {
    if (!handle) {
        return;
    }

    try {
        const fresh = await api.getOrder(handle);
        order.value = fresh;
        loadError.value = null;

        if (options.value === null || fresh.status !== 'draft') {
            options.value = { ...fresh.options };
        }
    } catch (error) {
        if (error instanceof ApiError && error.status === 404) {
            loadError.value =
                'Pedido não encontrado. Escaneie o QR do totem de novo.';
        }
        // Outras falhas: mantém o último estado e tenta no próximo ciclo.
    }
}

async function loadDisplay(totemCode: string): Promise<void> {
    try {
        display.value = await api.getDisplay(totemCode);
    } catch {
        // Sem preços do totem, a página usa o valor que o servidor calcula.
    }
}

usePolling(async () => {
    const status = order.value?.status;

    if (
        order.value === null ||
        (status && ACTIVE.includes(status)) ||
        order.value.file?.converting
    ) {
        await refresh();
    }
}, 2500);

watch(
    () => order.value?.totem_code,
    (code) => {
        if (code && !display.value) {
            void loadDisplay(code);
        }
    },
);

/* -------------------- avisos de pronto / problema -------------------- */

watch(
    () => order.value?.status,
    (status, previous) => {
        if (!status || !previous || status === previous) {
            return;
        }

        if (status === 'ready') {
            alerts.notifyReady(order.value!.pickup_code);
        } else if (
            (status === 'failed' || status === 'refunded') &&
            ACTIVE.includes(previous)
        ) {
            alerts.notifyProblem(
                order.value?.failure_reason ?? 'Veja os detalhes no celular.',
            );
        }
    },
);

async function enableAlerts(): Promise<void> {
    await alerts.enable();
    await alerts.keepAwake();
    alertsOn.value = true;
}

/* ------------------------------ envio ------------------------------ */

async function pickFile(file: File): Promise<void> {
    if (!handle) {
        return;
    }

    uploading.value = true;
    uploadError.value = null;
    uploadProgress.value = null;
    uploadName.value = file.name;

    if (imageUrl.value) {
        URL.revokeObjectURL(imageUrl.value);
        imageUrl.value = null;
    }

    try {
        const updated = await api.uploadFile(handle, file, (progress) => {
            uploadProgress.value = progress;
        });
        order.value = updated;
        options.value = { ...updated.options };
        replacing.value = false;

        if (file.type.startsWith('image/')) {
            imageUrl.value = URL.createObjectURL(file);
        }
    } catch (error) {
        uploadError.value = errorMessage(error);
    } finally {
        uploading.value = false;
    }
}

onBeforeUnmount(() => {
    if (imageUrl.value) {
        URL.revokeObjectURL(imageUrl.value);
    }
});

/* ----------------------------- opções ----------------------------- */

let saveTimer: number | undefined;
let pendingSave: Promise<void> | null = null;

function saveOptions(): Promise<void> {
    window.clearTimeout(saveTimer);

    if (!handle || !options.value || order.value?.status !== 'draft') {
        return Promise.resolve();
    }

    const snapshot = { ...options.value };
    saving.value = true;

    pendingSave = api
        .updateOptions(handle, snapshot)
        .then((updated) => {
            order.value = updated;
            saveError.value = null;
        })
        .catch((error: unknown) => {
            saveError.value = errorMessage(error);
        })
        .finally(() => {
            saving.value = false;
            pendingSave = null;
        });

    return pendingSave;
}

watch(
    options,
    (value, previous) => {
        if (!value || !previous || step.value !== 'layout') {
            return;
        }

        window.clearTimeout(saveTimer);
        saveTimer = window.setTimeout(() => void saveOptions(), 350);
    },
    { deep: true },
);

/* ------------------------------ ações ------------------------------ */

async function checkout(): Promise<void> {
    if (!handle || busy.value) {
        return;
    }

    busy.value = true;
    actionError.value = null;
    // Toque do usuário: libera som/vibração e mantém a tela acesa.
    void enableAlerts();

    try {
        await (pendingSave ?? saveOptions());

        if (saveError.value) {
            return;
        }

        order.value = await api.checkout(handle);
    } catch (error) {
        actionError.value = errorMessage(error);
        await refresh();
    } finally {
        busy.value = false;
    }
}

async function cancel(): Promise<void> {
    if (!handle || busy.value) {
        return;
    }

    if (!window.confirm('Cancelar este pedido? Nada será cobrado.')) {
        return;
    }

    busy.value = true;
    actionError.value = null;

    try {
        order.value = await api.cancel(handle);
    } catch (error) {
        actionError.value = errorMessage(error);
        await refresh();
    } finally {
        busy.value = false;
    }
}

const canPay = computed(
    () =>
        !!quote.value &&
        !saving.value &&
        !busy.value &&
        printerSummary.value?.acceptingOrders !== false,
);

void refresh();
</script>

<template>
    <div class="mx-auto flex min-h-dvh max-w-md flex-col">
        <header
            class="bg-paper/90 sticky top-0 z-20 flex items-center justify-between gap-3 px-4 pt-4 pb-3 backdrop-blur"
        >
            <BrandMark />
            <button
                v-if="
                    step === 'layout' || step === 'upload' || step === 'payment'
                "
                type="button"
                class="text-ink-soft hover:text-ink grid size-12 place-items-center rounded-xl"
                aria-label="Cancelar pedido"
                :disabled="busy"
                @click="cancel"
            >
                <X class="size-6" />
            </button>
        </header>

        <nav
            v-if="step && step !== 'outcome'"
            class="px-4 pb-2"
            aria-label="Etapas"
        >
            <ol class="grid grid-cols-4 gap-1.5">
                <li
                    v-for="(label, index) in [
                        'Arquivo',
                        'Layout',
                        'Pagamento',
                        'Retirada',
                    ]"
                    :key="label"
                    :aria-current="
                        index + 1 === stepNumber ? 'step' : undefined
                    "
                >
                    <span
                        :class="[
                            'block h-1.5 rounded-full',
                            index + 1 <= stepNumber ? 'bg-process' : 'bg-line',
                        ]"
                    />
                    <span
                        :class="[
                            'mt-1 block text-[11px] font-semibold',
                            index + 1 === stepNumber
                                ? 'text-ink'
                                : 'text-ink-soft',
                        ]"
                        >{{ label }}</span
                    >
                </li>
            </ol>
        </nav>

        <main class="flex-1 px-4 pt-4 pb-36">
            <div
                v-if="loadError"
                class="mt-10 space-y-4 text-center"
                role="alert"
            >
                <span
                    class="bg-process-soft text-process mx-auto grid size-16 place-items-center rounded-2xl"
                >
                    <ScanLine class="size-8" />
                </span>
                <h1 class="font-display text-2xl font-extrabold">
                    {{ loadError }}
                </h1>
                <p class="text-ink-soft">
                    Aponte a câmera para o QR Code na tela do totem.
                </p>
            </div>

            <div v-else-if="!order" class="space-y-4" aria-busy="true">
                <div class="skeleton h-10 w-2/3" />
                <div class="skeleton h-64 w-full" />
                <div class="skeleton h-12 w-full" />
            </div>

            <template v-else>
                <div
                    v-if="
                        printerSummary &&
                        !printerSummary.acceptingOrders &&
                        (step === 'upload' || step === 'layout')
                    "
                    class="bg-warn-soft text-warn mb-4 rounded-2xl px-4 py-3 text-sm font-semibold"
                    role="status"
                >
                    {{ printerSummary.title }}. {{ printerSummary.detail }}
                </div>

                <UploadStep
                    v-if="step === 'upload'"
                    :pricing="pricing"
                    :uploading="uploading"
                    :progress="uploadProgress"
                    :file-name="uploadName"
                    :error="uploadError"
                    @pick="pickFile"
                />

                <LayoutStep
                    v-else-if="
                        step === 'layout' && order.file && options && pricing
                    "
                    v-model="options"
                    :file="order.file"
                    :pricing="pricing"
                    :printer="printer"
                    :image-url="imageUrl"
                    :server-error="saveError"
                    @replace="replacing = true"
                />

                <div
                    v-else-if="step === 'layout'"
                    class="space-y-4"
                    aria-busy="true"
                >
                    <div class="skeleton h-10 w-2/3" />
                    <div class="skeleton h-48 w-full" />
                </div>

                <PaymentStep
                    v-else-if="step === 'payment' && order.payment"
                    :order="order"
                    :payment="order.payment"
                    :is-demo="api.isDemo"
                />

                <TrackingStep
                    v-else-if="step === 'tracking'"
                    :order="order"
                    :alerts-on="alertsOn"
                    :notifications-blocked="
                        alerts.permission.value === 'denied'
                    "
                    @enable-alerts="enableAlerts"
                />

                <OutcomeStep
                    v-else-if="step === 'outcome'"
                    :order="order"
                    :help-contact="display?.totem.help_contact ?? null"
                />

                <p
                    v-if="actionError"
                    role="alert"
                    class="bg-danger-soft text-danger mt-4 rounded-2xl px-4 py-3 font-semibold"
                >
                    {{ actionError }}
                </p>

                <p
                    v-if="api.isDemo && step !== 'payment'"
                    class="text-ink-soft mt-8 text-center font-mono text-[11px]"
                >
                    MODO DEMONSTRAÇÃO · sem cobrança real
                </p>
            </template>
        </main>

        <!-- Zona do polegar: a ação principal fica sempre aqui embaixo. -->
        <footer
            class="thumb-bar border-line bg-paper/95 fixed inset-x-0 bottom-0 z-30 border-t px-4 pt-3 backdrop-blur"
        >
            <div class="mx-auto max-w-md">
                <div v-if="step === 'layout'">
                    <div class="mb-2 flex items-baseline justify-between">
                        <span class="text-ink-soft text-sm">
                            Total
                            <LoaderCircle
                                v-if="saving"
                                class="ml-1 inline size-3.5 animate-spin"
                                aria-label="Atualizando"
                            />
                        </span>
                        <span
                            class="font-display text-2xl font-extrabold"
                            aria-live="polite"
                        >
                            {{ quote ? formatMoney(quote.total_cents) : '—' }}
                        </span>
                    </div>
                    <button
                        type="button"
                        class="bg-process text-on-process flex min-h-14 w-full items-center justify-center gap-2 rounded-2xl text-lg font-bold shadow-lg disabled:opacity-50"
                        :disabled="!canPay"
                        @click="checkout"
                    >
                        <LoaderCircle v-if="busy" class="size-5 animate-spin" />
                        Ir para o pagamento
                    </button>
                </div>
                <div id="cta" />
            </div>
        </footer>
    </div>
</template>
