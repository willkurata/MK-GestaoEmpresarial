<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { store as storeCompany } from '@/routes/mk/companies';
import { dashboard } from '@/routes/mk';

defineOptions({
    layout: {
        title: 'Cadastrar empresa',
        description: 'Crie o acesso da empresa e do primeiro administrador.',
    },
});
</script>

<template>
    <Head title="Cadastrar empresa" />
    <main class="min-h-screen bg-slate-50 px-5 py-8 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <div class="mx-auto max-w-3xl">
            <Link :href="dashboard()" class="text-sm text-blue-700 hover:underline dark:text-blue-300">← Voltar às empresas</Link>
            <header class="mb-6 mt-5">
                <p class="text-sm font-semibold text-blue-700 dark:text-blue-400">MK GESTÃO · MASTER</p>
                <h1 class="mt-1 text-3xl font-semibold">Cadastrar empresa</h1>
                <p class="mt-2 text-sm text-slate-500">O primeiro acesso individual será criado como administrador.</p>
            </header>

            <Form v-bind="storeCompany.form()" v-slot="{ errors, processing }" class="space-y-6">
                <section class="space-y-4 rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-semibold">Dados e acesso da empresa</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="grid gap-2 text-sm sm:col-span-2">Nome da empresa
                            <input name="name" required maxlength="255" autocomplete="organization" class="h-10 rounded-md border bg-transparent px-3" />
                            <InputError :message="errors.name" />
                        </label>
                        <label class="grid gap-2 text-sm">CNPJ
                            <input name="cnpj" required inputmode="numeric" placeholder="00.000.000/0000-00" class="h-10 rounded-md border bg-transparent px-3" />
                            <InputError :message="errors.cnpj" />
                        </label>
                        <div class="hidden sm:block" />
                        <label class="grid gap-2 text-sm">Senha da empresa
                            <input name="company_password" type="password" required minlength="12" autocomplete="new-password" class="h-10 rounded-md border bg-transparent px-3" />
                            <InputError :message="errors.company_password" />
                        </label>
                        <label class="grid gap-2 text-sm">Confirme a senha da empresa
                            <input name="company_password_confirmation" type="password" required minlength="12" autocomplete="new-password" class="h-10 rounded-md border bg-transparent px-3" />
                        </label>
                    </div>
                </section>

                <section class="space-y-4 rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-semibold">Primeiro administrador</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="grid gap-2 text-sm">Nome completo
                            <input name="admin_name" required maxlength="255" autocomplete="name" class="h-10 rounded-md border bg-transparent px-3" />
                            <InputError :message="errors.admin_name" />
                        </label>
                        <label class="grid gap-2 text-sm">CPF
                            <input name="admin_cpf" required inputmode="numeric" placeholder="000.000.000-00" class="h-10 rounded-md border bg-transparent px-3" />
                            <InputError :message="errors.admin_cpf" />
                        </label>
                        <label class="grid gap-2 text-sm sm:col-span-2">Usuário de acesso
                            <input name="admin_username" required maxlength="255" autocomplete="username" class="h-10 rounded-md border bg-transparent px-3" />
                            <InputError :message="errors.admin_username" />
                        </label>
                        <label class="grid gap-2 text-sm">Senha individual <span class="text-xs text-slate-500">Mínimo 8 caracteres</span>
                            <input name="admin_password" type="password" required minlength="8" autocomplete="new-password" class="h-10 rounded-md border bg-transparent px-3" />
                            <InputError :message="errors.admin_password" />
                        </label>
                        <label class="grid gap-2 text-sm">Confirme a senha individual
                            <input name="admin_password_confirmation" type="password" required minlength="8" autocomplete="new-password" class="h-10 rounded-md border bg-transparent px-3" />
                        </label>
                    </div>
                </section>

                <InputError :message="errors.message" />
                <button type="submit" :disabled="processing" class="rounded-lg bg-blue-700 px-5 py-3 text-sm font-semibold text-white disabled:opacity-60">
                    {{ processing ? 'Cadastrando…' : 'Cadastrar empresa e administrador' }}
                </button>
            </Form>
        </div>
    </main>
</template>
