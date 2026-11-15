<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { store as storeProduct, update as updateProduct } from '@/routes/products';

type Product = {
    id: number;
    name: string;
    barcode: string | null;
    sale_price: number | string;
    cost: number | string | null;
    stock_quantity: number | null;
    low_stock_threshold: number | null;
    low_stock: boolean;
    active: boolean;
};

type ProductFields = {
    name: string;
    barcode: string;
    sale_price: number | null;
    cost: number | null;
    tracks_stock: boolean;
    stock_quantity: number | null;
    low_stock_threshold: number | null;
    active: boolean;
};

const props = defineProps<{ products: Product[] }>();
const editingProductId = ref<number | null>(null);
const form = useForm<ProductFields>({
    name: '',
    barcode: '',
    sale_price: null,
    cost: null,
    tracks_stock: false,
    stock_quantity: null,
    low_stock_threshold: null,
    active: true,
}).transform((data) => ({
    ...data,
    stock_quantity: data.tracks_stock ? data.stock_quantity ?? 0 : null,
    low_stock_threshold: data.tracks_stock ? data.low_stock_threshold : null,
}));

function resetForm(): void {
    editingProductId.value = null;
    form.reset();
    form.clearErrors();
    form.active = true;
}

function editProduct(product: Product): void {
    editingProductId.value = product.id;
    form.name = product.name;
    form.barcode = product.barcode ?? '';
    form.sale_price = Number(product.sale_price);
    form.cost = product.cost === null ? null : Number(product.cost);
    form.tracks_stock = product.stock_quantity !== null;
    form.stock_quantity = product.stock_quantity;
    form.low_stock_threshold = product.low_stock_threshold;
    form.active = product.active;
    form.clearErrors();
}

function saveProduct(): void {
    if (editingProductId.value) {
        form.patch(updateProduct({ product: editingProductId.value }).url, { preserveScroll: true, onSuccess: resetForm });
        return;
    }

    form.post(storeProduct().url, { preserveScroll: true, onSuccess: resetForm });
}
</script>

