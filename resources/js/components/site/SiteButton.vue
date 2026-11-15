<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { RouteDefinition } from '@/wayfinder';

/**
 * Botão/link do site. `href` aceita rota Wayfinder (navegação Inertia) ou
 * URL externa em string (WhatsApp, e-mail, MK Gestão), que abre como <a>.
 */
const {
    href,
    variant = 'primary',
    external = false,
} = defineProps<{
    href: RouteDefinition<'get'> | string;
    variant?: 'primary' | 'secondary' | 'whatsapp' | 'ghost';
    external?: boolean;
}>();

const variants = {
    primary:
        'bg-mk-green-700 text-white hover:bg-mk-green-800 focus-visible:outline-mk-green-700',
    secondary:
        'border border-mk-graphite-300 bg-white text-mk-graphite-900 hover:border-mk-graphite-500 focus-visible:outline-mk-graphite-700',
    whatsapp:
        'bg-[#1f7a4d] text-white hover:bg-[#186440] focus-visible:outline-[#1f7a4d]',
    ghost: 'text-mk-green-700 underline-offset-4 hover:underline focus-visible:outline-mk-green-700',
};
</script>

<template>
    <a
        v-if="typeof href === 'string'"
        :href="href"
        :target="external ? '_blank' : undefined"
        :rel="external ? 'noopener noreferrer' : undefined"
        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md px-5 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2"
        :class="variants[variant]"
    >
        <slot />
    </a>
    <Link
        v-else
        :href="href"
        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md px-5 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2"
        :class="variants[variant]"
    >
        <slot />
    </Link>
</template>
