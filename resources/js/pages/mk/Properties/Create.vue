<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PropertyForm from '@/components/mk/PropertyForm.vue';
import {
    index as propertiesIndex,
    store as storeProperty,
} from '@/routes/mk/properties';
import type { PropertyOptions } from '@/lib/properties';

defineOptions({
    layout: {
        title: 'Cadastrar imóvel',
        description: 'Crie o rascunho de um imóvel para venda ou aluguel.',
    },
});

defineProps<{ options: PropertyOptions }>();
</script>

<template>
    <Head title="Cadastrar imóvel" />
    <main
        class="min-h-screen bg-slate-50 px-5 py-8 text-slate-900 dark:bg-slate-950 dark:text-slate-100"
    >
        <div class="mx-auto max-w-3xl">
            <Link
                :href="propertiesIndex()"
                class="text-sm text-blue-700 hover:underline dark:text-blue-300"
                >← Voltar aos imóveis</Link
            >
            <header class="mt-5 mb-6">
                <p
                    class="text-sm font-semibold text-blue-700 dark:text-blue-400"
                >
                    MK GESTÃO · MASTER
                </p>
                <h1 class="mt-1 text-3xl font-semibold">Cadastrar imóvel</h1>
                <p class="mt-2 text-sm text-slate-500">
                    O imóvel começa como rascunho e recebe um código de
                    referência. Depois de salvar, adicione as fotos e publique.
                </p>
            </header>
            <PropertyForm
                :action="storeProperty()"
                :options="options"
                submit-label="Salvar rascunho"
            />
        </div>
    </main>
</template>
