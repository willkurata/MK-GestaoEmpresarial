<script setup lang="ts">
import { DoorOpen, LayoutDashboard, Monitor, Smartphone } from '@lucide/vue';
import DemoBadge from '../components/DemoBadge.vue';

const surfaces = [
    {
        to: '/catraca',
        icon: DoorOpen,
        title: 'Tela da catraca',
        description:
            'Tablet ao lado do equipamento: identificação, liberação com timer e lotação.',
        device: 'Kiosk · paisagem',
    },
    {
        to: '/totem',
        icon: Monitor,
        title: 'Totem de autoatendimento',
        description:
            'Check-in, pagamento por Pix, matrícula e reserva de aula sem fila.',
        device: 'Touch · 21"+',
    },
    {
        to: '/aluno',
        icon: Smartphone,
        title: 'App do aluno (PWA)',
        description:
            'QR de acesso, aulas com fila de espera, histórico de treino e avisos.',
        device: 'Celular · instalável',
    },
    {
        to: '/admin',
        icon: LayoutDashboard,
        title: 'Painel do dono',
        description:
            'Faturamento, ocupação, inadimplência, acesso em tempo real e campanhas.',
        device: 'Desktop · tablet',
    },
];
</script>

<template>
    <div
        class="mx-auto flex min-h-dvh max-w-5xl flex-col justify-center px-5 py-12"
    >
        <div class="flex flex-wrap items-center gap-3">
            <span
                class="font-display text-ink-950 grid size-10 place-items-center rounded-xl bg-lime-400 font-bold"
                >MK</span
            >
            <span class="font-display text-lg font-bold tracking-wide"
                >MK ACADEMIA</span
            >
            <DemoBadge />
        </div>
        <h1 class="font-display mt-8 max-w-2xl text-4xl font-bold sm:text-5xl">
            Uma base, quatro superfícies.
        </h1>
        <p class="text-ink-300 mt-4 max-w-2xl text-lg">
            Escolha a tela para abrir. Dica: abra a catraca e o painel do dono
            em abas diferentes — os acessos aparecem no painel em tempo real.
        </p>

        <ul class="mt-10 grid gap-4 sm:grid-cols-2">
            <li v-for="surface in surfaces" :key="surface.to">
                <a
                    :href="`#${surface.to}`"
                    class="group border-ink-800 bg-ink-900 flex h-full flex-col rounded-3xl border p-6 transition hover:-translate-y-0.5 hover:border-lime-400/60"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="bg-ink-800 group-hover:text-ink-950 grid size-12 place-items-center rounded-2xl text-lime-400 group-hover:bg-lime-400"
                        >
                            <component :is="surface.icon" class="size-6" />
                        </span>
                        <span class="text-ink-400 text-xs">{{
                            surface.device
                        }}</span>
                    </div>
                    <h2 class="font-display mt-5 text-xl font-bold">
                        {{ surface.title }}
                    </h2>
                    <p class="text-ink-300 mt-2">{{ surface.description }}</p>
                </a>
            </li>
        </ul>

        <p class="text-ink-400 mt-10 text-sm">
            Todos os números e nomes são de demonstração. Nenhum dado real é
            exibido enquanto a API (WIL-43) não estiver pronta.
        </p>
    </div>
</template>
