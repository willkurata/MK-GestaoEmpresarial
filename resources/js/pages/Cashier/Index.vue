<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import { open as openCash, close as closeCash, withdraw as withdrawCash } from '@/routes/pos';
import { store as storeSale } from '@/routes/pos/sales';
import { refund as refundSale } from '@/routes/pos/sales';

type Product = { id: number; name: string; barcode: string | null; sale_price: number | string; active: boolean };
type SaleItem = { product_id: number; quantity: number };
type CashSession = { id: number; opened_at?: string; opening_amount?: number | string };
type Sale = {
    id: number;
    total?: number | string;
    total_amount?: number | string;
    created_at?: string;
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

const props = defineProps<{
    products: Product[];
    currentSession: CashSession | null;
    cashAvailable: string;
    cashMovements: CashMovement[];
    cashHeldBy: string | null;
    recentSales: Sale[];
}>();
const page = usePage();
const canReverseSales = computed(() => Boolean((page.props.auth as { permissions?: { reverseSales?: boolean } }).permissions?.reverseSales));
const productSearch = ref('');
const productSearchMessage = ref('');
const productSearchInput = ref<HTMLInputElement | null>(null);
const showManualProductPicker = ref(false);
const manualProductSearch = ref('');
const manualProductSearchInput = ref<HTMLInputElement | null>(null);
const openingForm = useForm({ opening_amount: 0 });
const saleForm = useForm<{ items: SaleItem[]; discount_type: 'none' | 'fixed' | 'percentage'; discount_value: number; payment_method: 'Pix' | 'Dinheiro'; received_amount: number | null }>({
    items: [], discount_type: 'none', discount_value: 0, payment_method: 'Pix', received_amount: null,
});
const closeForm = useForm({ closing_amount: 0 });
const withdrawalForm = useForm<{ amount: number | null; description: string }>({ amount: null, description: '' });
const refundForm = useForm<{ reason: string }>({ reason: '' });
const refundingSaleId = ref<number | null>(null);
const activeProducts = computed(() => props.products.filter((product) => product.active));
const manualProductMatches = computed(() => {
    const query = manualProductSearch.value.trim().toLocaleLowerCase('pt-BR');

    return activeProducts.value
        .filter((product) => product.name.toLocaleLowerCase('pt-BR').includes(query))
        .slice(0, 12);
});
const matchingProducts = computed(() => {
    const query = productSearch.value.trim().toLocaleLowerCase('pt-BR');

    if (!query) {
        return [];
    }

    return activeProducts.value
        .filter((product) => product.name.toLocaleLowerCase('pt-BR').includes(query) || product.barcode?.toLocaleLowerCase('pt-BR').includes(query))
        .slice(0, 6);
});
const selectedProductCandidate = computed(() => {
    const query = productSearch.value.trim().toLocaleLowerCase('pt-BR');

    if (!query) {
        return null;
    }

    return activeProducts.value.find((product) => product.barcode?.toLocaleLowerCase('pt-BR') === query)
        ?? activeProducts.value.find((product) => product.name.toLocaleLowerCase('pt-BR') === query)
        ?? (matchingProducts.value.length === 1 ? matchingProducts.value[0] : null);
});

const subtotal = computed(() => saleForm.items.reduce((sum, item) => {
    const product = props.products.find((candidate) => candidate.id === item.product_id);
    return sum + (product ? Number(product.sale_price) * item.quantity : 0);
}, 0));
const discountAmount = computed(() => {
    if (saleForm.discount_type === 'percentage') {
        return subtotal.value * Math.min(Math.max(saleForm.discount_value, 0), 100) / 100;
    }
    if (saleForm.discount_type === 'fixed') {
        return Math.min(Math.max(saleForm.discount_value, 0), subtotal.value);
    }
    return 0;
});
const total = computed(() => Math.max(subtotal.value - discountAmount.value, 0));
const changeAmount = computed(() => Math.max((saleForm.received_amount ?? 0) - total.value, 0));
const formatCurrency = (amount: number | string): string => Number(amount).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

function addProduct(productId: number): void {
    if (!activeProducts.value.some((product) => product.id === productId)) {
        return;
    }
    const existingItem = saleForm.items.find((item) => item.product_id === productId);
    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        saleForm.items.push({ product_id: productId, quantity: 1 });
    }
    productSearch.value = '';
    productSearchMessage.value = '';
    void nextTick(() => productSearchInput.value?.focus());
}

