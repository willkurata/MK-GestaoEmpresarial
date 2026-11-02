<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { login as mkLogin } from '@/routes';
import { login as companyLogin } from '@/routes/company';

defineOptions({
    layout: {
        title: 'Acesso da empresa',
        description: 'Entre com o CNPJ e a senha da sua empresa.',
    },
});
</script>

<template>
    <Head title="Acesso da empresa" />

    <Form v-bind="companyLogin.form()" v-slot="{ errors, processing }" class="flex flex-col gap-5">
        <div v-if="errors.cnpj || errors.password" role="alert" class="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
            CNPJ ou senha incorretos. Confira os dados e tente novamente.
        </div>
        <div class="grid gap-2">
            <label for="cnpj" class="text-sm font-medium">CNPJ da empresa</label>
            <input id="cnpj" name="cnpj" required autocomplete="username" inputmode="numeric" placeholder="00.000.000/0000-00" class="h-11 rounded-md border border-input bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-ring" />
            <InputError :message="errors.cnpj" />
        </div>
        <div class="grid gap-2">
            <label for="password" class="text-sm font-medium">Senha da empresa</label>
            <input id="password" name="password" type="password" required autocomplete="current-password" class="h-11 rounded-md border border-input bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-ring" />
            <InputError :message="errors.password" />
        </div>
        <button type="submit" :disabled="processing" class="h-11 rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground disabled:opacity-60">
            {{ processing ? 'Entrando…' : 'Acessar empresa' }}
        </button>
        <p class="text-center text-xs text-muted-foreground">O acesso individual dos funcionários será solicitado na próxima etapa.</p>
        <div class="text-center text-sm">
            <Link :href="mkLogin()" class="text-muted-foreground underline underline-offset-4">Acesso administrativo MK</Link>
        </div>
    </Form>
</template>
