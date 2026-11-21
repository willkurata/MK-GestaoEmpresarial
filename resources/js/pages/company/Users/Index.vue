<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { index, resetPassword, store, update } from '@/routes/company/users';

type CompanyUserRow = { id: number; cpf: string; name: string; username: string; role: string; status: string };

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Usuários', href: index() }],
    },
});

defineProps<{
    users: CompanyUserRow[];
    roles: string[];
    currentUserId: number;
    flash: {
        success?: string;
        temporaryPassword?: { name: string; username: string; password: string };
    };
}>();

const roleLabel = (role: string) => role === 'Funcionario' ? 'Funcionário' : role;
const inputClass = 'h-10 rounded-md border bg-transparent px-3';
const selectClass = 'h-9 rounded-md border bg-background px-2 text-sm';
</script>

<template>
    <Head title="Usuários" />
    <main class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <header>
            <p class="text-sm font-medium text-muted-foreground">EQUIPE</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight">Usuários</h1>
            <p class="mt-2 text-sm text-muted-foreground">Cadastre, edite, ative ou inative os acessos individuais desta empresa.</p>
        </header>

        <p v-if="flash.success" role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            {{ flash.success }}
        </p>

        <section v-if="flash.temporaryPassword" role="alert" class="rounded-xl border border-amber-300 bg-amber-50 p-5 text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">
            <h2 class="font-semibold">Senha temporária para {{ flash.temporaryPassword.name }}</h2>
            <p class="mt-1 text-sm">Usuário: {{ flash.temporaryPassword.username }}. Entregue a senha por um canal privado. Ela será exibida apenas nesta visita.</p>
            <code class="mt-3 block select-all rounded-lg border border-amber-400 bg-white px-4 py-3 font-mono text-lg tracking-wide text-slate-950 dark:bg-slate-900 dark:text-white">{{ flash.temporaryPassword.password }}</code>
            <p class="mt-2 text-sm">No próximo acesso, a pessoa precisará definir uma senha pessoal. Sessões abertas com a senha antiga foram encerradas.</p>
        </section>

        <section class="rounded-xl border bg-card p-5 text-card-foreground sm:p-6">
            <h2 class="text-lg font-semibold">Adicionar usuário</h2>
            <p class="mt-1 text-sm text-muted-foreground">CPF e usuário não podem se repetir dentro desta empresa.</p>
            <Form v-bind="store.form()" v-slot="{ errors, processing }" reset-on-success class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <label class="grid gap-2 text-sm">Nome completo
                    <input name="name" required maxlength="255" autocomplete="off" :class="inputClass" />
                    <InputError :message="errors.name" />
                </label>
                <label class="grid gap-2 text-sm">CPF
                    <input name="cpf" required inputmode="numeric" placeholder="000.000.000-00" autocomplete="off" :class="inputClass" />
                    <InputError :message="errors.cpf" />
                </label>
                <label class="grid gap-2 text-sm">Usuário
                    <input name="username" required maxlength="255" autocomplete="off" :class="inputClass" />
                    <InputError :message="errors.username" />
                </label>
                <label class="grid gap-2 text-sm">Perfil
                    <select name="role" required :class="inputClass">
                        <option v-for="role in roles" :key="role" :value="role" :selected="role === 'Funcionario'">{{ roleLabel(role) }}</option>
                    </select>
                    <InputError :message="errors.role" />
                </label>
                <label class="grid gap-2 text-sm">Senha inicial
                    <input name="password" type="password" required minlength="8" autocomplete="new-password" :class="inputClass" />
                    <InputError :message="errors.password" />
                </label>
                <label class="grid gap-2 text-sm">Confirme a senha
                    <input name="password_confirmation" type="password" required minlength="8" autocomplete="new-password" :class="inputClass" />
                </label>
                <div class="sm:col-span-2 lg:col-span-3">
                    <button type="submit" :disabled="processing" class="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground disabled:opacity-60">
                        {{ processing ? 'Salvando…' : 'Adicionar usuário' }}
                    </button>
                </div>
            </Form>
        </section>

        <section class="overflow-hidden rounded-xl border bg-card text-card-foreground">
            <div class="border-b px-5 py-4 sm:px-6">
                <h2 class="font-semibold">Usuários da empresa</h2>
                <p class="mt-1 text-sm text-muted-foreground">Usuários inativos não conseguem entrar, mas o histórico deles é mantido.</p>
            </div>
            <div class="divide-y">
                <div v-for="user in users" :key="user.id" class="grid gap-3 px-5 py-4 sm:px-6">
                    <p class="text-sm text-muted-foreground">
                        <span class="font-medium text-foreground">{{ user.name }}</span>
                        · CPF {{ user.cpf }} · {{ roleLabel(user.role) }} · {{ user.status === 'ativa' ? 'Ativo' : 'Inativo' }}
                        <span v-if="user.id === currentUserId" class="ml-1 rounded bg-muted px-1.5 py-0.5 text-xs">você</span>
                    </p>
                    <div class="flex flex-wrap items-end gap-3">
                        <Form v-bind="update.form({ companyUser: user.id })" v-slot="{ errors, processing }" class="flex flex-wrap items-end gap-3">
                            <label class="grid gap-1 text-xs text-muted-foreground">Nome
                                <input name="name" :value="user.name" required maxlength="255" class="h-9 rounded-md border bg-transparent px-2 text-sm text-foreground" />
                            </label>
                            <label class="grid gap-1 text-xs text-muted-foreground">Usuário
                                <input name="username" :value="user.username" required maxlength="255" class="h-9 rounded-md border bg-transparent px-2 text-sm text-foreground" />
                            </label>
                            <template v-if="user.id === currentUserId">
                                <input type="hidden" name="role" :value="user.role" />
                                <input type="hidden" name="status" :value="user.status" />
                            </template>
                            <template v-else>
                                <label class="grid gap-1 text-xs text-muted-foreground">Perfil
                                    <select name="role" :value="user.role" :class="selectClass">
                                        <option v-for="role in roles" :key="role" :value="role">{{ roleLabel(role) }}</option>
                                    </select>
                                </label>
                                <label class="grid gap-1 text-xs text-muted-foreground">Status
                                    <select name="status" :value="user.status" :class="selectClass">
                                        <option value="ativa">Ativo</option>
                                        <option value="inativa">Inativo</option>
                                    </select>
                                </label>
                            </template>
                            <button :disabled="processing" class="h-9 rounded-md border px-3 text-sm disabled:opacity-60">Salvar</button>
                            <div class="basis-full">
                                <InputError :message="errors.role || errors.name || errors.username || errors.status" />
                            </div>
                        </Form>
                        <Form v-if="user.id !== currentUserId" v-bind="resetPassword.form({ companyUser: user.id })" v-slot="{ errors }">
                            <button class="h-9 rounded-md border border-amber-500 px-3 text-sm text-amber-800 hover:bg-amber-50 dark:text-amber-300 dark:hover:bg-amber-950">Gerar senha temporária</button>
                            <InputError :message="errors.password" />
                        </Form>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
