<script setup lang="ts">
import { CalendarDays, DoorOpen, UserPlus, Wallet } from '@lucide/vue';
import KioskLayout from '../../layouts/KioskLayout.vue';
import { state } from '../../lib/store';

const actions = [
    {
        to: '/totem/checkin',
        icon: DoorOpen,
        title: 'Fazer check-in',
        hint: 'Rosto ou código',
        primary: true,
    },
    {
        to: '/totem/pagar',
        icon: Wallet,
        title: 'Pagar mensalidade',
        hint: 'Pix na hora',
    },
    {
        to: '/totem/matricula',
        icon: UserPlus,
        title: 'Matricular-se',
        hint: 'Sem fila, em 2 minutos',
    },
    {
        to: '/totem/aulas',
        icon: CalendarDays,
        title: 'Reservar aula',
        hint: 'Vagas e fila de espera',
    },
];
</script>

<template>
    <KioskLayout home>
        <div class="grid flex-1 gap-5 sm:grid-cols-2">
            <a
                v-for="action in actions"
                :key="action.to"
                :href="`#${action.to}`"
                class="flex min-h-52 flex-col justify-between rounded-3xl p-7 transition active:scale-[0.98]"
                :class="
                    action.primary
                        ? 'text-ink-950 bg-lime-400'
                        : 'border-ink-800 bg-ink-900 hover:border-ink-600 border'
                "
            >
                <component
                    :is="action.icon"
                    class="size-14"
                    stroke-width="1.75"
                />
                <span>
                    <span
                        class="font-display block text-3xl font-bold sm:text-4xl"
                        >{{ action.title }}</span
                    >
                    <span
                        class="mt-1 block text-lg"
                        :class="
                            action.primary ? 'text-ink-800' : 'text-ink-400'
                        "
                        >{{ action.hint }}</span
                    >
                </span>
            </a>
        </div>
        <p class="text-ink-400 mt-6 text-center text-lg">
            {{ state.occupancy }} pessoas treinando agora · capacidade
            {{ state.gym.capacity }}
        </p>
    </KioskLayout>
</template>
