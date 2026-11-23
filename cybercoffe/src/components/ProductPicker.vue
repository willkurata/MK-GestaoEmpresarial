<script setup lang="ts">
import QuantityStepper from '@cc/components/QuantityStepper.vue';
import { formatMoney, parseMoney } from '@cc/lib/format';
import type { NewSessionItem, Product } from '@cc/types';
import { PencilLine, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';

/**
 * Catálogo de produtos avulsos (água, refrigerante…) com opção de preço
 * manual para o que não está cadastrado (WIL-39). Escolha a quantidade e
 * toque no produto para lançar na conta da sessão.
 */
const props = defineProps<{
    products: Product[];
    busy: boolean;
    isDemo: boolean;
}>();

const emit = defineEmits<{ add: [item: NewSessionItem] }>();

const MAX_QUANTITY = 50;

const quantity = ref(1);
const category = ref<string>('Todos');
const isManualOpen = ref(false);
const manualName = ref('');
const manualPrice = ref('');
const manualError = ref<string | null>(null);

const categories = computed(() => [
    'Todos',
    ...new Set(props.products.map((product) => product.category)),
]);

const visibleProducts = computed(() =>
    category.value === 'Todos'
        ? props.products
        : props.products.filter(
              (product) => product.category === category.value,
          ),
);

function addProduct(product: Product): void {
    emit('add', { product_id: product.id, quantity: quantity.value });
    quantity.value = 1;
}

function addManual(): void {
    const name = manualName.value.trim();
    const priceCents = parseMoney(manualPrice.value);

    if (name === '') {
        manualError.value = 'Informe o nome do produto.';

        return;
    }

    if (priceCents === null) {
        manualError.value = 'Informe um preço válido, ex.: 3,50.';

        return;
    }

    manualError.value = null;
    emit('add', {
        name,
        unit_price_cents: priceCents,
        quantity: quantity.value,
    });
    manualName.value = '';
    manualPrice.value = '';
    quantity.value = 1;
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <QuantityStepper
                v-model="quantity"
                label="Quantidade"
                :min="1"
                :max="MAX_QUANTITY"
            />
            <div
                role="group"
                aria-label="Filtrar por categoria"
                class="flex flex-wrap gap-1"
            >
                <button
                    v-for="option in categories"
                    :key="option"
                    type="button"
                    :aria-pressed="category === option"
                    class="px-2.5 py-1 text-[11px] font-medium tracking-wide uppercase transition-colors"
                    :class="
                        category === option
                            ? 'bg-ink text-void'
                            : 'text-dim ring-line-strong hover:text-ink ring-1 ring-inset'
                    "
                    @click="category = option"
                >
                    {{ option }}
                </button>
            </div>
        </div>

        <p
            v-if="products.length === 0"
            class="text-dim bg-panel-2 p-4 text-center text-sm"
        >
            Nenhum produto cadastrado. Use o preço manual abaixo.
        </p>
        <div v-else class="grid grid-cols-2 gap-2 sm:grid-cols-3">
            <button
                v-for="product in visibleProducts"
                :key="product.id"
                type="button"
                class="pixel-corners bg-panel-2 ring-line-strong hover:bg-neon/10 hover:ring-neon group flex flex-col items-start gap-0.5 p-3 text-left ring-1 transition-colors ring-inset disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="busy"
                :aria-label="`Adicionar ${quantity} ${product.name}`"
                @click="addProduct(product)"
            >
                <span class="flex w-full items-start justify-between gap-1">
                    <span class="text-sm leading-tight font-semibold">
                        {{ product.name }}
                    </span>
                    <Plus
                        class="text-dim group-hover:text-neon size-4 shrink-0"
                    />
                </span>
                <span class="tabular text-dim text-xs">
                    {{ formatMoney(product.price_cents) }}
                </span>
            </button>
        </div>
        <p v-if="isDemo && products.length > 0" class="text-dim -mt-2 text-xs">
            Produtos e preços de exemplo (modo demonstração).
        </p>

        <div class="border-line border-t pt-3">
            <button
                type="button"
                class="text-dim hover:text-sky flex items-center gap-2 text-xs font-medium tracking-wide uppercase"
                :aria-expanded="isManualOpen"
                aria-controls="manual-item"
                @click="isManualOpen = !isManualOpen"
            >
                <PencilLine class="size-3.5" />
                Preço manual
            </button>
            <form
                v-if="isManualOpen"
                id="manual-item"
                class="animate-rise mt-3 grid gap-2 sm:grid-cols-[1fr_120px_auto]"
                @submit.prevent="addManual"
            >
                <label class="flex flex-col gap-1">
                    <span class="sr-only">Nome do produto</span>
                    <input
                        v-model="manualName"
                        type="text"
                        maxlength="60"
                        placeholder="Ex.: Pão de queijo"
                        class="bg-void text-ink ring-line-strong placeholder:text-dim/70 focus:ring-sky h-11 border-0 px-3 text-sm ring-1 outline-none ring-inset focus:ring-2"
                    />
                </label>
                <label class="flex flex-col gap-1">
                    <span class="sr-only">Preço unitário em reais</span>
                    <input
                        v-model="manualPrice"
                        type="text"
                        inputmode="decimal"
                        placeholder="R$ 0,00"
                        class="tabular bg-void text-ink ring-line-strong placeholder:text-dim/70 focus:ring-sky h-11 border-0 px-3 text-sm ring-1 outline-none ring-inset focus:ring-2"
                    />
                </label>
                <button type="submit" class="btn btn-sky" :disabled="busy">
                    <Plus class="size-4" /> Lançar
                </button>
                <p
                    v-if="manualError"
                    role="alert"
                    class="text-danger text-xs sm:col-span-3"
                >
                    {{ manualError }}
                </p>
            </form>
        </div>
    </div>
</template>