function addManualProduct(productId: number): void {
    addProduct(productId);
    manualProductSearch.value = '';
    void nextTick(() => manualProductSearchInput.value?.focus());
}

function addSelectedProduct(): void {
    if (!selectedProductCandidate.value) {
        productSearchMessage.value = 'Nenhum produto ativo corresponde ao código ou à busca.';
        return;
    }

    addProduct(selectedProductCandidate.value.id);
}

function focusProductSearch(): void {
    void nextTick(() => productSearchInput.value?.focus());
}

function removeItem(productId: number): void {
    saleForm.items = saleForm.items.filter((item) => item.product_id !== productId);
}

function submitSale(): void {
    saleForm.post(storeSale().url, {
        preserveScroll: true,
        onSuccess: () => {
            saleForm.reset();
            focusProductSearch();
        },
    });
}

function openSession(): void {
    openingForm.post(openCash().url, { preserveScroll: true });
}

function closeSession(): void {
    closeForm.post(closeCash().url, { preserveScroll: true });
}

function submitWithdrawal(): void {
    withdrawalForm.post(withdrawCash().url, {
        preserveScroll: true,
        onSuccess: () => withdrawalForm.reset(),
    });
}

function movementLabel(type: string): string {
    return { venda: 'Venda', retirada: 'Retirada', estorno: 'Estorno' }[type] ?? type;
}

function submitRefund(saleId: number): void {
    refundingSaleId.value = saleId;
    refundForm.post(refundSale({ sale: saleId }).url, {
        preserveScroll: true,
        onSuccess: () => { refundForm.reset(); refundingSaleId.value = null; },
        onError: () => { refundingSaleId.value = null; },
    });
}

</script>

