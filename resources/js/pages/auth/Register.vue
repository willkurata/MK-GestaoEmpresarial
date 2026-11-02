<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { login } from '@/routes';

defineOptions({
    layout: {
        title: 'Cadastro de acesso',
        description: 'Os acessos são criados pela administração MK Gestão.',
    },
});
</script>

<template>
    <Head title="Cadastro de acesso" />
    <div class="flex flex-col gap-4 text-center">
        <p class="text-sm text-muted-foreground">O cadastro público está desativado. Entre em contato com a administração MK para receber seu acesso.</p>
        <Link :href="login()" class="text-sm font-medium text-primary underline underline-offset-4">Acesso administrativo MK</Link>
    </div>
</template>
