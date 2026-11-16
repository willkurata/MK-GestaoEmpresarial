<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Building2,
    Calculator,
    Check,
    Handshake,
    House,
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
import { serviceWhatsappMessage, whatsappUrl } from '@/lib/site';
import { about, accounting, business, contact } from '@/routes/site';
import { index as properties } from '@/routes/site/properties';
import type { PropertySummary } from '@/types/site';

defineProps<{ featured_properties: PropertySummary[] }>();

const site = useSite();

/*
 * Destaques do carrossel (WIL-27): Imóveis, Contabilidade e Empresa. Cada
 * slide leva à página da área no site; o acesso ao sistema MK Gestão fica só
 * no botão "Área do cliente".
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
        whatsappMessage:
            'Olá! Vim pelo site da MK Negócios e procuro um imóvel no Litoral Norte.',
        tone: 'graphite',
    },
    {
        id: 'contabilidade',
        eyebrow: 'Contabilidade',
        title: 'A sua empresa em dia, sem complicação.',
        description:
            'Abertura, regularização e baixa de empresas, assessoria contábil e atendimento a MEI.',
        icon: Calculator,
        cta: { label: 'Conhecer os serviços', href: accounting() },
        whatsappMessage: serviceWhatsappMessage('contabilidade'),
        tone: 'green',
    },
    {
        id: 'empresa',
        eyebrow: 'Empresa · MK Gestão App',
        title: 'Mais organização para o seu negócio.',
        description:
            'Caixa, vendas, despesas e relatórios em um só lugar, com o apoio da MK para abrir e regularizar a sua empresa.',
        icon: Building2,
        cta: { label: 'Soluções para empresas', href: business() },
        whatsappMessage: serviceWhatsappMessage(
            'soluções para a minha empresa',
        ),
        tone: 'forest',
    },
];

/* "O que fazemos": serviços de cada frente, com link para a área. */
const services = [
    {
        icon: House,
        title: 'Imóveis',
        description:
            'Para quem quer morar, investir ou anunciar no Litoral Norte.',
        items: [
            'Compra e venda de imóveis',
            'Locação residencial',
            'Anúncio do seu imóvel',
            'Atendimento em Ubatuba e Caraguatatuba',
        ],
        href: properties(),
        cta: 'Ver imóveis',
    },
    {
        icon: Calculator,
        title: 'Contabilidade',
        description: 'Para manter a empresa em dia, do MEI à pequena empresa.',
        items: [
            'Contabilidade em geral',
            'Abertura, regularização e baixa',
            'Assessoria contábil',
            'Contabilidade para MEI',
        ],
        href: accounting(),
        cta: 'Conhecer serviços',
    },
    {
        icon: LayoutDashboard,
        title: 'Empresa · MK Gestão App',
        description: 'Para organizar o dia a dia do pequeno negócio.',
        items: [
            'Frente de caixa (dinheiro e Pix)',
            'Registro de despesas',
            'Relatórios por período',
            'Painel do negócio',
        ],
        href: business(),
        cta: 'Conhecer o app',
    },
];

/*
 * Planos: formatos de atendimento. Sem preços publicados — a MK ainda não
 * definiu valores, então cada plano é "sob consulta" e o botão abre o
 * WhatsApp já identificando o plano escolhido.
 */
