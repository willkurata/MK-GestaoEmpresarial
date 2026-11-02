<script setup lang="ts">
import { Head, Form, Link } from '@inertiajs/vue3';
import { logout } from '@/routes';
import { create as createCompany, show as showCompany } from '@/routes/mk/companies';

defineOptions({
    layout: {
        title: 'Administração MK',
        description: 'Visão master das empresas cadastradas.',
    },
});

defineProps<{
    companies: Array<{ id: number; cnpj: string; name: string; status: string; created_at: string }>;
}>();
</script>

<template>
    <Head title="Administração MK" />
    <main class="min-h-screen bg-slate-50 px-5 py-8 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <div class="mx-auto max-w-6xl">
            <header class="mb-8 flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold text-blue-700 dark:text-blue-400">MK GESTÃO · MASTER</p>
                    <h1 class="mt-2 text-3xl font-semibold">Empresas</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ companies.length }} empresa(s) cadastrada(s)</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="createCompany()" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Cadastrar empresa</Link>
                    <Form v-bind="logout.form()">
                        <button class="rounded-lg border px-4 py-2 text-sm">Sair</button>
                    </Form>
                </div>
            </header>
            <div class="overflow-hidden rounded-xl border bg-white dark:border-slate-800 dark:bg-slate-900">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        <tr><th class="px-5 py-3 font-medium">Empresa</th><th class="px-5 py-3 font-medium">CNPJ</th><th class="px-5 py-3 font-medium">Status</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="company in companies" :key="company.id" class="border-t dark:border-slate-800">
                            <td class="px-5 py-4 font-medium"><Link :href="showCompany({ company: company.id })" class="text-blue-700 hover:underline dark:text-blue-300">{{ company.name }}</Link></td>
                            <td class="px-5 py-4">{{ company.cnpj }}</td>
                            <td class="px-5 py-4">{{ company.status }}</td>
                        </tr>
                        <tr v-if="companies.length === 0"><td colspan="3" class="px-5 py-10 text-center text-slate-500">Nenhuma empresa cadastrada ainda.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</template>
