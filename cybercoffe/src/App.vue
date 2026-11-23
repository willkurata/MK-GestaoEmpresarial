<script setup lang="ts">
import { api } from '@cc/api';
import AppNav from '@cc/components/AppNav.vue';
import PixelBlocks from '@cc/components/PixelBlocks.vue';
import AdminPage from '@cc/pages/AdminPage.vue';
import BarPage from '@cc/pages/BarPage.vue';
import CheckinPage from '@cc/pages/CheckinPage.vue';
import MachinePage from '@cc/pages/MachinePage.vue';
import { route } from '@cc/router';
import { useTemplateRef, watchEffect } from 'vue';

const mainElement = useTemplateRef<HTMLElement>('mainElement');

const TITLES = {
    checkin: 'Check-in',
    machine: 'Máquina',
    bar: 'Barra',
    admin: 'Painel',
    'not-found': 'Página não encontrada',
} as const;

watchEffect(() => {
    document.title = `${TITLES[route.value.name]} · CyberCoffe`;
});
</script>

<template>
    <!-- Janela compacta do programa cliente: só a barrinha, sem navegação. -->
    <BarPage v-if="route.name === 'bar'" :station-id="route.stationId" />
    <div v-else class="min-h-dvh">
        <!-- Botão em vez de âncora: o hash é usado pelo roteador. -->
        <button
            type="button"
            class="focus:bg-neon focus:text-void sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:px-3 focus:py-2"
            @click="mainElement?.focus()"
        >
            Pular para o conteúdo
        </button>
        <AppNav :current="route.name" :is-demo="api.isDemo" />

        <main ref="mainElement" tabindex="-1" class="outline-none">
            <CheckinPage
                v-if="route.name === 'checkin'"
                :preselected-station-id="route.stationId"
            />
            <MachinePage
                v-else-if="route.name === 'machine'"
                :station-id="route.stationId"
            />
            <AdminPage v-else-if="route.name === 'admin'" />
            <section
                v-else
                class="mx-auto flex max-w-xl flex-col items-center gap-6 px-4 py-24 text-center"
            >
                <PixelBlocks :columns="10" :rows="4" :seed="404" />
                <h1 class="text-5xl font-bold">404</h1>
                <p class="text-dim">Essa tela não existe.</p>
                <a href="#/checkin" class="btn btn-neon">Voltar ao check-in</a>
            </section>
        </main>
    </div>
</template>