const plans = [
    {
        name: 'MEI',
        summary: 'Para o microempreendedor individual que quer ficar em dia.',
        features: [
            'Acompanhamento das obrigações do MEI',
            'Orientação para crescer e mudar de porte',
            'Atendimento direto pelo WhatsApp',
        ],
        featured: false,
    },
    {
        name: 'Empresa',
        summary: 'Contabilidade completa para a pequena empresa.',
        features: [
            'Contabilidade em geral e obrigações acessórias',
            'Assessoria contábil para decisões do dia a dia',
            'Abertura, regularização e baixa quando precisar',
        ],
        featured: true,
    },
    {
        name: 'Empresa + MK Gestão App',
        summary: 'Contabilidade e o sistema de gestão da MK juntos.',
        features: [
            'Tudo do plano Empresa',
            'Acesso ao MK Gestão App: caixa, vendas e despesas',
            'Relatórios para acompanhar o negócio',
        ],
        featured: false,
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

    <section id="o-que-fazemos" class="bg-white">
        <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-24">
            <SectionHeading
                eyebrow="O que fazemos"
                title="Três frentes, um mesmo atendimento"
                description="Escolha a área e conheça o que a MK faz em cada uma delas antes de falar com a gente."
                align="center"
            />
            <div class="mt-14 grid gap-6 md:grid-cols-3">
                <Link
                    v-for="service in services"
                    :key="service.title"
                    :href="service.href"
                    class="group flex flex-col rounded-3xl border border-mk-graphite-100 bg-mk-paper p-8 transition hover:-translate-y-1 hover:border-mk-green-300 hover:shadow-xl hover:shadow-mk-graphite-950/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mk-green-700 motion-reduce:transition-none motion-reduce:hover:translate-y-0"
                >
                    <span
                        class="inline-flex size-14 items-center justify-center rounded-2xl bg-mk-green-100 text-mk-green-700"
                    >
                        <component
                            :is="service.icon"
                            class="size-7"
                            aria-hidden="true"
                        />
                    </span>
                    <h3
                        class="mt-6 font-display text-2xl font-semibold text-mk-graphite-950"
                    >
                        {{ service.title }}
                    </h3>
                    <p class="mt-2 leading-relaxed text-mk-graphite-600">
                        {{ service.description }}
                    </p>
                    <ul class="mt-6 flex-1 space-y-3">
                        <li
                            v-for="item in service.items"
                            :key="item"
                            class="flex gap-3 text-sm text-mk-graphite-700"
                        >
                            <Check
                                class="mt-0.5 size-4 shrink-0 text-mk-green-600"
                                aria-hidden="true"
                            />
                            {{ item }}
                        </li>
                    </ul>
                    <span
                        class="mt-8 inline-flex items-center gap-2 text-sm font-semibold text-mk-green-700"
                    >
                        {{ service.cta }}
                        <ArrowRight
                            class="size-4 transition-transform group-hover:translate-x-1"
                            aria-hidden="true"
                        />
                    </span>
                </Link>
            </div>
        </div>
    </section>

    <section id="planos" class="border-y border-mk-graphite-100 bg-mk-sand">
        <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-24">
            <SectionHeading
                eyebrow="Planos"
                title="Um formato de atendimento para cada momento"
                description="Os valores dependem do porte e da rotina de cada negócio. Escolha o plano e montamos uma proposta para você."
                align="center"
            />
            <div class="mt-16 grid gap-8 lg:grid-cols-3 lg:items-center">
                <article
                    v-for="plan in plans"
                    :key="plan.name"
                    class="relative flex flex-col rounded-3xl p-8"
                    :class="
                        plan.featured
                            ? 'bg-mk-green-800 text-white shadow-2xl shadow-mk-green-950/20 lg:py-12'
                            : 'border border-mk-graphite-100 bg-white text-mk-graphite-950'
                    "
                >
                    <p
                        v-if="plan.featured"
                        class="absolute -top-3.5 left-8 rounded-full bg-mk-green-300 px-4 py-1 text-xs font-semibold text-mk-green-950"
                    >
                        Mais procurado
                    </p>
                    <h3 class="font-display text-2xl font-semibold">
                        {{ plan.name }}
                    </h3>
                    <p
                        class="mt-2 text-sm leading-relaxed"
                        :class="
                            plan.featured
                                ? 'text-mk-green-100'
                                : 'text-mk-graphite-600'
                        "
                    >
                        {{ plan.summary }}
                    </p>
                    <p class="mt-6 font-display text-3xl font-semibold">
                        Sob consulta
                    </p>
                    <ul class="mt-6 flex-1 space-y-3">
                        <li
                            v-for="feature in plan.features"
                            :key="feature"
                            class="flex gap-3 text-sm"
                            :class="
                                plan.featured
                                    ? 'text-mk-green-50'
                                    : 'text-mk-graphite-700'
                            "
                        >
                            <Check
                                class="mt-0.5 size-4 shrink-0"
                                :class="
                                    plan.featured
                                        ? 'text-mk-green-300'
                                        : 'text-mk-green-600'
                                "
                                aria-hidden="true"
                            />
                            {{ feature }}
                        </li>
                    </ul>
                    <SiteButton
                        :href="
                            whatsappUrl(
                                site.contact.whatsapp,
                                serviceWhatsappMessage(`o plano ${plan.name}`),
                            )
                        "
                        :variant="plan.featured ? 'light' : 'primary'"
                        external
                        class="mt-8 w-full"
                    >
                        <MessageCircle class="size-4" aria-hidden="true" />
                        Pedir proposta
                    </SiteButton>
                </article>
            </div>
            <p class="mt-12 text-center text-sm text-mk-graphite-600">
                Procura um imóvel? Não precisa de plano:
                <Link
                    :href="properties()"
                    class="font-semibold text-mk-green-700 underline-offset-4 hover:underline"
                    >veja os imóveis disponíveis</Link
                >.
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-24">
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
                <SiteButton :href="about()" variant="ghost" class="-ml-6">
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

    <section
        v-if="featured_properties.length > 0"
        class="border-y border-mk-graphite-100 bg-white"
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
            class="grid gap-10 rounded-3xl bg-mk-green-800 bg-[radial-gradient(ellipse_at_top_right,var(--color-mk-green-600),transparent_60%)] p-8 text-white sm:p-12 md:grid-cols-[1.5fr_1fr] md:items-center"
        >
            <div class="max-w-2xl">
                <p
                    class="text-xs font-semibold tracking-[0.25em] text-mk-green-200 uppercase"
                >
                    Vamos conversar?
                </p>
                <h2
                    class="mt-3 font-display text-3xl leading-tight font-semibold sm:text-4xl"
                >
                    Conte o que você precisa
                </h2>
                <p class="mt-4 leading-relaxed text-mk-green-50">
                    Imóvel, contabilidade ou o MK Gestão App: fale com a MK e
                    indicamos o melhor caminho.
                </p>
            </div>
            <div class="flex flex-col gap-3 md:items-end">
                <SiteButton
                    :href="
                        whatsappUrl(
                            site.contact.whatsapp,
                            'Olá! Vim pela página inicial da MK Negócios e gostaria de atendimento.',
                        )
                    "
                    variant="light"
                    external
                >
                    <MessageCircle class="size-4" aria-hidden="true" />
                    Falar pelo WhatsApp
                </SiteButton>
                <Link
                    :href="contact()"
                    class="inline-flex min-h-11 items-center justify-center rounded-full border border-white/40 px-6 text-sm font-semibold text-white transition-colors hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                >
                    Outras formas de contato
                </Link>
            </div>
        </div>
    </section>
</template>
