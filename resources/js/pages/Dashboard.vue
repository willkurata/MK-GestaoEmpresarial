<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { dashboard } from '@/routes';
import { index as cashierIndex } from '@/routes/pos';
import { index as productsIndex } from '@/routes/products';
import { index as expensesIndex } from '@/routes/expenses';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

defineProps<{
    company: { name: string };
    companyUser: { name: string; role: string };
}>();

const periods = ['Hoje', '7 dias', 'Este mês', 'Mês anterior'];
const selectedPeriod = ref('Hoje');
const metrics = [
    { label: 'Vendas no período', description: 'Acompanhe suas vendas' },
    { label: 'Despesas no período', description: 'Fixas e variáveis' },
    { label: 'Vendas em dinheiro', description: 'Recebimentos em espécie' },
    { label: 'Vendas via Pix', description: 'Recebimentos confirmados' },
];
const quickActions = [
    { title: 'Iniciar uma venda', description: 'Abrir o PDV e registrar uma venda', href: cashierIndex(), primary: true },
    { title: 'Produtos', description: 'Cadastrar preços e códigos de barras', href: productsIndex(), primary: false },
    { title: 'Despesas', description: 'Registrar uma despesa', href: expensesIndex(), primary: false },
];
</script>

<template>
    <Head title="Dashboard" />

    <main class="min-h-full bg-slate-50/70 px-4 py-6 text-slate-900 sm:px-6 lg:px-8 dark:bg-slate-950 dark:text-slate-100">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
            <header class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                <div>
                    <p class="text-sm font-semibold tracking-wide text-blue-700 dark:text-blue-400">MK GESTÃO</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">Visão geral</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ company.name }} · {{ companyUser.name }} ({{ companyUser.role }})</p>
                </div>
                <div class="flex flex-wrap gap-2" aria-label="Período do dashboard">
                    <button
                        v-for="period in periods"
                        :key="period"
                        type="button"
                        class="rounded-lg border px-3 py-2 text-sm font-medium transition-colors"
                        :class="selectedPeriod === period ? 'border-blue-700 bg-blue-700 text-white shadow-sm' : 'border-slate-200 bg-white text-slate-600 hover:border-blue-200 hover:text-blue-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300'"
                        :aria-pressed="selectedPeriod === period"
                        @click="selectedPeriod = period"
                    >
                        {{ period }}
                    </button>
                </div>
            </header>

            <section aria-label="Acesso rápido">
                <div class="mb-3 flex items-end justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">Acesso rápido</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Atalhos para as tarefas do dia a dia.</p>
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <Link v-for="action in quickActions" :key="action.title" :href="action.href" class="group flex min-h-24 items-center justify-between gap-4 rounded-xl border p-4 shadow-sm transition-colors" :class="action.primary ? 'border-blue-700 bg-blue-700 text-white hover:bg-blue-800' : 'border-slate-200 bg-white text-slate-900 hover:border-blue-200 hover:bg-blue-50/50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800'">
                        <span>
                            <span class="block font-semibold">{{ action.title }}</span>
                            <span class="mt-1 block text-xs" :class="action.primary ? 'text-blue-100' : 'text-slate-500 dark:text-slate-400'">{{ action.description }}</span>
                        </span>
                        <span class="text-xl" aria-hidden="true">→</span>
                    </Link>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Indicadores do período">
                <article v-for="metric in metrics" :key="metric.label" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/40 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-slate-600 dark:text-slate-300">{{ metric.label }}</p>
                            <p class="mt-4 text-3xl font-semibold tracking-tight text-slate-400 dark:text-slate-500" aria-label="Sem dados">—</p>
                        </div>
                        <span class="grid size-10 place-items-center rounded-xl bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" class="size-5" stroke="currentColor" stroke-width="1.8">
                                <path d="M4 19V5m0 14h16M8 15l3-4 3 2 5-7" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                    </div>
                    <p class="mt-3 text-xs text-slate-400 dark:text-slate-500">{{ metric.description }}</p>
                </article>
            </section>

            <section class="grid gap-6 xl:grid-cols-[1.6fr_1fr]">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/40 sm:p-6 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="font-semibold">Vendas</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Resumo do período selecionado</p>
                        </div>
                        <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 dark:bg-blue-950/60 dark:text-blue-300">Total <span class="ml-1">—</span></span>
                    </div>
                    <div class="mt-6 flex min-h-56 flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/70 px-6 py-8 text-center dark:border-slate-800 dark:bg-slate-950/50">
                        <span class="grid size-12 place-items-center rounded-2xl bg-white text-blue-700 shadow-sm dark:bg-slate-900 dark:text-blue-300" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" class="size-6" stroke="currentColor" stroke-width="1.7">
                                <path d="M4 19V5m0 14h16M7 15l4-4 3 2 5-6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <h3 class="mt-4 text-sm font-semibold">Nenhuma venda neste período</h3>
                        <p class="mt-1 max-w-sm text-sm leading-6 text-slate-500 dark:text-slate-400">Quando houver vendas registradas, a evolução aparecerá aqui.</p>
                    </div>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <div class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3 dark:bg-slate-950/60">
                            <span class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300"><span class="size-2.5 rounded-full bg-blue-600"></span>Dinheiro</span>
                            <span class="text-sm font-semibold text-slate-400">—</span>
                        </div>
                        <div class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3 dark:bg-slate-950/60">
                            <span class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300"><span class="size-2.5 rounded-full bg-sky-300"></span>Pix</span>
                            <span class="text-sm font-semibold text-slate-400">—</span>
                        </div>
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/40 sm:p-6 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
                    <div>
                        <h2 class="font-semibold">Despesas</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Distribuição por tipo</p>
                    </div>
                    <div class="mt-6 flex min-h-56 flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/70 px-5 py-8 text-center dark:border-slate-800 dark:bg-slate-950/50">
                        <span class="grid size-12 place-items-center rounded-2xl bg-white text-blue-700 shadow-sm dark:bg-slate-900 dark:text-blue-300" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" class="size-6" stroke="currentColor" stroke-width="1.7">
                                <path d="M12 3v9h9M20.5 15a8.5 8.5 0 1 1-8.5-11.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <h3 class="mt-4 text-sm font-semibold">Sem despesas para exibir</h3>
                        <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">Os lançamentos do período aparecerão neste resumo.</p>
                    </div>
                    <div class="mt-5 space-y-3">
                        <div class="flex items-center justify-between text-sm"><span class="text-slate-600 dark:text-slate-300">Fixas</span><span class="font-semibold text-slate-400">—</span></div>
                        <div class="flex items-center justify-between text-sm"><span class="text-slate-600 dark:text-slate-300">Variáveis</span><span class="font-semibold text-slate-400">—</span></div>
                    </div>
                </article>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-200/40 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4 sm:px-6 dark:border-slate-800">
                    <div>
                        <h2 class="font-semibold">Atividade recente</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Últimos movimentos registrados</p>
                    </div>
                    <span class="text-xs font-medium text-slate-400">{{ selectedPeriod }}</span>
                </div>
                <div class="flex flex-col items-center justify-center px-6 py-10 text-center">
                    <span class="grid size-11 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" class="size-5" stroke="currentColor" stroke-width="1.8">
                            <path d="M12 8v4l2.5 1.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <h3 class="mt-3 text-sm font-semibold">Tudo tranquilo por aqui</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">As vendas e despesas recentes serão listadas quando houver registros.</p>
                </div>
            </section>
        </div>
    </main>
</template>
