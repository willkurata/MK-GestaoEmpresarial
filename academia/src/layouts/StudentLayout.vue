<script setup lang="ts">
import { Bell, CalendarDays, History, House } from '@lucide/vue';
import DemoBadge from '../components/DemoBadge.vue';
import { isActive } from '../lib/router';
import { unreadCount } from '../lib/store';

defineProps<{ title?: string; back?: string }>();

const tabs = [
    { label: 'Início', to: '/aluno', icon: House, exact: true },
    { label: 'Aulas', to: '/aluno/aulas', icon: CalendarDays },
    { label: 'Treinos', to: '/aluno/treinos', icon: History },
    { label: 'Avisos', to: '/aluno/avisos', icon: Bell },
];
</script>

<template>
    <div class="bg-ink-950 mx-auto flex min-h-dvh max-w-md flex-col">
        <header
            class="bg-ink-950/90 sticky top-0 z-20 flex items-center justify-between gap-3 px-5 pt-[max(1rem,env(safe-area-inset-top))] pb-3 backdrop-blur"
        >
            <div class="flex min-w-0 items-center gap-2">
                <a
                    v-if="back"
                    :href="`#${back}`"
                    class="text-ink-300 hover:bg-ink-800 -ml-2 rounded-full p-2"
                    aria-label="Voltar"
                >
                    <svg
                        viewBox="0 0 24 24"
                        class="size-5"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path d="m15 18-6-6 6-6" />
                    </svg>
                </a>
                <h1 class="font-display truncate text-xl font-bold">
                    <slot name="title">{{ title }}</slot>
                </h1>
            </div>
            <DemoBadge />
        </header>

        <main class="flex-1 space-y-5 px-5 pb-28">
            <slot />
        </main>

        <nav
            class="border-ink-800 bg-ink-950/95 fixed inset-x-0 bottom-0 z-30 mx-auto max-w-md border-t pb-[env(safe-area-inset-bottom)] backdrop-blur"
            aria-label="Navegação do aluno"
        >
            <ul class="grid grid-cols-4">
                <li v-for="tab in tabs" :key="tab.to">
                    <a
                        :href="`#${tab.to}`"
                        class="relative flex min-h-16 flex-col items-center justify-center gap-1 text-[11px] font-medium"
                        :class="
                            isActive(tab.to, tab.exact)
                                ? 'text-lime-400'
                                : 'text-ink-400 hover:text-ink-100'
                        "
                        :aria-current="
                            isActive(tab.to, tab.exact) ? 'page' : undefined
                        "
                    >
                        <span class="relative">
                            <component :is="tab.icon" class="size-6" />
                            <span
                                v-if="
                                    tab.to === '/aluno/avisos' &&
                                    unreadCount > 0
                                "
                                class="text-ink-950 absolute -top-1 -right-2 grid min-w-4 place-items-center rounded-full bg-lime-400 px-1 text-[10px] font-bold"
                            >
                                {{ unreadCount }}
                            </span>
                        </span>
                        {{ tab.label }}
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</template>
