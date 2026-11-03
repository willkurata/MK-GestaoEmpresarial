<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/company/user/security';
import { update } from '@/routes/company/user/password';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Minha senha', href: edit() }],
    },
});

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function updatePassword(): void {
    form.put(update().url, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head title="Minha senha" />

    <main class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <header>
            <p class="text-sm font-medium text-muted-foreground">MINHA CONTA</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight">Segurança</h1>
            <p class="mt-2 text-sm text-muted-foreground">Troque a senha do seu usuário da empresa.</p>
        </header>

        <section class="rounded-xl border bg-card p-5 text-card-foreground sm:p-6" aria-labelledby="password-form-title">
            <Heading variant="small" title="Alterar senha" description="Confirme sua senha atual e escolha uma nova senha." />

            <form class="mt-6 space-y-5" @submit.prevent="updatePassword">
                <div class="grid gap-2">
                    <Label for="current_password">Senha atual</Label>
                    <PasswordInput id="current_password" v-model="form.current_password" autocomplete="current-password" required />
                    <InputError :message="form.errors.current_password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Nova senha</Label>
                    <PasswordInput id="password" v-model="form.password" autocomplete="new-password" required />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirme a nova senha</Label>
                    <PasswordInput id="password_confirmation" v-model="form.password_confirmation" autocomplete="new-password" required />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <div class="flex items-center gap-4">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Salvando…' : 'Salvar nova senha' }}
                    </Button>
                </div>
            </form>
        </section>
    </main>
</template>
