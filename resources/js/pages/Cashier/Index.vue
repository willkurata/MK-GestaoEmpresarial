<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    Banknote,
    LockKeyhole,
    Minus,
    Plus,
    QrCode,
    ScanBarcode,
    Search,
    Trash2,
    X,
} from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import {
    open as openCash,
    close as closeCash,
    index as cashierIndex,
    withdraw as withdrawCash,
} from '@/routes/pos';
import { refund as refundSale, store as storeSale } from '@/routes/pos/sales';
import { index as productsIndex } from '@/routes/products';

type Product = {
    id: number;
    name: string;
    barcode: string | null;
    sale_price: number | string;
    active: boolean;
};
type SaleItem = { product_id: number; quantity: number };
type CashSession = {
    id: number;
    opened_at?: string;
    opening_amount?: number | string;
};
type Sale = {
    id: number;
    total?: number | string;
    total_amount?: number | string;
    created_at?: string;
    occurred_at?: string;
    payment_method?: string;
    status?: string;
    refunded_at?: string | null;
    operator_name?: string;
    items?: Array<{ product_name?: string; name?: string; quantity: number }>;
};
type CashMovement = {
    id: number;
    type: string;
    description: string;
    method: string;
    amount: string;
    occurredAt: string;
    operator: string;
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Caixa e vendas', href: cashierIndex() }],
    },
});

const props = defineProps<{
    products: Product[];
    currentSession: CashSession | null;
    cashAvailable: string;
    cashMovements: CashMovement[];
    cashHeldBy: string | null;
    recentSales: Sale[];
}>();

const page = usePage();
const canReverseSales = computed(() =>
    Boolean(
        (page.props.auth as { permissions?: { reverseSales?: boolean } })
            .permissions?.reverseSales,
    ),
);

const MAX_QUANTITY = 100000;
const productSearch = ref('');
const productSearchMessage = ref('');
const productSearchInput = ref<HTMLInputElement | null>(null);

const openingForm = useForm<{ opening_amount: number | null }>({
    opening_amount: null,
});
const saleForm = useForm<{
    items: SaleItem[];
    discount_type: 'none' | 'fixed' | 'percentage';
    discount_value: number;
    payment_method: 'Pix' | 'Dinheiro';
    received_amount: number | null;
}>({
    items: [],
    discount_type: 'none',
    discount_value: 0,
    payment_method: 'Pix',
    received_amount: null,
});
const closeForm = useForm<{ closing_amount: number | null }>({
    closing_amount: null,
});
const confirmingClose = ref(false);
const withdrawalForm = useForm<{ amount: number | null; description: string }>({
    amount: null,
    description: '',
});
const refundForm = useForm<{ reason: string }>({ reason: '' });
const refundingSaleId = ref<number | null>(null);

const activeProducts = computed(() =>
    props.products.filter((product) => product.active),
);
const productsById = computed(
    () => new Map(props.products.map((product) => [product.id, product])),
);
const normalizedQuery = computed(() =>
    productSearch.value.trim().toLocaleLowerCase('pt-BR'),
);

const visibleProducts = computed(() => {
    const query = normalizedQuery.value;

    if (!query) {
        return activeProducts.value;
    }

    return activeProducts.value.filter(
        (product) =>
            product.name.toLocaleLowerCase('pt-BR').includes(query) ||
            product.barcode?.toLocaleLowerCase('pt-BR').includes(query),
    );
});

/** Produto que será adicionado ao pressionar Enter: código exato, nome exato ou único resultado. */
const selectedProductCandidate = computed(() => {
    const query = normalizedQuery.value;

    if (!query) {
        return null;
    }

    return (
        activeProducts.value.find(
            (product) => product.barcode?.toLocaleLowerCase('pt-BR') === query,
        ) ??
        activeProducts.value.find(
            (product) => product.name.toLocaleLowerCase('pt-BR') === query,
        ) ??
        (visibleProducts.value.length === 1 ? visibleProducts.value[0] : null)
    );
});

const quantityInCart = (productId: number): number =>
    saleForm.items.find((item) => item.product_id === productId)?.quantity ?? 0;
const unitPrice = (productId: number): number =>
    Number(productsById.value.get(productId)?.sale_price ?? 0);
const productName = (productId: number): string =>
    productsById.value.get(productId)?.name ?? 'Produto';
const itemCount = computed(() =>
    saleForm.items.reduce((sum, item) => sum + (Number(item.quantity) || 0), 0),
);

const subtotal = computed(() =>
    saleForm.items.reduce(
        (sum, item) =>
            sum + unitPrice(item.product_id) * (Number(item.quantity) || 0),
        0,
    ),
);
const discountAmount = computed(() => {
    if (saleForm.discount_type === 'percentage') {
        return (
            (subtotal.value *
                Math.min(Math.max(saleForm.discount_value || 0, 0), 100)) /
            100
        );
    }
    if (saleForm.discount_type === 'fixed') {
        return Math.min(
            Math.max(saleForm.discount_value || 0, 0),
            subtotal.value,
        );
    }
    return 0;
});
const total = computed(() =>
    Math.max(subtotal.value - discountAmount.value, 0),
);
const changeAmount = computed(() =>
    Math.max((saleForm.received_amount ?? 0) - total.value, 0),
);
const missingCash = computed(() =>
    saleForm.payment_method === 'Dinheiro'
        ? Math.max(total.value - (saleForm.received_amount ?? 0), 0)
        : 0,
);
const canSubmitSale = computed(
    () =>
        !saleForm.processing &&
        saleForm.items.length > 0 &&
        !(
            saleForm.payment_method === 'Dinheiro' &&
            (saleForm.received_amount ?? 0) < total.value
        ),
);

