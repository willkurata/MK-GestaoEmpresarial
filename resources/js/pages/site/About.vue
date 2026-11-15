<script setup lang="ts">
import { Eye, Handshake, MapPinned } from '@lucide/vue';
import PageHero from '@/components/site/PageHero.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import SiteButton from '@/components/site/SiteButton.vue';
import { contact } from '@/routes/site';
import { index as properties } from '@/routes/site/properties';

const principles = [
    {
        icon: Eye,
        title: 'Transparência',
        description:
            'Informações claras sobre cada imóvel e cada serviço, sem letras miúdas.',
    },
    {
        icon: Handshake,
        title: 'Atendimento direto',
        description:
            'Você fala com quem resolve, pelo canal que preferir: WhatsApp, e-mail ou pessoalmente.',
    },
    {
        icon: MapPinned,
        title: 'Conhecimento local',
        description:
            'Atuação em Ubatuba e Caraguatatuba, perto de quem compra, vende, aluga e empreende na região.',
    },
];
</script>

<template>
    <SeoHead
        title="Sobre a MK Negócios"
        description="Conheça a MK Negócios: imóveis, contabilidade e soluções para empresas em Ubatuba e Caraguatatuba, com atendimento direto e transparente."
    />

    <PageHero
        eyebrow="Sobre"
        title="MK Negócios"
        description="Uma empresa do Litoral Norte de São Paulo que reúne imóveis, contabilidade e soluções para empresas, para que clientes resolvam assuntos importantes em um só lugar."
    />

    <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
        <SectionHeading
            eyebrow="Como trabalhamos"
            title="O que orienta o nosso trabalho"
        />
        <ul class="mt-12 grid gap-6 md:grid-cols-3">
            <li
                v-for="principle in principles"
                :key="principle.title"
                class="rounded-lg border border-mk-graphite-100 bg-white p-7"
            >
                <component
                    :is="principle.icon"
                    class="size-7 text-mk-green-600"
                    aria-hidden="true"
                />
                <h3
                    class="mt-5 font-display text-xl font-semibold text-mk-graphite-950"
                >
                    {{ principle.title }}
                </h3>
                <p class="mt-2 leading-relaxed text-mk-graphite-600">
                    {{ principle.description }}
                </p>
            </li>
        </ul>

        <div class="mt-14 flex flex-wrap gap-3">
            <SiteButton :href="properties()">Ver imóveis</SiteButton>
            <SiteButton :href="contact()" variant="secondary">
                Fale com a gente
            </SiteButton>
        </div>
    </section>
</template>
