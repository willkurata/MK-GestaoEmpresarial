<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { resetPassword as resetUserPassword, store as storeUser, update as updateUser } from '@/routes/mk/companies/users';
import { update as updateCompany } from '@/routes/mk/companies';
import { dashboard } from '@/routes/mk';

defineOptions({
    layout: {
        title: 'Gerenciar empresa',
        description: 'Gerencie o acesso e os usuários da empresa.',
    },
});

defineProps<{
    company: { id: number; name: string; cnpj: string; status: string; created_at: string };
    users: Array<{ id: number; cpf: string; name: string; username: string; role: string; status: string }>;
    roles: string[];
    flash: {
        success?: string;
        temporaryPassword?: { name: string; username: string; password: string };
    };
}>();

const roleLabel = (role: string) => role === 'Funcionario' ? 'Funcionário' : role;
</script>

<template>
    <Head :title="company.name" />
    <main class="min-h-screen bg-slate-50 px-5 py-8 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <div class="mx-auto max-w-6xl">
            <Link :href="dashboard()" class="text-sm text-blue-700 hover:underline dark:text-blue-300">← Voltar às empresas</Link>
            <header class="mb-6 mt-5 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold text-blue-700 dark:text-blue-400">MK GESTÃO · MASTER</p>
                    <h1 class="mt-1 text-3xl font-semibold">{{ company.name }}</h1>
                    <p class="mt-2 text-sm text-slate-500">CNPJ {{ company.cnpj }} · {{ users.length }} usuário(s)</p>
                </div>
                <Form v-bind="updateCompany.form({ company: company.id })" class="flex items-center gap-2">
                    <input type="hidden" name="status" :value="company.status === 'ativa' ? 'inativa' : 'ativa'" />
                    <span class="text-sm">{{ company.status }}</span>
                    <button class="rounded-lg border px-4 py-2 text-sm">{{ company.status === 'ativa' ? 'Inativar empresa' : 'Ativar empresa' }}</button>
                </Form>
            </header>

            <p v-if="flash.success" role="status" class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                {{ flash.success }}
            </p>

            <section v-if="flash.temporaryPassword" role="alert" class="mb-7 rounded-xl border border-amber-300 bg-amber-50 p-5 text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">
                <h2 class="font-semibold">Senha temporária para {{ flash.temporaryPassword.name }}</h2>
                <p class="mt-1 text-sm">Usuário: {{ flash.temporaryPassword.username }}. Copie e entregue a senha por um canal privado. Ela será exibida apenas nesta visita.</p>
                <code class="mt-3 block select-all rounded-lg border border-amber-400 bg-white px-4 py-3 font-mono text-lg tracking-wide text-slate-950 dark:bg-slate-900 dark:text-white">{{ flash.temporaryPassword.password }}</code>
                <p class="mt-2 text-sm">No primeiro acesso, a pessoa precisará definir uma senha pessoal para continuar.</p>
            </section>

            <section class="mb-7 rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-semibold">Adicionar usuário</h2>
                <p class="mt-1 text-sm text-slate-500">CPF e usuário podem se repetir em outra empresa, mas não dentro desta.</p>
                <Form v-bind="storeUser.form({ company: company.id })" v-slot="{ errors, processing }" class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="grid gap-2 text-sm">Nome completo
                        <input name="name" required maxlength="255" autocomplete="name" class="h-10 rounded-md border bg-transparent px-3" />
                        <InputError :message="errors.name" />
                    </label>
                    <label class="grid gap-2 text-sm">CPF
                        <input name="cpf" required inputmode="numeric" placeholder="000.000.000-00" class="h-10 rounded-md border bg-transparent px-3" />
                        <InputError :message="errors.cpf" />
                    </label>
                    <label class="grid gap-2 text-sm">Usuário
                        <input name="username" required maxlength="255" autocomplete="username" class="h-10 rounded-md border bg-transparent px-3" />
                        <InputError :message="errors.username" />
                    </label>
                    <label class="grid gap-2 text-sm">Perfil
                        <select name="role" required class="h-10 rounded-md border bg-white px-3 dark:bg-slate-950">
                            <option v-for="role in roles" :key="role" :value="role">{{ roleLabel(role) }}</option>
                        </select>
                        <InputError :message="errors.role" />
                    </label>
                    <label class="grid gap-2 text-sm">Senha <span class="text-xs text-slate-500">Mínimo 8 caracteres</span>
                        <input name="password" type="password" required minlength="8" autocomplete="new-password" class="h-10 rounded-md border bg-transparent px-3" />
                        <InputError :message="errors.password" />
                    </label>
                    <label class="grid gap-2 text-sm">Confirme a senha
                        <input name="password_confirmation" type="password" required minlength="8" autocomplete="new-password" class="h-10 rounded-md border bg-transparent px-3" />
                    </label>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <button type="submit" :disabled="processing" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60">
                            {{ processing ? 'Salvando…' : 'Adicionar usuário' }}
                        </button>
                    </div>
                </Form>
            </section>

            <section class="overflow-hidden rounded-xl border bg-white dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b px-5 py-4 dark:border-slate-800">
                    <h2 class="font-semibold">Usuários da empresa</h2>
                </div>
                <div v-if="users.length" class="divide-y dark:divide-slate-800">
                    <div v-for="user in users" :key="user.id" class="grid gap-4 px-5 py-4 md:grid-cols-[1fr_auto] md:items-center">
                        <div>
                            <p class="font-medium">{{ user.name }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ user.username }} · CPF {{ user.cpf }} · {{ roleLabel(user.role) }} · {{ user.status }}</p>
                        </div>
                        <div class="flex flex-wrap items-end gap-3">
                            <Form v-bind="updateUser.form({ company: company.id, companyUser: user.id })" class="flex flex-wrap items-end gap-3">
                                <label class="grid gap-1 text-xs text-slate-500">Perfil
                                    <select name="role" :value="user.role" class="h-9 rounded-md border bg-white px-2 text-sm text-slate-900 dark:bg-slate-950 dark:text-slate-100">
                                        <option v-for="role in roles" :key="role" :value="role">{{ roleLabel(role) }}</option>
                                    </select>
                                </label>
                                <label class="grid gap-1 text-xs text-slate-500">Status
                                    <select name="status" :value="user.status" class="h-9 rounded-md border bg-white px-2 text-sm text-slate-900 dark:bg-slate-950 dark:text-slate-100">
                                        <option value="ativa">Ativo</option>
                                        <option value="inativa">Inativo</option>
                                    </select>
                                </label>
                                <button class="h-9 rounded-md border px-3 text-sm">Salvar</button>
                            </Form>
                            <Form v-if="user.role === 'Administrador'" v-bind="resetUserPassword.form({ company: company.id, companyUser: user.id })">
                                <button class="h-9 rounded-md border border-amber-500 px-3 text-sm text-amber-800 hover:bg-amber-50 dark:text-amber-300 dark:hover:bg-amber-950">Gerar senha temporária</button>
                            </Form>
                        </div>
                    </div>
                </div>
                <p v-else class="px-5 py-10 text-center text-sm text-slate-500">Nenhum usuário cadastrado.</p>
            </section>
        </div>
    </main>
</template>
