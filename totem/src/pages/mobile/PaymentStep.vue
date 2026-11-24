<script setup lang="ts">
import { Check, Clock, Copy, LoaderCircle, QrCode as QrIcon } from '@lucide/vue';
import QrCode from '@totem/components/QrCode.vue';
import { useNow } from '@totem/composables/useNow';
import {
    formatCountdown,
    formatMoney,
    plural,
    secondsUntil,
} from '@totem/lib/format';
import type { Order, PixPayment } from '@totem/types';
import { computed, ref } from 'vue';

const props = defineProps<{
    order: Order;
    payment: PixPayment;
    isDemo: boolean;
}>();

const now = useNow();
const copied = ref(false);
const showQr = ref(false);

const remaining = computed(() =>
    secondsUntil(props.payment.expires_at, now.value),
);

async function copyCode(): Promise<void> {
    try {
        await navigator.clipboard.writeText(props.payment.copy_paste);
    } catch {
        // Sem API de área de transferência: seleciona o texto para cópia manual.
        const field = document.getElementById('pix-code') as HTMLTextAreaElement;
        field?.select();
    }

    copied.value = true;
    window.setTimeout(() => (copied.value = false), 2500);
}
</script>

<template>
    <section class="animate-rise space-y-5">
        <header>
            <h1 class="font-display text-3xl font-extrabold tracking-tight">
                Pague com Pix
            </h1>
            <p class="mt-1 text-ink-soft">
                A impressão começa sozinha assim que o pagamento cair.
            </p>
        </header>

        <div class="rounded-3xl bg-surface p-5 ring-1 ring-line">
            <p class="text-sm font-semibold text-ink-soft">Total</p>
            <p class="font-display text-5xl font-extrabold tracking-tight">
                {{ formatMoney(payment.amount_cents) }}
            </p>
            <p v-if="order.quote" class="mt-1 font-mono text-xs text-ink-soft">
                {{ plural(order.quote.sides, 'face', 'faces') }} ×
                {{ plural(order.quote.copies, 'cópia', 'cópias') }} ·
                {{ plural(order.quote.sheets, 'folha', 'folhas') }}
            </p>
            <p
                class="mt-4 flex items-center gap-2 text-sm font-semibold"
                :class="remaining < 60 ? 'text-warn' : 'text-ink-soft'"
                aria-live="polite"
            >
                <Clock class="size-4" />
                Código vale por {{ formatCountdown(remaining) }}
            </p>
        </div>

        <ol class="space-y-2 text-sm">
            <li class="flex gap-2">
                <span class="font-mono font-bold text-process">1</span>
                Toque em <strong>Copiar código Pix</strong>.
            </li>
            <li class="flex gap-2">
                <span class="font-mono font-bold text-process">2</span>
                No app do banco, abra <strong>Pix › Copia e cola</strong>.
            </li>
            <li class="flex gap-2">
                <span class="font-mono font-bold text-process">3</span>
                Volte aqui. Avisamos quando ficar pronto.
            </li>
        </ol>

        <label for="pix-code" class="sr-only">Código Pix copia e cola</label>
        <textarea
            id="pix-code"
            readonly
            rows="2"
            :value="payment.copy_paste"
            class="w-full resize-none rounded-2xl bg-surface-2 px-3 py-2 font-mono text-[11px] break-all text-ink-soft focus:outline-none"
        />

        <button
            type="button"
            class="flex min-h-12 w-full items-center justify-center gap-2 rounded-2xl text-sm font-semibold text-process ring-1 ring-line"
            :aria-expanded="showQr"
            @click="showQr = !showQr"
        >
            <QrIcon class="size-4" />
            {{ showQr ? 'Esconder QR do Pix' : 'Pagar com outro celular (QR)' }}
        </button>
        <div v-if="showQr" class="mx-auto w-64">
            <QrCode
                :text="payment.copy_paste"
                :svg="payment.qr_svg"
                label="QR Code do Pix"
            />
        </div>

        <p
            class="flex items-center justify-center gap-2 text-sm text-ink-soft"
            aria-live="polite"
        >
            <LoaderCircle class="size-4 animate-spin text-process" />
            Aguardando o pagamento…
        </p>

        <p
            v-if="isDemo"
            class="rounded-2xl bg-warn-soft px-4 py-3 text-sm text-warn"
        >
            Demonstração: este código não paga nada. O pagamento é confirmado
            sozinho em alguns segundos.
        </p>

        <Teleport defer to="#cta">
            <button
                type="button"
                class="flex min-h-14 w-full items-center justify-center gap-2 rounded-2xl bg-process text-lg font-bold text-on-process shadow-lg"
                @click="copyCode"
            >
                <Check v-if="copied" class="size-5" />
                <Copy v-else class="size-5" />
                {{ copied ? 'Código copiado!' : 'Copiar código Pix' }}
            </button>
        </Teleport>
    </section>
</template>
