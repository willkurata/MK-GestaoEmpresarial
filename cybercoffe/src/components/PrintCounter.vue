<script setup lang="ts">
import PixelBlocks from '@cc/components/PixelBlocks.vue';
import QuantityStepper from '@cc/components/QuantityStepper.vue';
import { printedPages, printsCents } from '@cc/lib/billing';
import { formatMoney } from '@cc/lib/format';
import type { Session } from '@cc/types';
import { Printer } from '@lucide/vue';
import { computed, ref } from 'vue';

/**
 * Contador de páginas impressas na sessão (WIL-38). Mostra o acumulado e
 * permite lançar novas páginas, cobradas pelo preço por página da loja.
 */
const props = defineProps<{
    session: Session;
    busy: boolean;
    isDemo: boolean;
}>();

const emit = defineEmits<{ add: [pages: number] }>();

const MAX_PAGES = 500;
const pagesToAdd = ref(1);

const pages = computed(() => printedPages(props.session));
const total = computed(() => printsCents(props.session));
const preview = computed(
    () => pagesToAdd.value * props.session.price_per_page_cents,
);

function submit(): void {
    emit('add', pagesToAdd.value);
    pagesToAdd.value = 1;
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div
            class="bg-panel-2 relative flex items-end justify-between gap-4 overflow-hidden p-4"
        >
            <div>
                <span class="eyebrow">Páginas nesta sessão</span>
                <p
                    class="tabular text-sky mt-1 text-5xl leading-none font-bold"
                    aria-live="polite"
                >
                    {{ pages }}
                </p>
            </div>
            <div class="text-right">
                <span class="eyebrow">Na conta</span>
                <p class="tabular mt-1 text-xl font-semibold">
                    {{ formatMoney(total) }}
                </p>
            </div>
            <PixelBlocks
                class="absolute top-2 right-2 opacity-40"
                :columns="3"
                :rows="1"
                :size="5"
                tone="sky"
                :seed="pages + 7"
            />
        </div>

        <form
            class="flex flex-wrap items-center justify-between gap-3"
            @submit.prevent="submit"
        >
            <QuantityStepper
                v-model="pagesToAdd"
                label="Páginas"
                :min="1"
                :max="MAX_PAGES"
                size="lg"
            />
            <button
                type="submit"
                class="btn btn-sky h-14 flex-1"
                :disabled="busy || session.price_per_page_cents <= 0"
            >
                <Printer class="size-4" />
                Lançar {{ formatMoney(preview) }}
            </button>
        </form>
        <p class="text-dim text-xs">
            <template v-if="session.price_per_page_cents > 0">
                {{ formatMoney(session.price_per_page_cents) }} por página{{
                    isDemo ? ' (preço de exemplo)' : ''
                }}.
            </template>
            <template v-else>
                Preço por página não configurado na loja.
            </template>
        </p>
    </div>
</template>