const expectedCash = computed(() => Number(props.cashAvailable));
// `v-model.number` devolve '' quando o campo é apagado; só calcula com número válido.
const closingDifference = computed(() =>
    typeof closeForm.closing_amount !== 'number' ||
    Number.isNaN(closeForm.closing_amount)
        ? null
        : closeForm.closing_amount - expectedCash.value,
);

const formatCurrency = (amount: number | string): string =>
    Number(amount).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });
const formatDateTime = (value?: string): string =>
    value
        ? new Date(value).toLocaleString('pt-BR', {
              dateStyle: 'short',
              timeStyle: 'short',
          })
        : '';

watch(
    () => saleForm.discount_type,
    () => {
        saleForm.discount_value = 0;
    },
);
watch(
    () => saleForm.payment_method,
    (method) => {
        if (method === 'Pix') {
            saleForm.received_amount = null;
        }
    },
);
watch(
    () => closeForm.closing_amount,
    () => {
        confirmingClose.value = false;
    },
);

function focusProductSearch(): void {
    void nextTick(() => productSearchInput.value?.focus());
}

function addProduct(productId: number): void {
    if (!activeProducts.value.some((product) => product.id === productId)) {
        return;
    }
    const existingItem = saleForm.items.find(
        (item) => item.product_id === productId,
    );
    if (existingItem) {
        existingItem.quantity = Math.min(
            (Number(existingItem.quantity) || 0) + 1,
            MAX_QUANTITY,
        );
    } else {
        saleForm.items.push({ product_id: productId, quantity: 1 });
    }
    saleForm.clearErrors('items');
    productSearch.value = '';
    productSearchMessage.value = '';
    focusProductSearch();
}

function addSelectedProduct(): void {
    if (!selectedProductCandidate.value) {
        productSearchMessage.value = normalizedQuery.value
            ? 'Nenhum produto ativo corresponde ao código ou à busca. Escolha na lista abaixo.'
            : 'Digite o nome ou leia o código de barras do produto.';
        return;
    }

    addProduct(selectedProductCandidate.value.id);
}

function removeItem(productId: number): void {
    saleForm.items = saleForm.items.filter(
        (item) => item.product_id !== productId,
    );
}

function changeQuantity(productId: number, delta: number): void {
    const item = saleForm.items.find(
        (candidate) => candidate.product_id === productId,
    );
    if (!item) {
        return;
    }
    const next = (Number(item.quantity) || 0) + delta;
    if (next < 1) {
        removeItem(productId);
        return;
    }
    item.quantity = Math.min(next, MAX_QUANTITY);
}

/** Quantidade digitada como zero (ou vazia) remove o item, conforme a regra do carrinho. */
function normalizeQuantity(productId: number): void {
    const item = saleForm.items.find(
        (candidate) => candidate.product_id === productId,
    );
    if (!item) {
        return;
    }
    const quantity = Math.trunc(Number(item.quantity));
    if (!Number.isFinite(quantity) || quantity < 1) {
        removeItem(productId);
        return;
    }
    item.quantity = Math.min(quantity, MAX_QUANTITY);
}

function clearSale(): void {
    saleForm.reset();
    saleForm.clearErrors();
    focusProductSearch();
}

function fillExactCash(): void {
    saleForm.received_amount = Math.round(total.value * 100) / 100;
}

function submitSale(): void {
    if (!canSubmitSale.value) {
        return;
    }
    saleForm.post(storeSale().url, {
        preserveScroll: true,
        onSuccess: () => {
            saleForm.reset();
            focusProductSearch();
        },
    });
}

function openSession(): void {
    openingForm.post(openCash().url, {
        preserveScroll: true,
        onSuccess: () => openingForm.reset(),
    });
}

function closeSession(): void {
    if (!confirmingClose.value) {
        confirmingClose.value = true;
        return;
    }
    closeForm.post(closeCash().url, {
        preserveScroll: true,
        onSuccess: () => {
            closeForm.reset();
            confirmingClose.value = false;
        },
    });
}

function submitWithdrawal(): void {
    withdrawalForm.post(withdrawCash().url, {
        preserveScroll: true,
        onSuccess: () => withdrawalForm.reset(),
    });
}

function movementLabel(type: string): string {
    return (
        { venda: 'Venda', retirada: 'Retirada', estorno: 'Estorno' }[type] ??
        type
    );
}

function isRefunded(sale: Sale): boolean {
    return Boolean(sale.refunded_at) || sale.status === 'estornada';
}

function startRefund(saleId: number): void {
    refundForm.reset();
    refundForm.clearErrors();
    refundingSaleId.value = saleId;
    void nextTick(() =>
        document.getElementById(`refund-reason-${saleId}`)?.focus(),
    );
}

function cancelRefund(): void {
    refundForm.reset();
    refundForm.clearErrors();
    refundingSaleId.value = null;
}

function submitRefund(saleId: number): void {
    refundForm.post(refundSale({ sale: saleId }).url, {
        preserveScroll: true,
        onSuccess: () => cancelRefund(),
    });
}

/** Atalho F2: volta o foco para a busca de produtos, útil com leitor de código. */
function handleShortcut(event: KeyboardEvent): void {
    if (event.key === 'F2' && props.currentSession) {
        event.preventDefault();
        focusProductSearch();
    }
}

