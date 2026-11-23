<script setup lang="ts">
import BrandMark from '@cc/components/BrandMark.vue';
import type { RouteName } from '@cc/router';
import { LayoutDashboard, Monitor, Ticket } from '@lucide/vue';
import type { Component } from 'vue';

defineProps<{
    current: RouteName;
    isDemo: boolean;
}>();

const links: {
    name: RouteName;
    href: string;
    label: string;
    icon: Component;
}[] = [
    { name: 'checkin', href: '#/checkin', label: 'Check-in', icon: Ticket },
    { name: 'machine', href: '#/maquina', label: 'Máquina', icon: Monitor },
    {
        name: 'admin',
        href: '#/admin',
        label: 'Painel',
        icon: LayoutDashboard,
    },
];
</script>

<template>
    <header
        class="border-line bg-void/85 sticky top-0 z-30 border-b backdrop-blur-md"
    >
        <div
            class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6"
        >
            <a href="#/checkin" aria-label="CyberCoffe — início">
                <BrandMark />
            </a>

            <nav aria-label="Navegação principal" class="hidden sm:block">
                <ul class="flex items-center gap-1">
                    <li v-for="link in links" :key="link.name">
                        <a
                            :href="link.href"
                            :aria-current="
                                current === link.name ? 'page' : undefined
                            "
                            class="pixel-corners flex h-10 items-center gap-2 px-4 text-sm font-medium transition-colors"
                            :class="
                                current === link.name
                                    ? 'bg-neon/10 text-neon'
                                    : 'text-dim hover:bg-panel-2 hover:text-ink'
                            "
                        >
                            <component :is="link.icon" class="size-4" />
                            {{ link.label }}
                        </a>
                    </li>
                </ul>
            </nav>

            <span
                v-if="isDemo"
                class="pixel-corners bg-amber/15 text-amber px-3 py-1.5 text-[11px] font-semibold tracking-widest uppercase"
                title="Sem backend configurado: dados ficam só neste navegador"
            >
                Demonstração
            </span>
        </div>
    </header>

    <!-- Fora do <header>: o backdrop-filter dele prenderia o "fixed". -->
    <!-- Barra inferior no celular -->
    <nav
        aria-label="Navegação principal"
        class="border-line bg-void/95 fixed inset-x-0 bottom-0 z-30 border-t backdrop-blur-md sm:hidden"
    >
        <ul class="grid grid-cols-3">
            <li v-for="link in links" :key="link.name">
                <a
                    :href="link.href"
                    :aria-current="current === link.name ? 'page' : undefined"
                    class="flex h-16 flex-col items-center justify-center gap-1 text-[11px] font-medium tracking-wide uppercase"
                    :class="current === link.name ? 'text-neon' : 'text-dim'"
                >
                    <component :is="link.icon" class="size-5" />
                    {{ link.label }}
                </a>
            </li>
        </ul>
    </nav>
</template>
