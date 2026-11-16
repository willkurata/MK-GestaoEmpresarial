<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Menu, X } from '@lucide/vue';
import { onUnmounted, ref } from 'vue';
import SiteLogo from '@/components/site/SiteLogo.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useSite } from '@/composables/useSite';
import { about, accounting, business, contact, home } from '@/routes/site';
import { index as properties } from '@/routes/site/properties';

const site = useSite();
const { isCurrentOrParentUrl } = useCurrentUrl();
const menuOpen = ref(false);

/*
 * Menu principal (WIL-27). "MK Gestão App" leva à página que apresenta o
 * sistema, não ao login: o acesso ao sistema fica só em "Área do cliente".
 */
const navigation = [
    { label: 'Contabilidade', href: accounting() },
    { label: 'Imóveis', href: properties() },
    { label: 'MK Gestão App', href: business() },
    { label: 'Contate-nos', href: contact() },
    { label: 'Sobre a Gente', href: about() },
];

onUnmounted(
    router.on('navigate', () => {
        menuOpen.value = false;
    }),
);
</script>

<template>
    <header
        class="sticky top-0 z-40 border-b border-mk-graphite-100 bg-mk-paper/95 backdrop-blur"
    >
        <div
            class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-6 px-4 sm:px-6"
        >
            <Link
                :href="home()"
                class="rounded focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-mk-green-700"
            >
                <SiteLogo />
            </Link>

            <nav
                aria-label="Principal"
                class="hidden items-center gap-1 lg:flex"
            >
                <Link
                    v-for="item in navigation"
                    :key="item.label"
                    :href="item.href"
                    class="rounded-md px-3 py-2 text-sm font-medium transition-colors hover:text-mk-green-700"
                    :class="
                        isCurrentOrParentUrl(item.href)
                            ? 'text-mk-green-700'
                            : 'text-mk-graphite-700'
                    "
                    :aria-current="
                        isCurrentOrParentUrl(item.href) ? 'page' : undefined
                    "
                >
                    {{ item.label }}
                </Link>
                <a
                    :href="site.gestao_url"
                    class="ml-3 rounded-md border border-mk-graphite-300 px-4 py-2 text-sm font-semibold text-mk-graphite-900 transition-colors hover:border-mk-green-600 hover:text-mk-green-700"
                >
                    Área do cliente
                </a>
            </nav>

            <button
                type="button"
                class="inline-flex size-11 items-center justify-center rounded-md text-mk-graphite-900 lg:hidden"
                :aria-expanded="menuOpen"
                aria-controls="site-mobile-menu"
                @click="menuOpen = !menuOpen"
            >
                <X v-if="menuOpen" class="size-6" aria-hidden="true" />
                <Menu v-else class="size-6" aria-hidden="true" />
                <span class="sr-only">{{
                    menuOpen ? 'Fechar menu' : 'Abrir menu'
                }}</span>
            </button>
        </div>

        <nav
            v-show="menuOpen"
            id="site-mobile-menu"
            aria-label="Principal"
            class="border-t border-mk-graphite-100 bg-mk-paper px-4 pb-6 lg:hidden"
        >
            <Link
                v-for="item in navigation"
                :key="item.label"
                :href="item.href"
                class="block border-b border-mk-graphite-100 py-3 text-base font-medium"
                :class="
                    isCurrentOrParentUrl(item.href)
                        ? 'text-mk-green-700'
                        : 'text-mk-graphite-800'
                "
            >
                {{ item.label }}
            </Link>
            <a
                :href="site.gestao_url"
                class="mt-4 flex min-h-11 items-center justify-center rounded-md border border-mk-graphite-300 text-sm font-semibold text-mk-graphite-900"
            >
                Área do cliente
            </a>
        </nav>
    </header>
</template>
