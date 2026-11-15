<script setup lang="ts">
import {
    BookOpenCheck,
    BriefcaseBusiness,
    FileCheck2,
    FileX2,
    MessageCircle,
    UserRound,
} from '@lucide/vue';
import PageHero from '@/components/site/PageHero.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import SiteButton from '@/components/site/SiteButton.vue';
import { useSite } from '@/composables/useSite';
import { serviceWhatsappMessage, whatsappUrl } from '@/lib/site';

const site = useSite();

const services = [
    {
        icon: BookOpenCheck,
        title: 'Contabilidade em geral',
        description:
            'Escrituração, obrigações acessórias e acompanhamento contábil para manter a empresa em dia.',
    },
    {
        icon: FileCheck2,
        title: 'Abertura e regularização',
        description:
            'Orientação e condução da abertura de empresas e da regularização de pendências cadastrais e fiscais.',
    },
    {
        icon: FileX2,
        title: 'Baixa de empresas',
        description:
            'Encerramento formal da empresa, com os procedimentos necessários junto aos órgãos competentes.',
    },
    {
        icon: BriefcaseBusiness,
        title: 'Assessoria contábil',
        description:
            'Apoio para decisões do dia a dia: enquadramento, rotinas financeiras e organização de documentos.',
    },
    {
        icon: UserRound,
        title: 'Contabilidade para MEI',
        description:
            'Suporte ao microempreendedor individual nas obrigações e no crescimento do negócio.',
    },
];
</script>

<template>
    <SeoHead
        title="Contabilidade"
        description="Contabilidade em geral, abertura, regularização e baixa de empresas, assessoria contábil e contabilidade para MEI em Ubatuba e Caraguatatuba."
    />

    <PageHero
        eyebrow="Contabilidade"
        title="Contabilidade próxima para quem empreende no Litoral Norte"
        description="Da abertura ao dia a dia da empresa, a MK Negócios cuida da parte contábil para você focar no seu negócio."
    >
        <SiteButton
            :href="
                whatsappUrl(
                    site.contact.whatsapp,
                    serviceWhatsappMessage('serviços de contabilidade'),
                )
            "
            variant="whatsapp"
            external
        >
            <MessageCircle class="size-4" aria-hidden="true" />
            Falar com a contabilidade
        </SiteButton>
    </PageHero>

    <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
        <SectionHeading eyebrow="Serviços" title="Como podemos ajudar" />
        <ul class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <li
                v-for="service in services"
                :key="service.title"
                class="flex flex-col rounded-lg border border-mk-graphite-100 bg-white p-7"
            >
                <component
                    :is="service.icon"
                    class="size-7 text-mk-green-600"
                    aria-hidden="true"
                />
                <h3
                    class="mt-5 font-display text-xl font-semibold text-mk-graphite-950"
                >
                    {{ service.title }}
                </h3>
                <p class="mt-2 flex-1 leading-relaxed text-mk-graphite-600">
                    {{ service.description }}
                </p>
                <a
                    :href="
                        whatsappUrl(
                            site.contact.whatsapp,
                            serviceWhatsappMessage(service.title.toLowerCase()),
                        )
                    "
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-5 text-sm font-semibold text-mk-green-700 underline-offset-4 hover:underline"
                >
                    Consultar pelo WhatsApp
                    <span class="sr-only">sobre {{ service.title }}</span>
                </a>
            </li>
        </ul>
    </section>
</template>
