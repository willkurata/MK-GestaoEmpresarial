<script setup lang="ts">
import {
    billableMinutes,
    billTotals,
    elapsedSeconds,
    printedPages,
} from '@cc/lib/billing';
import { formatDuration, formatMoney, formatRate } from '@cc/lib/format';
import type { Session } from '@cc/types';
import { Receipt, X } from '@lucide/vue';
import { computed } from 'vue';

/**
 * Conta da sessão: tempo + impressões + produtos avulsos (WIL-36).
 * Com `editable`, cada lançamento pode ser desfeito enquanto a sessão está aberta.
 */
const props = defineProps<{
    session: Session;
    now: number;
    editable?: boolean;
    busy?: boolean;
    title?: string;
}>();

defineEmits<{
    removeItem: [itemId: number];
    removePrint: [entryId: number];
}>();

const totals = computed(() => billTotals(props.session, props.now));
const isOpenRunning = computed(
    () => props.session.billing === 'open' && props.session.status !== 'ended',
);
const pages = computed(() => printedPages(props.session));

const timeLabel = computed(() => {
    const session = props.session;

    if (session.billing === 'package') {
        return `Pacote · ${formatDuration(Math.round(session.total_seconds / 60))}`;
    }

    const minutes = billableMinutes(elapsedSeconds(session, props.now));

    return `Tempo livre · ${formatDuration(minutes)}`;
});
</script>

<template>
    <article class="panel pixel-corners p-5">
        <div class="flex items-center justify-between">
            <span class="eyebrow">{{ title ?? 'Conta da sessão' }}</span>
            <Receipt class="text-neon size-4" />
        </div>

        <div class="mt-4 grid gap-2.5 text-sm">
            <div class="flex items-baseline justify-between gap-3">
                <span>
                    <span class="font-medium">{{ timeLabel }}</span>
                    <span
                        v-if="session.hourly_rate_cents !== null"
                        class="text-dim block text-xs"
                    >
                        {{ formatRate(session.hourly_rate_cents) }} · por minuto
                        iniciado
                    </span>
                </span>
                <span class="tabular font-semibold">
                    {{ formatMoney(totals.time) }}
                </span>
            </div>

            <!-- Impressões -->
            <div
                v-if="session.prints.length > 0"
                class="border-line border-t pt-2.5"
            >
                <div class="flex items-baseline justify-between gap-3">
                    <span class="font-medium">
                        Impressões ·
                        <span class="tabular">{{ pages }}</span>
                        {{ pages === 1 ? 'página' : 'páginas' }}
                    </span>
                    <span class="tabular font-semibold">
                        {{ formatMoney(totals.prints) }}
                    </span>
                </div>
                <ul class="mt-1.5 grid gap-1">
                    <li
                        v-for="entry in session.prints"
                        :key="entry.id"
                        class="text-dim flex items-center justify-between gap-2 text-xs"
                    >
                        <span class="tabular">
                            {{ entry.pages }} pág. ×
                            {{ formatMoney(entry.price_per_page_cents) }}
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="tabular">{{
                                formatMoney(
                                    entry.pages * entry.price_per_page_cents,
                                )
                            }}</span>
                            <button
                                v-if="editable"
                                type="button"
                                class="hover:text-danger -m-1 p-1 disabled:opacity-40"
                                :disabled="busy"
                                :aria-label="`Remover ${entry.pages} páginas`"
                                @click="$emit('removePrint', entry.id)"
                            >
                                <X class="size-3.5" />
                            </button>
                        </span>
                    </li>
                </ul>
            </div>

            <!-- Produtos -->
            <div
                v-if="session.items.length > 0"
                class="border-line border-t pt-2.5"
            >
                <div class="flex items-baseline justify-between gap-3">
                    <span class="font-medium">Produtos</span>
                    <span class="tabular font-semibold">
                        {{ formatMoney(totals.products) }}
                    </span>
                </div>
                <ul class="mt-1.5 grid gap-1">
                    <li
                        v-for="item in session.items"
                        :key="item.id"
                        class="text-dim flex items-center justify-between gap-2 text-xs"
                    >
                        <span class="truncate">
                            <span class="tabular">{{ item.quantity }}×</span>
                            {{ item.name }}
                            <span
                                v-if="item.product_id === null"
                                class="text-sky"
                                title="Preço digitado no lançamento"
                                >· manual</span
                            >
                        </span>
                        <span class="flex shrink-0 items-center gap-1">
                            <span class="tabular">{{
                                formatMoney(
                                    item.quantity * item.unit_price_cents,
                                )
                            }}</span>
                            <button
                                v-if="editable"
                                type="button"
                                class="hover:text-danger -m-1 p-1 disabled:opacity-40"
                                :disabled="busy"
                                :aria-label="`Remover ${item.name}`"
                                @click="$emit('removeItem', item.id)"
                            >
                                <X class="size-3.5" />
                            </button>
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <div
            class="border-line-strong mt-4 grid gap-1.5 border-t border-dashed pt-4 text-sm"
        >
            <div class="flex items-end justify-between">
                <span class="eyebrow">Total</span>
                <span class="tabular text-neon text-3xl font-bold">
                    {{ formatMoney(totals.total) }}
                </span>
            </div>
            <div
                v-if="totals.paid > 0"
                class="text-dim flex justify-between text-xs"
            >
                <span>Já pago</span>
                <span class="tabular">{{ formatMoney(totals.paid) }}</span>
            </div>
            <div
                v-if="totals.paid > 0"
                class="flex justify-between font-semibold"
            >
                <span>A pagar no balcão</span>
                <span class="tabular text-sky">{{
                    formatMoney(totals.balance)
                }}</span>
            </div>
        </div>

        <p v-if="isOpenRunning" class="text-dim mt-3 text-xs">
            Prévia ao vivo. O valor do tempo é fechado pelo servidor ao
            encerrar.
        </p>
    </article>
</template>