<template>
    <Head title="Caixa" />
    <main class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <header>
            <p class="text-sm font-medium text-muted-foreground">VENDAS</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight">Caixa</h1>
            <p class="mt-2 text-sm text-muted-foreground">Abra o turno para registrar vendas e acompanhe as movimentações recentes.</p>
        </header>

        <section v-if="props.cashHeldBy" class="rounded-xl border bg-card p-5 text-card-foreground sm:p-6" role="status">
            <h2 class="text-lg font-semibold">Caixa aberto por {{ props.cashHeldBy }}</h2>
            <p class="mt-1 text-sm text-muted-foreground">O operador atual deve fechar o turno antes que outro operador abra o caixa.</p>
        </section>

        <section v-else-if="!props.currentSession" class="rounded-xl border bg-card p-5 text-card-foreground sm:p-6" aria-labelledby="open-session-title">
            <h2 id="open-session-title" class="text-lg font-semibold">Abrir caixa</h2>
            <p class="mt-1 text-sm text-muted-foreground">Informe o valor inicial disponível no caixa.</p>
            <form class="mt-4 flex flex-col gap-4 sm:max-w-md sm:flex-row sm:items-end" @submit.prevent="openSession">
                <label class="grid flex-1 gap-2 text-sm font-medium" for="opening-amount">Valor de abertura (R$)
                    <input id="opening-amount" v-model.number="openingForm.opening_amount" type="number" min="0" step="0.01" inputmode="decimal" required class="h-11 rounded-md border border-input bg-background px-3 font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                    <span v-if="openingForm.errors.opening_amount" class="font-normal text-destructive">{{ openingForm.errors.opening_amount }}</span>
                </label>
                <button type="submit" :disabled="openingForm.processing" class="min-h-11 rounded-md bg-primary px-5 text-sm font-semibold text-primary-foreground disabled:opacity-60">{{ openingForm.processing ? 'Abrindo…' : 'Abrir caixa' }}</button>
            </form>
        </section>

        <template v-else>
            <section class="flex flex-col justify-between gap-4 rounded-xl border bg-card p-5 text-card-foreground sm:flex-row sm:items-center sm:p-6" aria-labelledby="session-title">
                <div>
                    <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Caixa aberto</p>
                    <h2 id="session-title" class="mt-1 text-lg font-semibold">Turno em andamento</h2>
                    <p v-if="props.currentSession.opened_at" class="mt-1 text-sm text-muted-foreground">Aberto em {{ new Date(props.currentSession.opened_at).toLocaleString('pt-BR') }}</p>
                </div>
                <form class="flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="closeSession">
                    <label class="grid gap-2 text-sm font-medium" for="closing-amount">Valor contado no fechamento (R$)
                        <input id="closing-amount" v-model.number="closeForm.closing_amount" type="number" min="0" step="0.01" inputmode="decimal" required class="h-11 rounded-md border border-input bg-background px-3 font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                        <span v-if="closeForm.errors.closing_amount" class="font-normal text-destructive">{{ closeForm.errors.closing_amount }}</span>
                    </label>
                    <button type="submit" :disabled="closeForm.processing" class="min-h-11 rounded-md border px-4 text-sm font-semibold hover:bg-muted disabled:opacity-60">{{ closeForm.processing ? 'Fechando…' : 'Fechar caixa' }}</button>
                </form>
            </section>

            <section class="rounded-xl border bg-card p-5 text-card-foreground sm:p-6" aria-labelledby="withdrawal-title">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="withdrawal-title" class="text-lg font-semibold">Retirada de dinheiro</h2>
                        <p class="mt-1 text-sm text-muted-foreground">Registre o motivo e o valor. A retirada reduz o dinheiro esperado no fechamento.</p>
                    </div>
                    <p class="rounded-lg bg-muted/50 px-3 py-2 text-sm">Disponível no caixa: <strong>{{ formatCurrency(props.cashAvailable) }}</strong></p>
                </div>
                <form class="mt-4 grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(12rem,0.5fr)_auto] sm:items-end" @submit.prevent="submitWithdrawal">
                    <label class="grid gap-2 text-sm font-medium" for="withdrawal-description">Motivo da retirada
                        <input id="withdrawal-description" v-model="withdrawalForm.description" required maxlength="255" autocomplete="off" placeholder="Ex.: pagamento de entrega" class="h-11 rounded-md border border-input bg-background px-3 font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                        <span v-if="withdrawalForm.errors.description" class="font-normal text-destructive">{{ withdrawalForm.errors.description }}</span>
                    </label>
                    <label class="grid gap-2 text-sm font-medium" for="withdrawal-amount">Valor (R$)
                        <input id="withdrawal-amount" v-model.number="withdrawalForm.amount" type="number" min="0.01" :max="Number(props.cashAvailable)" step="0.01" inputmode="decimal" required class="h-11 rounded-md border border-input bg-background px-3 font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                        <span v-if="withdrawalForm.errors.amount" class="font-normal text-destructive">{{ withdrawalForm.errors.amount }}</span>
                    </label>
                    <button type="submit" :disabled="withdrawalForm.processing || Number(props.cashAvailable) <= 0" class="min-h-11 rounded-md border px-4 text-sm font-semibold hover:bg-muted disabled:opacity-50">
                        {{ withdrawalForm.processing ? 'Registrando…' : 'Registrar retirada' }}
                    </button>
                </form>
            </section>

            <section class="overflow-hidden rounded-xl border bg-card text-card-foreground" aria-labelledby="cash-movements-title">
                <div class="border-b px-5 py-4 sm:px-6">
                    <h2 id="cash-movements-title" class="font-semibold">Movimentações do turno</h2>
                    <p class="mt-1 text-sm text-muted-foreground">Vendas, estornos e retiradas registradas no caixa aberto.</p>
                </div>
                <ul v-if="props.cashMovements.length" class="divide-y">
                    <li v-for="movement in props.cashMovements" :key="movement.id" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 sm:px-6">
                        <div class="min-w-0">
                            <p class="font-medium">{{ movementLabel(movement.type) }} <span class="font-normal text-muted-foreground">· {{ movement.method }}</span></p>
                            <p class="mt-1 truncate text-sm text-muted-foreground">{{ movement.description }} · {{ movement.operator }} · {{ movement.occurredAt }}</p>
                        </div>
                        <strong class="shrink-0 text-sm" :class="Number(movement.amount) < 0 ? 'text-destructive' : ''">{{ formatCurrency(movement.amount) }}</strong>
                    </li>
                </ul>
                <p v-else class="px-5 py-8 text-center text-sm text-muted-foreground sm:px-6">Nenhuma movimentação registrada neste turno.</p>
            </section>

            <section class="rounded-xl border bg-card p-5 text-card-foreground sm:p-6" aria-labelledby="new-sale-title">
                <h2 id="new-sale-title" class="text-lg font-semibold">Registrar venda</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
                    <label class="grid gap-2 text-sm font-medium" for="product-search">Produto ou código de barras
                        <input id="product-search" ref="productSearchInput" v-model="productSearch" v-focus autocomplete="off" placeholder="Digite ou leia o código de barras" class="h-11 min-w-0 rounded-md border border-input bg-background px-3 text-sm font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring" @input="productSearchMessage = ''" @keydown.enter.prevent="addSelectedProduct" />
                    </label>
                    <button type="button" :disabled="!selectedProductCandidate" class="min-h-11 self-end rounded-md border px-4 text-sm font-medium hover:bg-muted disabled:opacity-50" @click="addSelectedProduct">Adicionar produto</button>
                </div>
                <ul v-if="matchingProducts.length > 0" class="mt-2 divide-y rounded-lg border" aria-label="Produtos encontrados">
                    <li v-for="product in matchingProducts" :key="product.id">
                        <button type="button" class="flex min-h-12 w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-muted" @click="addProduct(product.id)">
                            <span class="min-w-0 truncate font-medium">{{ product.name }}<span v-if="product.barcode" class="ml-2 font-mono text-xs text-muted-foreground">{{ product.barcode }}</span></span>
                            <span class="shrink-0">{{ formatCurrency(product.sale_price) }}</span>
                        </button>
                    </li>
                </ul>
                <p v-if="productSearchMessage" class="mt-2 text-sm text-destructive" role="status">{{ productSearchMessage }}</p>
                <p v-if="activeProducts.length === 0" class="mt-3 text-sm text-muted-foreground">Nenhum produto ativo disponível. Cadastre ou ative produtos para começar.</p>
                <div v-else class="mt-3 rounded-lg bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
                    Sem leitor? Abra a busca manual abaixo e escolha o produto pelo nome.
                </div>
                <div v-if="activeProducts.length > 0" class="mt-3">
                    <button type="button" class="min-h-10 rounded-md border px-3 text-sm font-medium hover:bg-muted" :aria-expanded="showManualProductPicker" aria-controls="manual-product-picker" @click="showManualProductPicker = !showManualProductPicker">
                        {{ showManualProductPicker ? 'Fechar busca manual' : 'Adicionar produto manualmente' }}
                    </button>
                    <section v-if="showManualProductPicker" id="manual-product-picker" class="mt-3 rounded-xl border p-4" aria-label="Adicionar produto manualmente">
                        <label class="grid gap-2 text-sm font-medium" for="manual-product-search">Buscar pelo nome
                            <input id="manual-product-search" ref="manualProductSearchInput" v-model="manualProductSearch" v-focus autocomplete="off" placeholder="Ex.: café, bolo, suco" class="h-11 rounded-md border border-input bg-background px-3 text-sm font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                        </label>
                        <p class="mt-2 text-xs text-muted-foreground">Escolha um produto para adicioná-lo à venda. O leitor de código é opcional.</p>
                        <div v-if="manualProductMatches.length > 0" class="mt-3 grid max-h-72 gap-2 overflow-y-auto sm:grid-cols-2">
                            <button v-for="product in manualProductMatches" :key="product.id" type="button" class="flex min-h-14 items-center justify-between gap-3 rounded-lg border bg-card px-3 py-2 text-left text-sm hover:border-primary/50 hover:bg-muted/50" @click="addManualProduct(product.id)">
                                <span class="min-w-0 truncate font-medium">{{ product.name }}</span>
                                <span class="shrink-0 text-muted-foreground">{{ formatCurrency(product.sale_price) }}</span>
                            </button>
                        </div>
                        <p v-else class="mt-3 rounded-lg border border-dashed px-3 py-5 text-center text-sm text-muted-foreground">Nenhum produto corresponde a essa busca.</p>
                        <p v-if="!manualProductSearch && activeProducts.length > manualProductMatches.length" class="mt-2 text-xs text-muted-foreground">Mostrando 12 produtos. Digite parte do nome para encontrar os demais.</p>
                    </section>
                </div>

                <form class="mt-5 space-y-5" @submit.prevent="submitSale">
                    <div v-if="saleForm.items.length === 0" class="rounded-lg border border-dashed px-4 py-8 text-center">
                        <p class="font-medium">Nenhum item na venda</p>
                        <p class="mt-1 text-sm text-muted-foreground">Selecione um produto acima para adicioná-lo.</p>
                    </div>
                    <div v-else class="overflow-x-auto rounded-lg border">
                        <table class="w-full min-w-[520px] text-left text-sm">
                            <thead class="bg-muted/50 text-muted-foreground"><tr><th scope="col" class="px-4 py-3 font-medium">Produto</th><th scope="col" class="px-4 py-3 font-medium">Preço unitário</th><th scope="col" class="w-28 px-4 py-3 font-medium">Quantidade</th><th scope="col" class="px-4 py-3 font-medium">Total</th><th scope="col" class="px-4 py-3"><span class="sr-only">Remover</span></th></tr></thead>
                            <tbody>
                                <tr v-for="(item, index) in saleForm.items" :key="item.product_id" class="border-t">
                                    <th scope="row" class="px-4 py-3 font-medium">{{ props.products.find((product) => product.id === item.product_id)?.name }}</th>
                                    <td class="px-4 py-3">{{ formatCurrency(props.products.find((product) => product.id === item.product_id)?.sale_price ?? 0) }}</td>
                                    <td class="px-4 py-3"><label class="sr-only" :for="`quantity-${item.product_id}`">Quantidade de {{ props.products.find((product) => product.id === item.product_id)?.name }}</label><input :id="`quantity-${item.product_id}`" v-model.number="saleForm.items[index].quantity" type="number" min="1" step="1" required class="h-10 w-24 rounded-md border border-input bg-background px-2 outline-none focus-visible:ring-2 focus-visible:ring-ring" /></td>
                                    <td class="px-4 py-3">{{ formatCurrency((Number(props.products.find((product) => product.id === item.product_id)?.sale_price ?? 0)) * item.quantity) }}</td>
                                    <td class="px-4 py-3 text-right"><button type="button" class="rounded px-2 py-1 text-sm text-destructive underline-offset-4 hover:underline" :aria-label="`Remover ${props.products.find((product) => product.id === item.product_id)?.name}`" @click="removeItem(item.product_id)">Remover</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-if="saleForm.errors.items" class="text-sm text-destructive">{{ saleForm.errors.items }}</p>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <label class="grid gap-2 text-sm font-medium" for="discount-type">Desconto
                            <select id="discount-type" v-model="saleForm.discount_type" class="h-11 rounded-md border border-input bg-background px-3 font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring"><option value="none">Sem desconto</option><option value="fixed">Valor fixo (R$)</option><option value="percentage">Percentual (%)</option></select>
                        </label>
                        <label v-if="saleForm.discount_type !== 'none'" class="grid gap-2 text-sm font-medium" for="discount-value">{{ saleForm.discount_type === 'percentage' ? 'Desconto (%)' : 'Desconto (R$)' }}
                            <input id="discount-value" v-model.number="saleForm.discount_value" type="number" min="0" :max="saleForm.discount_type === 'percentage' ? 100 : undefined" :step="saleForm.discount_type === 'percentage' ? 1 : 0.01" inputmode="decimal" class="h-11 rounded-md border border-input bg-background px-3 font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                            <span v-if="saleForm.errors.discount_value" class="font-normal text-destructive">{{ saleForm.errors.discount_value }}</span>
                        </label>
                        <label class="grid gap-2 text-sm font-medium" for="payment-method">Forma de pagamento
                            <select id="payment-method" v-model="saleForm.payment_method" class="h-11 rounded-md border border-input bg-background px-3 font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring"><option value="Pix">Pix</option><option value="Dinheiro">Dinheiro</option></select>
                        </label>
                        <label v-if="saleForm.payment_method === 'Dinheiro'" class="grid gap-2 text-sm font-medium" for="received-amount">Valor recebido (R$)
                            <input id="received-amount" v-model.number="saleForm.received_amount" type="number" min="0" step="0.01" inputmode="decimal" required class="h-11 rounded-md border border-input bg-background px-3 font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                            <span v-if="saleForm.errors.received_amount" class="font-normal text-destructive">{{ saleForm.errors.received_amount }}</span>
                        </label>
                    </div>
                    <div class="flex flex-col gap-4 rounded-lg bg-muted/40 p-4 sm:flex-row sm:items-end sm:justify-between">
                        <dl class="grid grid-cols-2 gap-x-8 gap-y-1 text-sm"><dt class="text-muted-foreground">Subtotal</dt><dd class="text-right font-medium">{{ formatCurrency(subtotal) }}</dd><dt class="text-muted-foreground">Desconto</dt><dd class="text-right font-medium">{{ formatCurrency(discountAmount) }}</dd><dt class="font-semibold">Total</dt><dd class="text-right text-lg font-semibold">{{ formatCurrency(total) }}</dd><template v-if="saleForm.payment_method === 'Dinheiro'"><dt class="text-muted-foreground">Troco</dt><dd class="text-right font-medium">{{ formatCurrency(changeAmount) }}</dd></template></dl>
                        <button type="submit" :disabled="saleForm.processing || saleForm.items.length === 0 || (saleForm.payment_method === 'Dinheiro' && (saleForm.received_amount ?? 0) < total)" class="min-h-11 rounded-md bg-primary px-5 text-sm font-semibold text-primary-foreground disabled:opacity-50">{{ saleForm.processing ? 'Registrando…' : 'Finalizar venda' }}</button>
                    </div>
                    <p v-if="saleForm.errors.payment_method" class="text-sm text-destructive">{{ saleForm.errors.payment_method }}</p>
                </form>
            </section>
        </template>

        <section class="overflow-hidden rounded-xl border bg-card text-card-foreground" aria-labelledby="recent-sales-title">
            <div class="border-b px-5 py-4 sm:px-6"><h2 id="recent-sales-title" class="font-semibold">Vendas recentes</h2></div>
            <div v-if="props.recentSales.length === 0" class="px-5 py-12 text-center sm:px-6"><p class="font-medium">Nenhuma venda recente</p><p class="mt-1 text-sm text-muted-foreground">As vendas registradas aparecerão aqui.</p></div>
            <ul v-else class="divide-y">
                <li v-for="sale in props.recentSales" :key="sale.id" class="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                    <div class="min-w-0">
                        <p class="font-medium">Venda #{{ sale.id }} <span v-if="sale.status" class="ml-2 text-xs text-muted-foreground">{{ sale.status }}</span></p>
                        <p v-if="sale.created_at" class="mt-1 text-sm text-muted-foreground">{{ new Date(sale.created_at).toLocaleString('pt-BR') }}<span v-if="sale.payment_method"> · {{ sale.payment_method }}</span><span v-if="sale.operator_name"> · {{ sale.operator_name }}</span></p>
                        <p v-if="sale.items?.length" class="mt-2 text-sm text-muted-foreground">{{ sale.items.map((item) => `${item.quantity} × ${item.product_name ?? item.name ?? 'Produto'}`).join(', ') }}</p>
                    </div>
                    <div class="flex shrink-0 flex-col gap-3 sm:items-end">
                        <p class="font-semibold">{{ formatCurrency(sale.total ?? sale.total_amount ?? 0) }}</p>
                        <form v-if="canReverseSales && props.currentSession && !sale.refunded_at && sale.status !== 'estornada'" class="flex flex-col gap-2 sm:items-end" @submit.prevent="submitRefund(sale.id)">
                            <label class="grid gap-1 text-xs font-medium" :for="`refund-reason-${sale.id}`">Motivo do estorno
                                <input :id="`refund-reason-${sale.id}`" v-model="refundForm.reason" required maxlength="1000" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring sm:w-64" />
                            </label>
                            <span v-if="refundForm.errors.reason" class="text-sm text-destructive">{{ refundForm.errors.reason }}</span>
                            <button type="submit" :disabled="refundForm.processing && refundingSaleId === sale.id" class="min-h-10 rounded-md border border-destructive/40 px-3 text-sm font-medium text-destructive hover:bg-destructive/5 disabled:opacity-50">{{ refundForm.processing && refundingSaleId === sale.id ? 'Estornando…' : 'Estornar venda completa' }}</button>
                        </form>
                        <p v-else-if="canReverseSales && !props.currentSession && !sale.refunded_at && sale.status !== 'estornada'" class="text-sm text-muted-foreground">Abra seu turno de caixa para estornar esta venda.</p>
                        <p v-else-if="sale.refunded_at || sale.status === 'estornada'" class="text-sm text-muted-foreground">Venda estornada</p>
                    </div>
                </li>
            </ul>
        </section>
    </main>
</template>