onMounted(() => window.addEventListener('keydown', handleShortcut));
onBeforeUnmount(() => window.removeEventListener('keydown', handleShortcut));
</script>

<template>
    <Head title="Caixa" />
    <main
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8"
    >
        <PageHeader eyebrow="Vendas" title="Caixa e vendas">
            <template #description>
                <template v-if="props.currentSession"
                    >Registre vendas no turno aberto. Retirada e fechamento
                    ficam abaixo do PDV.</template
                >
                <template v-else
                    >Abra o turno com o dinheiro contado na gaveta para começar
                    a vender.</template
                >
            </template>
            <template #actions>
                <span
                    v-if="props.currentSession"
                    class="inline-flex items-center gap-2 rounded-full border border-success/30 bg-success/10 px-3 py-1.5 text-sm font-medium text-success"
                    role="status"
                >
                    <span
                        class="size-2 rounded-full bg-success"
                        aria-hidden="true"
                    ></span>
                    Caixa aberto<span
                        v-if="props.currentSession.opened_at"
                        class="font-normal"
                        >· desde
                        {{
                            formatDateTime(props.currentSession.opened_at)
                        }}</span
                    >
                </span>
                <span
                    v-else
                    class="inline-flex items-center gap-2 rounded-full border bg-muted px-3 py-1.5 text-sm font-medium text-muted-foreground"
                    role="status"
                >
                    <LockKeyhole class="size-3.5" aria-hidden="true" />
                    {{ props.cashHeldBy ? 'Caixa em uso' : 'Caixa fechado' }}
                </span>
            </template>
        </PageHeader>

        <!-- Turno aberto por outra pessoa -->
        <section
            v-if="props.cashHeldBy"
            class="rounded-xl border border-warning/40 bg-warning/10 p-5 sm:p-6"
            role="status"
            aria-labelledby="cash-held-title"
        >
            <h2 id="cash-held-title" class="text-lg font-semibold">
                Caixa em uso por {{ props.cashHeldBy }}
            </h2>
            <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
                Só pode haver um turno aberto por vez. Para trocar de operador,
                {{ props.cashHeldBy }} precisa entrar e fechar o turno com o
                valor contado. Depois disso, abra um novo turno com o dinheiro
                entregue.
            </p>
        </section>

        <!-- Caixa fechado -->
        <section
            v-else-if="!props.currentSession"
            class="rounded-xl border bg-card p-5 text-card-foreground sm:p-6"
            aria-labelledby="open-session-title"
        >
            <div class="flex items-start gap-4">
                <span
                    class="hidden size-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary sm:grid"
                >
                    <Banknote class="size-5" aria-hidden="true" />
                </span>
                <div class="min-w-0 flex-1">
                    <h2 id="open-session-title" class="text-lg font-semibold">
                        Abrir caixa
                    </h2>
                    <p class="mt-1 max-w-xl text-sm text-muted-foreground">
                        Conte o dinheiro físico na gaveta e informe o valor. Ele
                        não é copiado do turno anterior; diferenças na troca de
                        operador ficam registradas.
                    </p>
                    <form
                        class="mt-4 flex flex-col gap-3 sm:max-w-md sm:flex-row sm:items-end"
                        @submit.prevent="openSession"
                    >
                        <label
                            class="grid flex-1 gap-2 text-sm font-medium"
                            for="opening-amount"
                            >Dinheiro contado na abertura (R$)
                            <input
                                id="opening-amount"
                                v-model.number="openingForm.opening_amount"
                                v-focus
                                type="number"
                                min="0"
                                step="0.01"
                                inputmode="decimal"
                                required
                                placeholder="0,00"
                                class="h-11 rounded-md border border-input bg-background px-3 font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                :aria-invalid="
                                    Boolean(openingForm.errors.opening_amount)
                                "
                            />
                            <span
                                v-if="openingForm.errors.opening_amount"
                                class="font-normal text-destructive"
                                >{{ openingForm.errors.opening_amount }}</span
                            >
                        </label>
                        <button
                            type="submit"
                            :disabled="openingForm.processing"
                            class="min-h-11 rounded-md bg-primary px-5 text-sm font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
                        >
                            {{
                                openingForm.processing
                                    ? 'Abrindo…'
                                    : 'Abrir caixa'
                            }}
                        </button>
                    </form>
                </div>
            </div>
        </section>

        <!-- Caixa aberto: PDV -->
        <template v-else>
            <div
                class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,26rem)]"
            >
                <section
                    class="rounded-xl border bg-card p-4 text-card-foreground sm:p-5"
                    aria-labelledby="products-title"
                >
                    <div
                        class="flex flex-wrap items-baseline justify-between gap-2"
                    >
                        <h2 id="products-title" class="text-lg font-semibold">
                            Produtos
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            <kbd class="rounded border bg-muted px-1 font-mono"
                                >F2</kbd
                            >
                            volta para a busca
                        </p>
                    </div>
                    <form
                        class="mt-3"
                        role="search"
                        @submit.prevent="addSelectedProduct"
                    >
                        <label class="sr-only" for="product-search"
                            >Buscar produto pelo nome ou código de barras</label
                        >
                        <div class="relative">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <input
                                id="product-search"
                                ref="productSearchInput"
                                v-model="productSearch"
                                v-focus
                                autocomplete="off"
                                placeholder="Buscar pelo nome ou ler o código de barras"
                                class="h-12 w-full min-w-0 rounded-lg border border-input bg-background pr-11 pl-9 text-base outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                aria-describedby="product-search-hint"
                                @input="productSearchMessage = ''"
                            />
                            <ScanBarcode
                                class="pointer-events-none absolute top-1/2 right-3 size-5 -translate-y-1/2 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </div>
                        <p
                            id="product-search-hint"
                            class="mt-1.5 text-xs text-muted-foreground"
                        >
                            <template v-if="selectedProductCandidate"
                                >Enter adiciona
                                <strong class="text-foreground">{{
                                    selectedProductCandidate.name
                                }}</strong
                                >.</template
                            >
                            <template v-else
                                >Toque em um produto para adicionar. Toques
                                repetidos aumentam a quantidade.</template
                            >
                        </p>
                        <p
                            v-if="productSearchMessage"
                            class="mt-1 text-sm text-destructive"
                            role="alert"
                        >
                            {{ productSearchMessage }}
                        </p>
                    </form>

                    <div
                        v-if="activeProducts.length === 0"
                        class="mt-4 rounded-lg border border-dashed px-4 py-10 text-center"
                    >
                        <p class="font-medium">Nenhum produto ativo</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Cadastre ou ative produtos para vendê-los no caixa.
                        </p>
                        <Link
                            :href="productsIndex()"
                            class="mt-4 inline-flex min-h-10 items-center rounded-md border px-4 text-sm font-medium hover:bg-muted"
                            >Ir para Produtos</Link
                        >
                    </div>
                    <p
                        v-else-if="visibleProducts.length === 0"
                        class="mt-4 rounded-lg border border-dashed px-4 py-8 text-center text-sm text-muted-foreground"
                    >
                        Nenhum produto corresponde a “{{
                            productSearch.trim()
                        }}”.
                    </p>
                    <ul
                        v-else
                        class="mt-4 grid max-h-[28rem] grid-cols-2 gap-2 overflow-y-auto pr-1 sm:grid-cols-3 lg:max-h-[calc(100vh-18rem)] xl:grid-cols-4"
                        aria-label="Produtos disponíveis"
                    >
                        <li
                            v-for="product in visibleProducts"
                            :key="product.id"
                        >
                            <button
                                type="button"
                                class="relative flex h-full min-h-20 w-full flex-col justify-between gap-2 rounded-lg border bg-background p-3 text-left transition-colors hover:border-primary/60 hover:bg-primary/5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                :class="
                                    quantityInCart(product.id)
                                        ? 'border-primary/60 bg-primary/5'
                                        : ''
                                "
                                :aria-label="`Adicionar ${product.name}, ${formatCurrency(product.sale_price)}${quantityInCart(product.id) ? `, ${quantityInCart(product.id)} na venda` : ''}`"
                                @click="addProduct(product.id)"
                            >
                                <span
                                    class="line-clamp-2 text-sm leading-snug font-medium"
                                    >{{ product.name }}</span
                                >
                                <span
                                    class="text-sm font-semibold text-primary tabular-nums"
                                    >{{
                                        formatCurrency(product.sale_price)
                                    }}</span
                                >
                                <span
                                    v-if="quantityInCart(product.id)"
                                    class="absolute top-1.5 right-1.5 grid min-w-6 place-items-center rounded-full bg-primary px-1.5 text-xs font-semibold text-primary-foreground tabular-nums"
                                    aria-hidden="true"
                                    >{{ quantityInCart(product.id) }}</span
                                >
                            </button>
                        </li>
                    </ul>
                </section>

                <section
                    class="rounded-xl border bg-card text-card-foreground lg:sticky lg:top-4"
                    aria-labelledby="new-sale-title"
                >
                    <form @submit.prevent="submitSale">
                        <div
                            class="flex items-center justify-between gap-2 border-b px-4 py-3 sm:px-5"
                        >
                            <h2
                                id="new-sale-title"
                                class="text-lg font-semibold"
                            >
                                Venda atual
                                <span
                                    v-if="itemCount"
                                    class="text-sm font-normal text-muted-foreground"
                                    >· {{ itemCount }}
                                    {{
                                        itemCount === 1 ? 'item' : 'itens'
                                    }}</span
                                >
                            </h2>
                            <button
                                v-if="saleForm.items.length"
                                type="button"
                                class="inline-flex min-h-9 items-center gap-1 rounded-md px-2 text-sm text-muted-foreground hover:bg-muted hover:text-foreground"
                                @click="clearSale"
                            >
                                <X class="size-4" aria-hidden="true" />Limpar
                            </button>
                        </div>

                        <div
                            v-if="saleForm.items.length === 0"
                            class="px-5 py-10 text-center"
                        >
                            <p class="font-medium">Nenhum item na venda</p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Escolha os produtos ao lado para montar a venda.
                            </p>
                        </div>
                        <ul
                            v-else
                            class="max-h-80 divide-y overflow-y-auto"
                            aria-label="Itens da venda"
                        >
                            <li
                                v-for="item in saleForm.items"
                                :key="item.product_id"
                                class="flex items-center gap-3 px-4 py-3 sm:px-5"
                            >
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">
                                        {{ productName(item.product_id) }}
                                    </p>
                                    <p
                                        class="text-xs text-muted-foreground tabular-nums"
                                    >
                                        {{
                                            formatCurrency(
                                                unitPrice(item.product_id),
                                            )
                                        }}
                                        × {{ Number(item.quantity) || 0 }} =
                                        <span
                                            class="font-medium text-foreground"
                                            >{{
                                                formatCurrency(
                                                    unitPrice(item.product_id) *
                                                        (Number(
                                                            item.quantity,
                                                        ) || 0),
                                                )
                                            }}</span
                                        >
                                    </p>
                                </div>
                                <div
                                    class="flex shrink-0 items-center rounded-md border"
                                >
                                    <button
                                        type="button"
                                        class="grid size-9 place-items-center rounded-l-md hover:bg-muted"
                                        :aria-label="
                                            Number(item.quantity) <= 1
                                                ? `Remover ${productName(item.product_id)}`
                                                : `Diminuir quantidade de ${productName(item.product_id)}`
                                        "
                                        @click="
                                            changeQuantity(item.product_id, -1)
                                        "
                                    >
                                        <Trash2
                                            v-if="Number(item.quantity) <= 1"
                                            class="size-4 text-destructive"
                                            aria-hidden="true"
                                        />
                                        <Minus
                                            v-else
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                    </button>
                                    <label
                                        class="sr-only"
                                        :for="`quantity-${item.product_id}`"
                                        >Quantidade de
                                        {{
                                            productName(item.product_id)
                                        }}</label
                                    >
                                    <input
                                        :id="`quantity-${item.product_id}`"
                                        v-model.number="item.quantity"
                                        type="number"
                                        min="0"
                                        :max="MAX_QUANTITY"
                                        step="1"
                                        inputmode="numeric"
                                        class="h-9 w-12 [appearance:textfield] border-x bg-background text-center text-sm tabular-nums outline-none focus-visible:ring-2 focus-visible:ring-ring [&::-webkit-inner-spin-button]:appearance-none"
                                        @change="
                                            normalizeQuantity(item.product_id)
                                        "
                                    />
                                    <button
                                        type="button"
                                        class="grid size-9 place-items-center rounded-r-md hover:bg-muted"
                                        :aria-label="`Aumentar quantidade de ${productName(item.product_id)}`"
                                        @click="
                                            changeQuantity(item.product_id, 1)
                                        "
                                    >
                                        <Plus
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                    </button>
                                </div>
                            </li>
                        </ul>
                        <p
                            v-if="saleForm.errors.items"
                            class="px-5 pt-2 text-sm text-destructive"
                            role="alert"
                        >
                            {{ saleForm.errors.items }}
                        </p>

                        <div class="space-y-4 border-t px-4 py-4 sm:px-5">
                            <fieldset>
                                <legend class="text-sm font-medium">
                                    Desconto na venda
                                </legend>
                                <div
                                    class="mt-2 flex flex-wrap items-center gap-2"
                                >
                                    <div
                                        class="inline-flex rounded-md border p-0.5"
                                        role="group"
                                        aria-label="Tipo de desconto"
                                    >
                                        <button
                                            v-for="option in [
                                                { value: 'none', label: 'Sem' },
                                                { value: 'fixed', label: 'R$' },
                                                {
                                                    value: 'percentage',
                                                    label: '%',
                                                },
                                            ] as const"
                                            :key="option.value"
                                            type="button"
                                            class="min-h-8 rounded px-3 text-sm font-medium"
                                            :class="
                                                saleForm.discount_type ===
                                                option.value
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'text-muted-foreground hover:bg-muted'
                                            "
                                            :aria-pressed="
                                                saleForm.discount_type ===
                                                option.value
                                            "
                                            @click="
                                                saleForm.discount_type =
                                                    option.value
                                            "
                                        >
                                            {{ option.label }}
                                        </button>
                                    </div>
                                    <template
                                        v-if="saleForm.discount_type !== 'none'"
                                    >
                                        <label
                                            class="sr-only"
                                            for="discount-value"
                                            >{{
                                                saleForm.discount_type ===
                                                'percentage'
                                                    ? 'Desconto em porcentagem'
                                                    : 'Desconto em reais'
                                            }}</label
                                        >
                                        <input
                                            id="discount-value"
                                            v-model.number="
                                                saleForm.discount_value
                                            "
                                            type="number"
                                            min="0"
                                            :max="
                                                saleForm.discount_type ===
                                                'percentage'
                                                    ? 100
                                                    : undefined
                                            "
                                            :step="
                                                saleForm.discount_type ===
                                                'percentage'
                                                    ? 1
                                                    : 0.01
                                            "
                                            inputmode="decimal"
                                            class="h-9 w-28 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        />
                                    </template>
                                </div>
                                <p
                                    v-if="saleForm.errors.discount_value"
                                    class="mt-1 text-sm text-destructive"
                                >
                                    {{ saleForm.errors.discount_value }}
                                </p>
                            </fieldset>

                            <fieldset>
                                <legend class="text-sm font-medium">
                                    Forma de pagamento
                                </legend>
                                <div class="mt-2 grid grid-cols-2 gap-2">
                                    <label
                                        v-for="method in [
                                            { value: 'Pix', icon: QrCode },
                                            {
                                                value: 'Dinheiro',
                                                icon: Banknote,
                                            },
                                        ] as const"
                                        :key="method.value"
                                        class="flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-md border text-sm font-medium has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring"
                                        :class="
                                            saleForm.payment_method ===
                                            method.value
                                                ? 'border-primary bg-primary/10 text-primary'
                                                : 'hover:bg-muted'
                                        "
                                    >
                                        <input
                                            v-model="saleForm.payment_method"
                                            type="radio"
                                            name="payment_method"
                                            :value="method.value"
                                            class="sr-only"
                                        />
                                        <component
                                            :is="method.icon"
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        {{ method.value }}
                                    </label>
                                </div>
                                <p
                                    v-if="saleForm.payment_method === 'Pix'"
                                    class="mt-2 text-xs text-muted-foreground"
                                >
                                    Confirme o recebimento do Pix antes de
                                    finalizar.
                                </p>
                                <p
                                    v-if="saleForm.errors.payment_method"
                                    class="mt-1 text-sm text-destructive"
                                >
                                    {{ saleForm.errors.payment_method }}
                                </p>
                            </fieldset>

                            <div
                                v-if="saleForm.payment_method === 'Dinheiro'"
                                class="grid gap-2"
                            >
                                <label
                                    class="text-sm font-medium"
                                    for="received-amount"
                                    >Valor recebido (R$)</label
                                >
                                <div class="flex gap-2">
                                    <input
                                        id="received-amount"
                                        v-model.number="
                                            saleForm.received_amount
                                        "
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        inputmode="decimal"
                                        required
                                        placeholder="0,00"
                                        class="h-11 min-w-0 flex-1 rounded-md border border-input bg-background px-3 outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        :aria-invalid="
                                            Boolean(
                                                saleForm.errors.received_amount,
                                            )
                                        "
                                    />
                                    <button
                                        type="button"
                                        class="min-h-11 shrink-0 rounded-md border px-3 text-sm font-medium hover:bg-muted disabled:opacity-50"
                                        :disabled="total <= 0"
                                        @click="fillExactCash"
                                    >
                                        Valor exato
                                    </button>
                                </div>
                                <span
                                    v-if="saleForm.errors.received_amount"
                                    class="text-sm text-destructive"
                                    >{{ saleForm.errors.received_amount }}</span
                                >
                            </div>
                        </div>

                        <div
                            class="rounded-b-xl border-t bg-muted/40 px-4 py-4 sm:px-5"
                        >
                            <dl
                                class="grid grid-cols-[1fr_auto] gap-y-1 text-sm tabular-nums"
                            >
                                <dt class="text-muted-foreground">Subtotal</dt>
                                <dd class="text-right">
                                    {{ formatCurrency(subtotal) }}
                                </dd>
                                <template v-if="discountAmount > 0"
                                    ><dt class="text-muted-foreground">
                                        Desconto
                                    </dt>
                                    <dd class="text-right">
                                        −{{ formatCurrency(discountAmount) }}
                                    </dd></template
                                >
                                <dt class="pt-1 text-base font-semibold">
                                    Total
                                </dt>
                                <dd
                                    class="pt-1 text-right text-2xl font-semibold tracking-tight"
                                >
                                    {{ formatCurrency(total) }}
                                </dd>
                                <template
                                    v-if="
                                        saleForm.payment_method === 'Dinheiro'
                                    "
                                >
                                    <dt class="text-muted-foreground">
                                        {{
                                            missingCash > 0
                                                ? 'Falta receber'
                                                : 'Troco'
                                        }}
                                    </dt>
                                    <dd
                                        class="text-right font-semibold"
                                        :class="
                                            missingCash > 0
                                                ? 'text-destructive'
                                                : 'text-success'
                                        "
                                    >
                                        {{
                                            formatCurrency(
                                                missingCash > 0
                                                    ? missingCash
                                                    : changeAmount,
                                            )
                                        }}
                                    </dd>
                                </template>
                            </dl>
                            <button
                                type="submit"
                                :disabled="!canSubmitSale"
                                class="mt-4 min-h-12 w-full rounded-md bg-primary px-5 text-base font-semibold text-primary-foreground shadow-sm hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{
                                    saleForm.processing
                                        ? 'Registrando…'
                                        : saleForm.items.length
                                          ? `Finalizar venda · ${formatCurrency(total)}`
                                          : 'Finalizar venda'
                                }}
                            </button>
                        </div>
                    </form>
                </section>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <section
                    class="rounded-xl border bg-card p-5 text-card-foreground sm:p-6"
                    aria-labelledby="withdrawal-title"
                >
                    <h2 id="withdrawal-title" class="text-lg font-semibold">
                        Retirada de dinheiro
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Reduz o dinheiro esperado no fechamento. O motivo é
                        obrigatório.
                    </p>
                    <form
                        class="mt-4 grid gap-3"
                        @submit.prevent="submitWithdrawal"
                    >
                        <label
                            class="grid gap-2 text-sm font-medium"
                            for="withdrawal-description"
                            >Motivo da retirada
                            <input
                                id="withdrawal-description"
                                v-model="withdrawalForm.description"
                                required
                                maxlength="255"
                                autocomplete="off"
                                placeholder="Ex.: pagamento de entrega"
                                class="h-11 rounded-md border border-input bg-background px-3 font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            />
                            <span
                                v-if="withdrawalForm.errors.description"
                                class="font-normal text-destructive"
                                >{{ withdrawalForm.errors.description }}</span
                            >
                        </label>
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-end"
                        >
                            <label
                                class="grid flex-1 gap-2 text-sm font-medium"
                                for="withdrawal-amount"
                                >Valor (R$)
                                <input
                                    id="withdrawal-amount"
                                    v-model.number="withdrawalForm.amount"
                                    type="number"
                                    min="0.01"
                                    :max="expectedCash"
                                    step="0.01"
                                    inputmode="decimal"
                                    required
                                    placeholder="0,00"
                                    class="h-11 rounded-md border border-input bg-background px-3 font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                />
                                <span
                                    v-if="withdrawalForm.errors.amount"
                                    class="font-normal text-destructive"
                                    >{{ withdrawalForm.errors.amount }}</span
                                >
                            </label>
                            <button
                                type="submit"
                                :disabled="
                                    withdrawalForm.processing ||
                                    expectedCash <= 0
                                "
                                class="min-h-11 rounded-md border px-4 text-sm font-semibold hover:bg-muted disabled:opacity-50"
                            >
                                {{
                                    withdrawalForm.processing
                                        ? 'Registrando…'
                                        : 'Registrar retirada'
                                }}
                            </button>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Disponível em dinheiro:
                            <strong class="text-foreground tabular-nums">{{
                                formatCurrency(expectedCash)
                            }}</strong>
                        </p>
                    </form>
                </section>

                <section
                    class="rounded-xl border bg-card p-5 text-card-foreground sm:p-6"
                    aria-labelledby="close-title"
                >
                    <h2 id="close-title" class="text-lg font-semibold">
                        Fechar caixa
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Conte o dinheiro físico. Pix não entra na gaveta.
                    </p>
                    <form
                        class="mt-4 grid gap-3"
                        @submit.prevent="closeSession"
                    >
                        <dl
                            class="grid grid-cols-[1fr_auto] gap-y-1 rounded-lg bg-muted/40 px-3 py-2 text-sm tabular-nums"
                        >
                            <dt class="text-muted-foreground">
                                Esperado em dinheiro
                            </dt>
                            <dd class="text-right font-medium">
                                {{ formatCurrency(expectedCash) }}
                            </dd>
                            <template v-if="closingDifference !== null">
                                <dt class="text-muted-foreground">Contado</dt>
                                <dd class="text-right font-medium">
                                    {{
                                        formatCurrency(
                                            closeForm.closing_amount ?? 0,
                                        )
                                    }}
                                </dd>
                                <dt class="font-medium">
                                    {{
                                        Math.abs(closingDifference) < 0.005
                                            ? 'Sem diferença'
                                            : closingDifference > 0
                                              ? 'Sobra'
                                              : 'Falta'
                                    }}
                                </dt>
                                <dd
                                    class="text-right font-semibold"
                                    :class="
                                        Math.abs(closingDifference) < 0.005
                                            ? 'text-success'
                                            : 'text-destructive'
                                    "
                                >
                                    {{
                                        formatCurrency(
                                            Math.abs(closingDifference),
                                        )
                                    }}
                                </dd>
                            </template>
                        </dl>
                        <label
                            class="grid gap-2 text-sm font-medium"
                            for="closing-amount"
                            >Valor contado no fechamento (R$)
                            <input
                                id="closing-amount"
                                v-model.number="closeForm.closing_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                inputmode="decimal"
                                required
                                placeholder="0,00"
                                class="h-11 rounded-md border border-input bg-background px-3 font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            />
                            <span
                                v-if="closeForm.errors.closing_amount"
                                class="font-normal text-destructive"
                                >{{ closeForm.errors.closing_amount }}</span
                            >
                        </label>
                        <p
                            v-if="confirmingClose"
                            class="rounded-lg border border-warning/40 bg-warning/10 px-3 py-2 text-sm"
                            role="alert"
                        >
                            Confirme o fechamento.<template
                                v-if="
                                    closingDifference !== null &&
                                    Math.abs(closingDifference) >= 0.005
                                "
                            >
                                A diferença de
                                {{
                                    formatCurrency(Math.abs(closingDifference))
                                }}
                                ficará registrada para conferência.</template
                            >
                            Novas vendas exigirão abrir outro turno.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="submit"
                                :disabled="closeForm.processing"
                                class="min-h-11 flex-1 rounded-md px-4 text-sm font-semibold disabled:opacity-60"
                                :class="
                                    confirmingClose
                                        ? 'bg-destructive text-white hover:bg-destructive/90'
                                        : 'border hover:bg-muted'
                                "
                            >
                                {{
                                    closeForm.processing
                                        ? 'Fechando…'
                                        : confirmingClose
                                          ? 'Confirmar fechamento'
                                          : 'Fechar caixa'
                                }}
                            </button>
                            <button
                                v-if="confirmingClose"
                                type="button"
                                class="min-h-11 rounded-md border px-4 text-sm font-medium hover:bg-muted"
                                @click="confirmingClose = false"
                            >
                                Voltar
                            </button>
                        </div>
                    </form>
                </section>
            </div>

            <section
                class="overflow-hidden rounded-xl border bg-card text-card-foreground"
                aria-labelledby="cash-movements-title"
            >
                <div class="border-b px-5 py-4 sm:px-6">
                    <h2 id="cash-movements-title" class="font-semibold">
                        Movimentações do turno
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Últimas vendas, estornos e retiradas do caixa aberto.
                    </p>
                </div>
                <ul v-if="props.cashMovements.length" class="divide-y">
                    <li
                        v-for="movement in props.cashMovements"
                        :key="movement.id"
                        class="flex items-center justify-between gap-3 px-5 py-3 sm:px-6"
                    >
                        <div class="min-w-0">
                            <p class="text-sm font-medium">
                                {{ movementLabel(movement.type) }}
                                <span class="font-normal text-muted-foreground"
                                    >· {{ movement.method }}</span
                                >
                            </p>
                            <p
                                class="mt-0.5 truncate text-xs text-muted-foreground"
                            >
                                {{ movement.description }} ·
                                {{ movement.operator }} ·
                                {{ movement.occurredAt }}
                            </p>
                        </div>
                        <strong
                            class="shrink-0 text-sm tabular-nums"
                            :class="
                                Number(movement.amount) < 0
                                    ? 'text-destructive'
                                    : ''
                            "
                            >{{ formatCurrency(movement.amount) }}</strong
                        >
                    </li>
                </ul>
                <p
                    v-else
                    class="px-5 py-8 text-center text-sm text-muted-foreground sm:px-6"
                >
                    Nenhuma movimentação registrada neste turno.
                </p>
            </section>
        </template>

        <section
            class="overflow-hidden rounded-xl border bg-card text-card-foreground"
            aria-labelledby="recent-sales-title"
        >
            <div class="border-b px-5 py-4 sm:px-6">
                <h2 id="recent-sales-title" class="font-semibold">
                    Vendas recentes
                </h2>
                <p
                    v-if="canReverseSales"
                    class="mt-1 text-sm text-muted-foreground"
                >
                    Estorno integral com motivo obrigatório. É lançado no seu
                    turno aberto, no mesmo meio de pagamento.
                </p>
            </div>
            <div
                v-if="props.recentSales.length === 0"
                class="px-5 py-12 text-center sm:px-6"
            >
                <p class="font-medium">Nenhuma venda recente</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    As vendas registradas aparecerão aqui.
                </p>
            </div>
            <ul v-else class="divide-y">
                <li
                    v-for="sale in props.recentSales"
                    :key="sale.id"
                    class="px-5 py-4 sm:px-6"
                >
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="min-w-0">
                            <p
                                class="flex flex-wrap items-center gap-2 font-medium"
                            >
                                Venda #{{ sale.id }}
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="
                                        isRefunded(sale)
                                            ? 'bg-warning/15 text-warning'
                                            : 'bg-success/10 text-success'
                                    "
                                    >{{
                                        isRefunded(sale)
                                            ? 'Estornada'
                                            : 'Finalizada'
                                    }}</span
                                >
                            </p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{
                                    formatDateTime(
                                        sale.occurred_at ?? sale.created_at,
                                    )
                                }}<span v-if="sale.payment_method">
                                    · {{ sale.payment_method }}</span
                                ><span v-if="sale.operator_name">
                                    · {{ sale.operator_name }}</span
                                >
                            </p>
                            <p
                                v-if="sale.items?.length"
                                class="mt-1 text-sm text-muted-foreground"
                            >
                                {{
                                    sale.items
                                        .map(
                                            (item) =>
                                                `${item.quantity} × ${item.product_name ?? item.name ?? 'Produto'}`,
                                        )
                                        .join(', ')
                                }}
                            </p>
                        </div>
                        <div
                            class="flex shrink-0 flex-row items-center justify-between gap-3 sm:flex-col sm:items-end"
                        >
                            <p
                                class="font-semibold tabular-nums"
                                :class="
                                    isRefunded(sale)
                                        ? 'text-muted-foreground line-through'
                                        : ''
                                "
                            >
                                {{
                                    formatCurrency(
                                        sale.total ?? sale.total_amount ?? 0,
                                    )
                                }}
                            </p>
                            <template
                                v-if="canReverseSales && !isRefunded(sale)"
                            >
                                <button
                                    v-if="
                                        props.currentSession &&
                                        refundingSaleId !== sale.id
                                    "
                                    type="button"
                                    class="min-h-9 rounded-md border border-destructive/40 px-3 text-sm font-medium text-destructive hover:bg-destructive/5"
                                    @click="startRefund(sale.id)"
                                >
                                    Estornar
                                </button>
                                <p
                                    v-else-if="!props.currentSession"
                                    class="text-xs text-muted-foreground"
                                >
                                    Abra seu turno para estornar.
                                </p>
                            </template>
                        </div>
                    </div>
                    <form
                        v-if="refundingSaleId === sale.id"
                        class="mt-3 grid gap-2 rounded-lg border border-destructive/30 bg-destructive/5 p-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end"
                        @submit.prevent="submitRefund(sale.id)"
                    >
                        <label
                            class="grid gap-1 text-sm font-medium"
                            :for="`refund-reason-${sale.id}`"
                            >Motivo do estorno da venda #{{ sale.id }}
                            <input
                                :id="`refund-reason-${sale.id}`"
                                v-model="refundForm.reason"
                                required
                                maxlength="1000"
                                autocomplete="off"
                                placeholder="Ex.: cliente desistiu da compra"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            />
                            <span
                                v-if="refundForm.errors.reason"
                                class="font-normal text-destructive"
                                >{{ refundForm.errors.reason }}</span
                            >
                        </label>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="min-h-10 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted"
                                @click="cancelRefund"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                :disabled="refundForm.processing"
                                class="min-h-10 rounded-md bg-destructive px-3 text-sm font-semibold text-white hover:bg-destructive/90 disabled:opacity-50"
                            >
                                {{
                                    refundForm.processing
                                        ? 'Estornando…'
                                        : `Estornar ${formatCurrency(sale.total ?? sale.total_amount ?? 0)}`
                                }}
                            </button>
                        </div>
                    </form>
                </li>
            </ul>
        </section>
    </main>
</template>
