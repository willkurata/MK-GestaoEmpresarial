<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Calculator,
    Handshake,
    House,
    KeyRound,
    LayoutDashboard,
    MapPinned,
    MessageCircle,
    Waypoints,
} from '@lucide/vue';
import HomeCarousel from '@/components/site/HomeCarousel.vue';
import type { CarouselSlide } from '@/components/site/HomeCarousel.vue';
import PropertyCard from '@/components/site/PropertyCard.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import SiteButton from '@/components/site/SiteButton.vue';
import { useSite } from '@/composables/useSite';
import { whatsappUrl } from '@/lib/site';
import { about, accounting, business, contact } from '@/routes/site';
import { index as properties } from '@/routes/site/properties';
import type { PropertySummary } from '@/types/site';

defineProps<{ featured_properties: PropertySummary[] }>();

const site = useSite();

/*
 * Destaques do carrossel (WIL-27). Cada slide leva à página da área no site;
 * o acesso ao sistema MK Gestão fica só no botão "Área do cliente".
 */
const slides: CarouselSlide[] = [
    {
        id: 'imoveis',
        eyebrow: 'Imóveis · Ubatuba e Caraguatatuba',
        title: 'O seu próximo imóvel no Litoral Norte começa aqui.',
        description:
            'Imóveis para comprar ou alugar, com anúncios claros e atendimento próximo do início ao fim.',
        icon: House,
        cta: { label: 'Ver imóveis', href: properties() },
        tone: 'graphite',
    },
    {
        id: 'mk-gestao',
        eyebrow: 'MK Gestão App',
        title: 'A gestão do seu negócio, simples assim.',
        description:
            'Caixa, vendas, despesas e relatórios em um só lugar, pensado para pequenos negócios.',
        icon: LayoutDashboard,
        cta: { label: 'Conhecer o MK Gestão', href: business() },
        tone: 'green',
    },
    {
        id: 'contabilidade',
        eyebrow: 'Contabilidade',
        title: 'A sua empresa em dia, sem complicação.',
        description:
            'Abertura, regularização e baixa de empresas, assessoria contábil e atendimento a MEI.',
        icon: Calculator,
        cta: { label: 'Conhecer os serviços', href: accounting() },
        tone: 'graphite',
    },
    {
        id: 'anuncie',
        eyebrow: 'Anuncie com a MK',
        title: 'Quer vender ou alugar o seu imóvel?',
        description:
            'Conte sobre o imóvel e avaliamos juntos a melhor forma de anunciar.',
        icon: KeyRound,
        cta: { label: 'Fale com a gente', href: contact() },
        tone: 'green',
    },
];

const pillars = [
    {
        icon: Waypoints,
        title: 'Tudo em um só lugar',
        description:
            'Imóveis, contabilidade e gestão do negócio com a mesma equipe, sem precisar repetir a sua história a cada etapa.',
    },
    {
        icon: Handshake,
        title: 'Atendimento direto',
        description:
            'Você fala com quem resolve, pelo WhatsApp, por e-mail ou pessoalmente.',
    },
    {
        icon: MapPinned,
        title: 'Conhecimento local',
        description:
            'Atuação em Ubatuba e Caraguatatuba, perto de quem vive, investe e empreende na região.',
    },
];

const areas = [
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
        icon: House,
        title: 'Imóveis',
        description:
            'Compra, venda e locação de imóveis no Litoral Norte, com anúncios claros e atendimento próximo do início ao fim.',
        href: properties(),
        cta: 'Ver imóveis',
        featured: true,
    },
    {
        icon: LayoutDashboard,
        title: 'MK Gestão App',
        description:
            'O sistema da MK para pequenos negócios: caixa, vendas, despesas e relatórios em um só lugar.',
        href: business(),
        cta: 'Conhecer o app',
        featured: false,
    },
];
</script>

<template>
    <SeoHead
        title="Imóveis, contabilidade e o MK Gestão App"
        description="MK Negócios: imóveis à venda e para locação, contabilidade e o MK Gestão App para pequenos negócios em Ubatuba e Caraguatatuba, Litoral Norte de São Paulo."
    />

    <h1 class="sr-only">
        MK Negócios: imóveis, contabilidade e o MK Gestão App no Litoral Norte
        de São Paulo
    </h1>

    <HomeCarousel :slides="slides" />

    <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
        <div class="grid gap-12 md:grid-cols-[1.1fr_1fr] md:items-start">
            <SectionHeading
                eyebrow="Quem somos"
                title="A MK Negócios cuida do que é importante para você e para o seu negócio"
            />
            <div
                class="space-y-4 text-base leading-relaxed text-mk-graphite-700"
            >
                <p>
                    A MK Negócios é uma empresa do Litoral Norte de São Paulo,
                    com atendimento em Ubatuba e Caraguatatuba. Reunimos em um
                    só lugar três frentes que costumam andar juntas na vida de
                    quem mora, investe ou empreende na região.
                </p>
                <p>
                    Ajudamos a comprar, vender e alugar imóveis; cuidamos da
                    contabilidade e da regularização de empresas, inclusive MEI;
                    e oferecemos o MK Gestão App, o nosso sistema para pequenos
                    negócios organizarem caixa, vendas e despesas.
                </p>
                <SiteButton :href="about()" variant="ghost" class="-ml-5">
                    Conheça a MK Negócios
                    <ArrowRight class="size-4" aria-hidden="true" />
                </SiteButton>
            </div>
        </div>
        <ul class="mt-14 grid gap-8 md:grid-cols-3">
            <li
                v-for="pillar in pillars"
                :key="pillar.title"
                class="border-t-2 border-mk-green-600 pt-5"
            >
                <component
                    :is="pillar.icon"
                    class="size-7 text-mk-green-600"
                    aria-hidden="true"
                />
                <h3
                    class="mt-4 font-display text-xl font-semibold text-mk-graphite-950"
                >
                    {{ pillar.title }}
                </h3>
                <p class="mt-2 text-sm leading-relaxed text-mk-graphite-600">
                    {{ pillar.description }}
                </p>
            </li>
        </ul>
    </section>

    <section class="border-t border-mk-graphite-100 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
            <SectionHeading
                eyebrow="O que fazemos"
                title="Três frentes, um mesmo atendimento"
                description="Escolha a área e conheça o que a MK faz em cada uma delas antes de falar com a gente."
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
                            : 'border-mk-graphite-100 bg-mk-paper hover:border-mk-green-400'
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
                        :class="
                            area.featured ? 'text-white' : 'text-mk-green-700'
                        "
                    >
                        {{ area.cta }}
                        <ArrowRight
                            class="size-4 transition-transform group-hover:translate-x-1"
                            aria-hidden="true"
                        />
                    </span>
                </Link>
            </div>
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
                eyebrow="Vamos conversar?"
                title="Conte o que você precisa"
                description="Imóvel, contabilidade ou o MK Gestão App: fale com a MK e indicamos o melhor caminho."
            />
            <div class="flex flex-col gap-3 md:items-end">
                <SiteButton
                    :href="
                        whatsappUrl(
                            site.contact.whatsapp,
                            'Olá! Vim pela página inicial da MK Negócios e gostaria de atendimento.',
                        )
                    "
                    variant="whatsapp"
                    external
                >
                    <MessageCircle class="size-4" aria-hidden="true" />
                    Falar pelo WhatsApp
                </SiteButton>
                <SiteButton :href="contact()" variant="ghost">
                    Outras formas de contato
                </SiteButton>
            </div>
        </div>
    </section>
</template>
