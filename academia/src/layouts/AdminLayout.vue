<script setup lang="ts">
import {
    CalendarDays,
    ChartColumn,
    DoorOpen,
    LayoutDashboard,
    Megaphone,
    Settings,
    Users,
    Wallet,
} from '@lucide/vue';
import DemoBadge from '../components/DemoBadge.vue';
import { isActive } from '../lib/router';
import { state } from '../lib/store';

defineProps<{ title: string; subtitle?: string }>();

const navItems = [
    { label: 'Visão geral', to: '/admin', icon: LayoutDashboard, exact: true },
    { label: 'Alunos', to: '/admin/alunos', icon: Users },
    { label: 'Acesso', to: '/admin/acesso', icon: DoorOpen },
    { label: 'Campanhas', to: '/admin/campanhas', icon: Megaphone },
    { label: 'Relatórios', to: '/admin/relatorios', icon: ChartColumn },
];

const soonItems = [
    { label: 'Planos e cobrança', icon: Wallet },
    { label: 'Agenda de aulas', icon: CalendarDays },
    { label: 'Configurações', icon: Settings },
];
</script>

<template>
    <div
        class="surface-light bg-canvas min-h-dvh text-slate-900 lg:grid lg:grid-cols-[15rem_1fr]"
    >
        <aside class="border-line hidden border-r bg-white lg:flex lg:flex-col">
            <a href="#/" class="flex items-center gap-2 px-5 py-5">
                <span
                    class="bg-ink-950 font-display grid size-8 place-items-center rounded-lg text-sm font-bold text-lime-400"
                    >MK</span
                >
                <span class="font-display text-sm font-bold tracking-wide"
                    >MK ACADEMIA</span
                >
            </a>
            <nav class="flex-1 space-y-0.5 px-3" aria-label="Menu principal">
                <p
                    class="px-2 pb-2 text-[11px] font-semibold tracking-wider text-slate-400 uppercase"
                >
                    Menu principal
                </p>
                <a
                    v-for="item in navItems"
                    :key="item.to"
                    :href="`#${item.to}`"
                    class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors"
                    :class="
                        isActive(item.to, item.exact)
                            ? 'bg-ink-950 text-white'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                    "
                    :aria-current="
                        isActive(item.to, item.exact) ? 'page' : undefined
                    "
                >
                    <component :is="item.icon" class="size-4" />
                    {{ item.label }}
                </a>
                <p
                    class="px-2 pt-5 pb-2 text-[11px] font-semibold tracking-wider text-slate-400 uppercase"
                >
                    Em breve
                </p>
                <span
                    v-for="item in soonItems"
                    :key="item.label"
                    class="flex cursor-not-allowed items-center gap-2.5 px-2.5 py-2 text-sm text-slate-400"
                    aria-disabled="true"
                >
                    <component :is="item.icon" class="size-4" />
                    {{ item.label }}
                </span>
            </nav>
            <div class="border-line m-3 rounded-xl border p-3 text-xs">
                <p class="font-semibold">{{ state.gym.name }}</p>
                <p class="mt-0.5 text-slate-500">Dono · acesso completo</p>
            </div>
        </aside>

        <div class="min-w-0">
            <header
                class="border-line sticky top-0 z-20 border-b bg-white/90 backdrop-blur"
            >
                <div
                    class="flex items-center justify-between gap-3 px-4 py-3 lg:px-8"
                >
                    <div class="min-w-0">
                        <h1
                            class="font-display truncate text-lg font-bold lg:text-xl"
                        >
                            {{ title }}
                        </h1>
                        <p
                            v-if="subtitle"
                            class="truncate text-sm text-slate-500"
                        >
                            {{ subtitle }}
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <slot name="actions" />
                        <DemoBadge tone="light" />
                    </div>
                </div>
                <nav
                    class="flex gap-1 overflow-x-auto px-3 pb-2 lg:hidden"
                    aria-label="Menu principal"
                >
                    <a
                        v-for="item in navItems"
                        :key="item.to"
                        :href="`#${item.to}`"
                        class="flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium"
                        :class="
                            isActive(item.to, item.exact)
                                ? 'bg-ink-950 text-white'
                                : 'text-slate-600'
                        "
                    >
                        <component :is="item.icon" class="size-4" />
                        {{ item.label }}
                    </a>
                </nav>
            </header>
            <main class="mx-auto max-w-7xl space-y-6 px-4 py-6 lg:px-8">
                <slot />
            </main>
        </div>
    </div>
</template>
