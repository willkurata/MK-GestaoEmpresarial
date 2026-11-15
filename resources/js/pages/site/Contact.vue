<script setup lang="ts">
import { Mail, MapPin, MessageCircle } from '@lucide/vue';
import PageHero from '@/components/site/PageHero.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import SiteButton from '@/components/site/SiteButton.vue';
import { useSite } from '@/composables/useSite';
import { whatsappUrl } from '@/lib/site';

const site = useSite();
</script>

<template>
    <SeoHead
        title="Contato"
        description="Fale com a MK Negócios pelo WhatsApp (12) 99729-8895 ou por e-mail. Atendimento em Ubatuba e Caraguatatuba."
    />

    <PageHero
        eyebrow="Contato"
        title="Fale com a MK Negócios"
        description="Escolha o canal mais prático para você. Se o assunto for um imóvel, informe o código de referência do anúncio para agilizar."
    />

    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <ul class="grid gap-6 md:grid-cols-3">
            <li
                class="flex flex-col rounded-lg border border-mk-graphite-100 bg-white p-7"
            >
                <MessageCircle
                    class="size-7 text-[#1f7a4d]"
                    aria-hidden="true"
                />
                <h2
                    class="mt-5 font-display text-xl font-semibold text-mk-graphite-950"
                >
                    WhatsApp
                </h2>
                <p class="mt-1 text-mk-graphite-600">
                    {{ site.contact.whatsapp_display }}
                </p>
                <div class="mt-6">
                    <SiteButton
                        :href="
                            whatsappUrl(
                                site.contact.whatsapp,
                                'Olá! Vim pela página de contato da MK Negócios.',
                            )
                        "
                        variant="whatsapp"
                        external
                    >
                        Iniciar conversa
                    </SiteButton>
                </div>
            </li>
            <li
                class="flex flex-col rounded-lg border border-mk-graphite-100 bg-white p-7"
            >
                <Mail class="size-7 text-mk-green-600" aria-hidden="true" />
                <h2
                    class="mt-5 font-display text-xl font-semibold text-mk-graphite-950"
                >
                    E-mail
                </h2>
                <p class="mt-1 break-all text-mk-graphite-600">
                    {{ site.contact.email }}
                </p>
                <div class="mt-6">
                    <SiteButton
                        :href="`mailto:${site.contact.email}`"
                        variant="secondary"
                    >
                        Enviar e-mail
                    </SiteButton>
                </div>
            </li>
            <li
                class="flex flex-col rounded-lg border border-mk-graphite-100 bg-white p-7"
            >
                <MapPin class="size-7 text-mk-green-600" aria-hidden="true" />
                <h2
                    class="mt-5 font-display text-xl font-semibold text-mk-graphite-950"
                >
                    Onde atendemos
                </h2>
                <ul class="mt-1 text-mk-graphite-600">
                    <li v-for="city in site.cities" :key="city">
                        {{ city }} — SP
                    </li>
                </ul>
            </li>
        </ul>
    </section>
</template>
