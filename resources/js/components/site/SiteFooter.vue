<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import SiteLogo from '@/components/site/SiteLogo.vue';
import { useSite } from '@/composables/useSite';
import { whatsappUrl } from '@/lib/site';
import { about, accounting, business, contact, privacy } from '@/routes/site';
import { index as properties } from '@/routes/site/properties';

const site = useSite();
const year = new Date().getFullYear();
</script>

<template>
    <footer class="bg-mk-graphite-950 text-mk-graphite-200">
        <div
            class="mx-auto grid max-w-6xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-[1.4fr_1fr_1fr]"
        >
            <div>
                <SiteLogo inverted />
                <p
                    class="mt-5 max-w-sm text-sm leading-relaxed text-mk-graphite-300"
                >
                    Imóveis, contabilidade e soluções para empresas no Litoral
                    Norte de São Paulo.
                </p>
                <p class="mt-4 text-sm text-mk-graphite-400">
                    Atendimento em {{ site.cities.join(' e ') }} — SP
                </p>
            </div>

            <nav aria-label="Rodapé">
                <p
                    class="text-xs font-semibold tracking-[0.2em] text-mk-green-300 uppercase"
                >
                    Navegação
                </p>
                <ul class="mt-4 space-y-2 text-sm">
                    <li>
                        <Link :href="properties()" class="hover:text-white"
                            >Imóveis</Link
                        >
                    </li>
                    <li>
                        <Link :href="accounting()" class="hover:text-white"
                            >Contabilidade</Link
                        >
                    </li>
                    <li>
                        <Link :href="business()" class="hover:text-white"
                            >Empresas</Link
                        >
                    </li>
                    <li>
                        <Link :href="about()" class="hover:text-white"
                            >Sobre</Link
                        >
                    </li>
                    <li>
                        <Link :href="contact()" class="hover:text-white"
                            >Contato</Link
                        >
                    </li>
                    <li>
                        <a :href="site.gestao_url" class="hover:text-white"
                            >Área do cliente (MK Gestão)</a
                        >
                    </li>
                </ul>
            </nav>

            <div>
                <p
                    class="text-xs font-semibold tracking-[0.2em] text-mk-green-300 uppercase"
                >
                    Fale com a MK
                </p>
                <ul class="mt-4 space-y-2 text-sm">
                    <li>
                        <a
                            :href="
                                whatsappUrl(
                                    site.contact.whatsapp,
                                    'Olá! Vim pelo site da MK Negócios.',
                                )
                            "
                            target="_blank"
                            rel="noopener noreferrer"
                            class="hover:text-white"
                        >
                            WhatsApp {{ site.contact.whatsapp_display }}
                        </a>
                    </li>
                    <li>
                        <a
                            :href="`mailto:${site.contact.email}`"
                            class="hover:text-white"
                        >
                            {{ site.contact.email }}
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="border-t border-mk-graphite-800">
            <div
                class="mx-auto flex max-w-6xl flex-col gap-2 px-4 py-6 text-xs text-mk-graphite-400 sm:flex-row sm:items-center sm:justify-between sm:px-6"
            >
                <p>
                    © {{ year }} MK Negócios
                    <template v-if="site.creci">
                        · CRECI {{ site.creci }}</template
                    >
                    <template v-if="site.crc"> · CRC {{ site.crc }}</template>
                </p>
                <Link :href="privacy()" class="hover:text-white">
                    Política de privacidade
                </Link>
            </div>
        </div>
    </footer>
</template>