<template>
    <Head title="Produtos" />
    <main class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <header>
            <p class="text-sm font-medium text-muted-foreground">CATÁLOGO</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight">Produtos</h1>
            <p class="mt-2 text-sm text-muted-foreground">Cadastre e mantenha os produtos disponíveis para venda.</p>
        </header>

        <section class="rounded-xl border bg-card p-5 text-card-foreground sm:p-6" aria-labelledby="product-form-title">
            <h2 id="product-form-title" class="text-lg font-semibold">{{ editingProductId ? 'Atualizar produto' : 'Novo produto' }}</h2>
            <form class="mt-5 grid gap-4 sm:grid-cols-2" @submit.prevent="saveProduct">
                <label class="grid gap-2 text-sm font-medium sm:col-span-2" for="product-name">
                    Nome
                    <input id="product-name" v-model="form.name" required maxlength="255" autocomplete="off" class="h-11 rounded-md border border-input bg-background px-3 font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                    <span v-if="form.errors.name" class="text-sm font-normal text-destructive">{{ form.errors.name }}</span>
                </label>
                <label class="grid gap-2 text-sm font-medium sm:col-span-2" for="product-barcode">
                    Código de barras <span class="font-normal text-muted-foreground">Opcional</span>
                    <input id="product-barcode" v-model="form.barcode" maxlength="64" autocomplete="off" inputmode="numeric" class="h-11 rounded-md border border-input bg-background px-3 font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                    <span v-if="form.errors.barcode" class="text-sm font-normal text-destructive">{{ form.errors.barcode }}</span>
                </label>
                <label class="grid gap-2 text-sm font-medium" for="product-sale-price">
                    Preço de venda (R$)
                    <input id="product-sale-price" v-model.number="form.sale_price" required type="number" min="0.01" step="0.01" inputmode="decimal" class="h-11 rounded-md border border-input bg-background px-3 font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                    <span v-if="form.errors.sale_price" class="text-sm font-normal text-destructive">{{ form.errors.sale_price }}</span>
                </label>
                <label class="grid gap-2 text-sm font-medium" for="product-cost">
                    Custo (R$) <span class="font-normal text-muted-foreground">Opcional</span>
                    <input id="product-cost" v-model.number="form.cost" type="number" min="0" step="0.01" inputmode="decimal" class="h-11 rounded-md border border-input bg-background px-3 font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                    <span v-if="form.errors.cost" class="text-sm font-normal text-destructive">{{ form.errors.cost }}</span>
                </label>
                <fieldset class="grid gap-4 rounded-lg border p-4 sm:col-span-2 sm:grid-cols-2">
                    <legend class="px-1 text-sm font-medium">Estoque</legend>
                    <label class="flex min-h-11 items-center gap-3 text-sm sm:col-span-2">
                        <input v-model="form.tracks_stock" type="checkbox" class="size-4 accent-primary" />
                        Controlar estoque deste produto
                    </label>
                    <p v-if="!form.tracks_stock" class="text-sm text-muted-foreground sm:col-span-2">Sem controle, o produto pode ser vendido sem limite de quantidade (ex.: itens preparados na hora).</p>
                    <template v-else>
                        <label class="grid gap-2 text-sm font-medium" for="product-stock-quantity">
                            Quantidade em estoque
                            <input id="product-stock-quantity" v-model.number="form.stock_quantity" required type="number" min="0" step="1" inputmode="numeric" class="h-11 rounded-md border border-input bg-background px-3 font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                            <span v-if="form.errors.stock_quantity" class="text-sm font-normal text-destructive">{{ form.errors.stock_quantity }}</span>
                        </label>
                        <label class="grid gap-2 text-sm font-medium" for="product-low-stock-threshold">
                            Alertar quando chegar a <span class="font-normal text-muted-foreground">Opcional</span>
                            <input id="product-low-stock-threshold" v-model.number="form.low_stock_threshold" type="number" min="0" step="1" inputmode="numeric" class="h-11 rounded-md border border-input bg-background px-3 font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                            <span v-if="form.errors.low_stock_threshold" class="text-sm font-normal text-destructive">{{ form.errors.low_stock_threshold }}</span>
                        </label>
                        <p class="text-xs text-muted-foreground sm:col-span-2">O estoque é reduzido automaticamente a cada venda finalizada no caixa. Estoque zerado sempre gera alerta.</p>
                    </template>
                </fieldset>
                <label class="flex min-h-11 items-center gap-3 text-sm sm:col-span-2">
                    <input v-model="form.active" type="checkbox" class="size-4 accent-primary" />
                    Produto ativo e disponível no caixa
                </label>
                <div class="flex flex-wrap gap-3 sm:col-span-2">
                    <button type="submit" :disabled="form.processing" class="min-h-11 rounded-md bg-primary px-5 text-sm font-semibold text-primary-foreground disabled:opacity-60">
                        {{ form.processing ? 'Salvando…' : editingProductId ? 'Salvar alterações' : 'Cadastrar produto' }}
                    </button>
                    <button v-if="editingProductId" type="button" class="min-h-11 rounded-md border px-5 text-sm font-medium hover:bg-muted" @click="resetForm">Cancelar edição</button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border bg-card text-card-foreground" aria-labelledby="products-list-title">
            <div class="border-b px-5 py-4 sm:px-6">
                <h2 id="products-list-title" class="font-semibold">Produtos cadastrados</h2>
            </div>
            <div v-if="props.products.length === 0" class="px-5 py-12 text-center sm:px-6">
                <p class="font-medium">Nenhum produto cadastrado</p>
                <p class="mt-1 text-sm text-muted-foreground">Cadastre o primeiro produto pelo formulário acima.</p>
            </div>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[700px] text-left text-sm">
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr><th scope="col" class="px-5 py-3 font-medium sm:px-6">Produto</th><th scope="col" class="px-5 py-3 font-medium">Código</th><th scope="col" class="px-5 py-3 font-medium">Preço</th><th scope="col" class="px-5 py-3 font-medium">Custo</th><th scope="col" class="px-5 py-3 font-medium">Estoque</th><th scope="col" class="px-5 py-3 font-medium">Status</th><th scope="col" class="px-5 py-3 text-right font-medium">Ação</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="product in props.products" :key="product.id" class="border-t">
                            <th scope="row" class="px-5 py-4 font-medium sm:px-6">{{ product.name }}</th>
                            <td class="px-5 py-4 font-mono text-xs">{{ product.barcode || '—' }}</td>
                            <td class="px-5 py-4">{{ Number(product.sale_price).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) }}</td>
                            <td class="px-5 py-4">{{ product.cost === null ? '—' : Number(product.cost).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) }}</td>
                            <td class="px-5 py-4">
                                <span v-if="product.stock_quantity === null" class="text-muted-foreground">Não controlado</span>
                                <span v-else :class="product.low_stock ? 'font-semibold text-amber-700 dark:text-amber-400' : ''">
                                    {{ product.stock_quantity }}<span v-if="product.low_stock" class="ml-1 text-xs">· baixo</span>
                                </span>
                            </td>
                            <td class="px-5 py-4"><span :class="product.active ? 'text-emerald-700 dark:text-emerald-400' : 'text-muted-foreground'">{{ product.active ? 'Ativo' : 'Inativo' }}</span></td>
                            <td class="px-5 py-4 text-right"><button type="button" class="min-h-9 rounded-md px-3 font-medium text-primary underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" @click="editProduct(product)">Editar</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</template>
