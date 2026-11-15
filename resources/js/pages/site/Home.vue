<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Building2,
    Calculator,
    House,
    MapPin,
    MessageCircle,
} from '@lucide/vue';
import BrandBars from '@/components/site/BrandBars.vue';
import PropertyCard from '@/components/site/PropertyCard.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import SiteButton from '@/components/site/SiteButton.vue';
import { useSite } from '@/composables/useSite';
import { whatsappUrl } from '@/lib/site';
import { accounting, business, contact } from '@/routes/site';
import { index as properties } from '@/routes/site/properties';
import type { PropertySummary } from '@/types/site';

defineProps<{ featured_properties: PropertySummary[] }>();

const site = useSite();

const areas = [
    {
        icon: House,
        title: 'Imóveis',
        description:
            'Compra, venda e locação de imóveis no Litoral Norte, com anúncios claros e atendimento próximo do início ao fim.',
        href: properties(),
        cta: 'Ver imóveis',
        featured: true,
    },
    {
        icon: Calculator,
        title: 'Contabilidade',
        description:
            'Contabilidade em geral, abertura, regularização e baixa de empresas, assessoria contábil e atendimento a MEI.',
        href: accounting(),
        cta: 'Conhecer serviços',
        featured: false,
    },
    {
        icon: Building2,
        title: 'Empresas',
        description:
            'Soluções para organizar o seu negócio, incluindo o MK Gestão: caixa, vendas, despesas e relatórios em um só lugar.',
        href: business(),
        cta: 'Soluções para empresas',
        featured: false,
    },
];
</script>

<template>
    <SeoHead
        title="Imóveis, contabilidade e soluções para empresas"
        description="MK Negócios: imóveis à venda e para locação, contabilidade e soluções para empresas em Ubatuba e Caraguatatuba, Litoral Norte de São Paulo."
    />

    <section class="relative overflow-hidden bg-mk-graphite-950 text-white">
        <BrandBars
            class="absolute -right-10 bottom-0 size-[22rem] text-mk-green-700/60 sm:size-[30rem]"
        />
        <div
            class="relative mx-auto max-w-6xl px-4 pt-20 pb-24 sm:px-6 sm:pt-28 sm:pb-32"
        >
            <p
                class="flex items-center gap-2 text-xs font-semibold tracking-[0.25em] text-mk-green-300 uppercase"
            >
                <MapPin class="size-4" aria-hidden="true" />
                Ubatuba · Caraguatatuba
            </p>
            <h1
                class="mt-5 max-w-3xl font-display text-5xl leading-[1.05] font-semibold sm:text-6xl"
            >
                O seu próximo imóvel no Litoral Norte começa aqui.
            </h1>
            <p
                class="mt-6 max-w-xl text-lg leading-relaxed text-mk-graphite-200"
            >
                A MK Negócios reúne imóveis, contabilidade e soluções para
                empresas, com atendimento direto e transparente.
            </p>
            <div class="mt-10 flex flex-wrap gap-3">
                <SiteButton :href="properties()">
                    Ver imóveis
                    <ArrowRight class="size-4" aria-hidden="true" />
                </SiteButton>
                <SiteButton
                    :href="
                        whatsappUrl(
                            site.contact.whatsapp,
                            'Olá! Quero anunciar ou encontrar um imóvel com a MK Negócios.',
                        )
                    "
                    variant="secondary"
                    external
                >
                    <MessageCircle class="size-4" aria-hidden="true" />
                    Falar no WhatsApp
                </SiteButton>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
        <SectionHeading
            eyebrow="O que fazemos"
            title="Três frentes, um mesmo atendimento"
            description="Imóveis são o nosso foco principal. Contabilidade e soluções para empresas completam o apoio para quem vive, investe ou empreende na região."
        />
        <div class="mt-12 grid gap-6 md:grid-cols-3">
            <Link
                v-for="area in areas"
                :key="area.title"
                :href="area.href"
                class="group flex flex-col rounded-lg border p-7 transition-colors"
                :class="
                    area.featured
                        ? 'border-mk-green-700 bg-mk-green-700 text-white hover:bg-mk-green-800'
                        : 'border-mk-graphite-100 bg-white hover:border-mk-green-400'
                "
            >
                <component
                    :is="area.icon"
                    class="size-8"
                    :class="
                        area.featured
                            ? 'text-mk-green-200'
                            : 'text-mk-green-600'
                    "
                    aria-hidden="true"
                />
                <h3 class="mt-6 font-display text-2xl font-semibold">
                    {{ area.title }}
                </h3>
                <p
                    class="mt-3 flex-1 leading-relaxed"
                    :class="
                        area.featured
                            ? 'text-mk-green-50'
                            : 'text-mk-graphite-600'
                    "
                >
                    {{ area.description }}
                </p>
                <span
                    class="mt-6 inline-flex items-center gap-2 text-sm font-semibold"
                    :class="area.featured ? 'text-white' : 'text-mk-green-700'"
                >
                    {{ area.cta }}
                    <ArrowRight
                        class="size-4 transition-transform group-hover:translate-x-1"
                        aria-hidden="true"
                    />
                </span>
            </Link>
        </div>
    </section>

    <section
        v-if="featured_properties.length > 0"
        class="border-y border-mk-graphite-100 bg-mk-sand"
    >
        <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <SectionHeading
                    eyebrow="Destaques"
                    title="Imóveis em destaque"
                />
                <SiteButton :href="properties()" variant="ghost">
                    Ver todos
                    <ArrowRight class="size-4" aria-hidden="true" />
                </SiteButton>
            </div>
            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <PropertyCard
                    v-for="property in featured_properties"
                    :key="property.reference"
                    :property="property"
                />
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
        <div
            class="grid gap-10 rounded-lg bg-mk-green-50 p-8 sm:p-12 md:grid-cols-[1.5fr_1fr] md:items-center"
        >
            <SectionHeading
                eyebrow="Quer vender ou alugar?"
                title="Anuncie o seu imóvel com a MK Negócios"
                description="Conte sobre o imóvel e avaliamos juntos a melhor forma de anunciar. Cada anúncio recebe um código de referência e página própria."
            />
            <div class="flex flex-col gap-3 md:items-end">
                <SiteButton
                    :href="
                        whatsappUrl(
                            site.contact.whatsapp,
                            'Olá! Quero anunciar meu imóvel com a MK Negócios.',
                        )
                    "
                    variant="whatsapp"
                    external
                >
                    <MessageCircle class="size-4" aria-hidden="true" />
                    Anunciar pelo WhatsApp
                </SiteButton>
                <SiteButton :href="contact()" variant="ghost">
                    Outras formas de contato
                </SiteButton>
            </div>
        </div>
    </section>
</template>
