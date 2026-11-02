<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Building2, LayoutGrid, LockKeyhole, Package, ReceiptText, ShoppingCart, UsersRound } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { login as companyUserLogin } from '@/routes/company/user';

defineProps<{
    company: { name: string; cnpj: string };
}>();

const lockedModules = [
    { title: 'Dashboard', description: 'Resumo da empresa', icon: LayoutGrid },
    { title: 'Produtos', description: 'Catálogo e preços', icon: Package },
    { title: 'PDV e caixa', description: 'Vendas e recebimentos', icon: ShoppingCart },
    { title: 'Despesas', description: 'Lançamentos financeiros', icon: ReceiptText },
];
</script>

<template>
    <Head title="Acesso à empresa" />

    <main class="mx-auto flex min-h-[calc(100vh-3rem)] w-full max-w-7xl flex-col gap-8 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
        <header class="flex flex-col justify-between gap-4 border-b border-border/70 pb-6 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-semibold tracking-wide text-primary">MK GESTÃO · ESPAÇO DA EMPRESA</p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">Bem-vinda, {{ company.name }}</h1>
                <p class="mt-2 text-sm text-muted-foreground">Entre com seu usuário individual para liberar as ferramentas da empresa.</p>
            </div>
            <div class="flex items-center gap-2 self-start rounded-lg border bg-card px-3 py-2 text-sm text-muted-foreground sm:self-auto">
                <Building2 class="size-4 text-primary" aria-hidden="true" />
                <span>CNPJ {{ company.cnpj }}</span>
            </div>
        </header>

        <div class="grid flex-1 items-center gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(22rem,28rem)]">
            <section class="mx-auto w-full max-w-2xl" aria-labelledby="workspace-title">
                <div class="flex items-start gap-4">
                    <div class="grid size-12 shrink-0 place-items-center rounded-2xl bg-primary/10 text-primary">
                        <UsersRound class="size-6" aria-hidden="true" />
                    </div>
                    <div>
                        <h2 id="workspace-title" class="text-xl font-semibold">Seu espaço de trabalho está pronto</h2>
                        <p class="mt-2 max-w-xl text-sm leading-6 text-muted-foreground">Os módulos ficam protegidos até identificarmos quem está operando. Depois do acesso, você segue para o dashboard e pode iniciar uma venda no caixa.</p>
                    </div>
                </div>

                <div class="mt-7 grid gap-3 sm:grid-cols-2" aria-label="Módulos bloqueados até o acesso individual">
                    <article v-for="module in lockedModules" :key="module.title" class="flex min-h-24 items-center gap-3 rounded-xl border bg-card px-4 py-4 text-card-foreground opacity-70">
                        <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-muted text-muted-foreground">
                            <component :is="module.icon" class="size-5" aria-hidden="true" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-medium">{{ module.title }}</span>
                            <span class="mt-1 block text-xs text-muted-foreground">{{ module.description }}</span>
                        </span>
                        <LockKeyhole class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                    </article>
                </div>

                <div class="mt-6 flex items-start gap-3 rounded-xl border border-dashed bg-muted/30 p-4 text-sm text-muted-foreground">
                    <LockKeyhole class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <p>Estoque e recursos fora da V1 continuam indisponíveis. O login individual libera somente os módulos previstos para o perfil da pessoa.</p>
                </div>
            </section>

            <section class="w-full rounded-2xl border bg-card p-6 text-card-foreground shadow-lg shadow-primary/5 sm:p-8" aria-labelledby="individual-login-title">
                <div class="mb-6">
                    <p class="text-sm font-medium text-primary">ACESSO INDIVIDUAL</p>
                    <h2 id="individual-login-title" class="mt-2 text-2xl font-semibold">Quem está entrando?</h2>
                    <p class="mt-2 text-sm leading-6 text-muted-foreground">Use seu usuário e senha cadastrados pelo administrador da empresa.</p>
                </div>

                <Form v-bind="companyUserLogin.form()" v-slot="{ errors, processing }" class="flex flex-col gap-4">
                    <div v-if="errors.username || errors.password" role="alert" class="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                        Usuário ou senha incorretos. Confira os dados e tente novamente.
                    </div>
                    <div class="grid gap-2">
                        <label for="username" class="text-sm font-medium">Usuário</label>
                        <input id="username" name="username" required autocomplete="username" autofocus class="h-12 rounded-lg border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                        <InputError :message="errors.username" />
                    </div>
                    <div class="grid gap-2">
                        <label for="staff-password" class="text-sm font-medium">Senha</label>
                        <input id="staff-password" name="password" type="password" required autocomplete="current-password" class="h-12 rounded-lg border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                        <InputError :message="errors.password" />
                    </div>
                    <button type="submit" :disabled="processing" class="mt-2 flex h-12 items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground shadow-sm transition-opacity hover:opacity-90 disabled:opacity-60">
                        <LockKeyhole class="size-4" aria-hidden="true" />
                        {{ processing ? 'Validando acesso…' : 'Entrar e abrir o dashboard' }}
                    </button>
                    <p class="text-center text-xs text-muted-foreground">Seu perfil define quais ações ficam disponíveis.</p>
                </Form>
            </section>
        </div>
    </main>
</template>
