<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { TriangleAlert } from '@lucide/vue';
import { index as productsIndex } from '@/routes/products';

type StockProduct = {
    id: number;
    name: string;
    barcode: string | null;
    active: boolean;
    tracks_stock: boolean;
    stock_quantity: number | null;
    low_stock_threshold: number | null;
    low_stock: boolean;
};

const props = defineProps<{
    products: StockProduct[];
    summary: { tracked: number; untracked: number; lowStock: number };
}>();

const lowStockProducts = computed(() => props.products.filter((product) => product.low_stock));
</script>

<template>
    <Head title="Estoque" />
    <main class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-muted-foreground">CATÁLOGO</p>
                <h1 class="mt-1 text-3xl font-semibold tracking-tight">Estoque</h1>
                <p class="mt-2 text-sm text-muted-foreground">Quantidade atual dos produtos. As vendas finalizadas no caixa dão baixa automaticamente.</p>
            </div>
            <Link :href="productsIndex()" class="inline-flex min-h-11 items-center rounded-md border px-4 text-sm font-medium hover:bg-muted">Ajustar no cadastro de produtos</Link>
        </header>

        <section
            v-if="lowStockProducts.length > 0"
            role="alert"
            class="flex gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200"
        >
            <TriangleAlert class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <div>
                <p class="font-semibold">{{ lowStockProducts.length === 1 ? '1 produto com estoque baixo' : `${lowStockProducts.length} produtos com estoque baixo` }}</p>
                <p class="mt-1 text-sm">{{ lowStockProducts.map((product) => `${product.name} (${product.stock_quantity})`).join(', ') }}</p>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-3" aria-label="Resumo do estoque">
            <div class="rounded-xl border bg-card p-5 text-card-foreground">
                <p class="text-sm text-muted-foreground">Com estoque controlado</p>
                <p class="mt-2 text-2xl font-semibold">{{ props.summary.tracked }}</p>
            </div>
            <div class="rounded-xl border bg-card p-5 text-card-foreground">
                <p class="text-sm text-muted-foreground">Estoque baixo ou zerado</p>
                <p class="mt-2 text-2xl font-semibold" :class="props.summary.lowStock > 0 ? 'text-amber-700 dark:text-amber-400' : ''">{{ props.summary.lowStock }}</p>
            </div>
            <div class="rounded-xl border bg-card p-5 text-card-foreground">
                <p class="text-sm text-muted-foreground">Sem controle de estoque</p>
                <p class="mt-2 text-2xl font-semibold">{{ props.summary.untracked }}</p>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border bg-card text-card-foreground" aria-labelledby="stock-list-title">
            <div class="border-b px-5 py-4 sm:px-6">
                <h2 id="stock-list-title" class="font-semibold">Produtos</h2>
            </div>
            <div v-if="props.products.length === 0" class="px-5 py-12 text-center sm:px-6">
                <p class="font-medium">Nenhum produto cadastrado</p>
                <p class="mt-1 text-sm text-muted-foreground">Cadastre produtos e ative o controle de estoque para acompanhá-los aqui.</p>
            </div>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[560px] text-left text-sm">
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-medium sm:px-6">Produto</th>
                            <th scope="col" class="px-5 py-3 font-medium">Estoque atual</th>
                            <th scope="col" class="px-5 py-3 font-medium">Alerta em</th>
                            <th scope="col" class="px-5 py-3 font-medium">Situação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="product in props.products" :key="product.id" class="border-t">
                            <th scope="row" class="px-5 py-4 font-medium sm:px-6">
                                {{ product.name }}
                                <span v-if="!product.active" class="ml-1 text-xs font-normal text-muted-foreground">(inativo)</span>
                            </th>
                            <td class="px-5 py-4 tabular-nums">{{ product.tracks_stock ? product.stock_quantity : '—' }}</td>
                            <td class="px-5 py-4 tabular-nums">{{ product.low_stock_threshold ?? '—' }}</td>
                            <td class="px-5 py-4">
                                <span v-if="!product.tracks_stock" class="text-muted-foreground">Não controlado</span>
                                <span v-else-if="product.stock_quantity === 0" class="font-semibold text-destructive">Sem estoque</span>
                                <span v-else-if="product.low_stock" class="font-semibold text-amber-700 dark:text-amber-400">Estoque baixo</span>
                                <span v-else class="text-emerald-700 dark:text-emerald-400">Normal</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</template>
