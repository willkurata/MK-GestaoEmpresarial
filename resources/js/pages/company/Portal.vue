<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { logout as companyLogout } from '@/routes/company';
import { login as companyUserLogin } from '@/routes/company/user';

defineOptions({
    layout: {
        title: 'Portal da empresa',
        description: 'Empresa identificada. Entre com seu usuário individual.',
    },
});

defineProps<{
    company: { name: string; cnpj: string };
}>();

const modules = ['Produtos', 'Vendas', 'Estoque', 'Financeiro', 'Relatórios'];
</script>

<template>
    <Head title="Portal da empresa" />

    <div class="flex flex-col gap-6">
        <section class="rounded-xl border bg-card p-5 text-card-foreground">
            <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Empresa identificada</p>
            <h1 class="mt-1 text-xl font-semibold">Bem-vinda, {{ company.name }}</h1>
            <p class="mt-1 text-sm text-muted-foreground">CNPJ {{ company.cnpj }}</p>
        </section>

        <section class="grid grid-cols-2 gap-3 sm:grid-cols-3" aria-label="Módulos do sistema">
            <div v-for="module in modules" :key="module" aria-disabled="true" class="rounded-lg border border-dashed bg-muted/30 p-4 text-sm font-medium text-muted-foreground/60">
                {{ module }} <span class="ml-1 text-xs">Em breve</span>
            </div>
        </section>

        <section class="rounded-xl border bg-card p-5 text-card-foreground">
            <div class="mb-5">
                <h2 class="font-semibold">Acesso individual</h2>
                <p class="mt-1 text-sm text-muted-foreground">Use seu usuário e senha cadastrados pela empresa.</p>
            </div>
            <Form v-bind="companyUserLogin.form()" v-slot="{ errors, processing }" class="flex flex-col gap-4">
                <div v-if="errors.username || errors.password" role="alert" class="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    Usuário ou senha incorretos. Confira os dados e tente novamente.
                </div>
                <div class="grid gap-2">
                    <label for="username" class="text-sm font-medium">Usuário</label>
                    <input id="username" name="username" required autocomplete="username" class="h-11 rounded-md border border-input bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-ring" />
                    <InputError :message="errors.username" />
                </div>
                <div class="grid gap-2">
                    <label for="staff-password" class="text-sm font-medium">Senha</label>
                    <input id="staff-password" name="password" type="password" required autocomplete="current-password" class="h-11 rounded-md border border-input bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-ring" />
                    <InputError :message="errors.password" />
                </div>
                <button type="submit" :disabled="processing" class="h-11 rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground disabled:opacity-60">
                    {{ processing ? 'Validando…' : 'Entrar no sistema' }}
                </button>
            </Form>
            <Form v-bind="companyLogout.form()" class="mt-3 text-center">
                <button type="submit" class="text-sm text-muted-foreground underline underline-offset-4">Sair da empresa</button>
            </Form>
        </section>
    </div>
</template>
