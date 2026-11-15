<script setup lang="ts">
import {
    ChartColumn,
    FileCheck2,
    LayoutDashboard,
    MessageCircle,
    ReceiptText,
    Wallet,
} from '@lucide/vue';
import PageHero from '@/components/site/PageHero.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import SiteButton from '@/components/site/SiteButton.vue';
import { useSite } from '@/composables/useSite';
import { serviceWhatsappMessage, whatsappUrl } from '@/lib/site';
import { accounting } from '@/routes/site';

const site = useSite();

const gestaoFeatures = [
    {
        icon: Wallet,
        title: 'Frente de caixa',
        description:
            'Abertura, movimentação e fechamento de caixa, com vendas em dinheiro e Pix.',
    },
    {
        icon: ReceiptText,
        title: 'Despesas',
        description: 'Registro de despesas fixas e variáveis do negócio.',
    },
    {
        icon: ChartColumn,
        title: 'Relatórios',
        description:
            'Vendas, despesas e produtos por período, gerados quando você precisar.',
    },
    {
        icon: LayoutDashboard,
        title: 'Painel',
        description: 'Uma visão rápida de como o negócio está indo.',
    },
];
</script>

<template>
    <SeoHead
        title="Soluções para empresas"
        description="Organize o seu negócio com o MK Gestão — caixa, vendas, despesas e relatórios — e conte com a MK Negócios para abrir ou regularizar a sua empresa."
    />

    <PageHero
        eyebrow="Empresas"
        title="Mais organização para o seu negócio"
        description="Ferramentas e apoio para pequenos negócios: do sistema de gestão do dia a dia à abertura e regularização da empresa."
    >
        <SiteButton
            :href="
                whatsappUrl(
                    site.contact.whatsapp,
                    serviceWhatsappMessage('soluções para a minha empresa'),
                )
            "
            variant="whatsapp"
            external
        >
            <MessageCircle class="size-4" aria-hidden="true" />
            Falar com a MK
        </SiteButton>
        <SiteButton :href="site.gestao_url" variant="secondary">
            Área do cliente
        </SiteButton>
    </PageHero>

    <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
        <SectionHeading
            eyebrow="MK Gestão"
            title="Gestão simples para o dia a dia"
            description="O MK Gestão é o sistema da MK para pequenos negócios: transforma o registro simples de vendas e despesas em informação útil para decidir."
        />
        <ul class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <li
                v-for="feature in gestaoFeatures"
                :key="feature.title"
                class="rounded-lg border border-mk-graphite-100 bg-white p-6"
            >
                <component
                    :is="feature.icon"
                    class="size-7 text-mk-green-600"
                    aria-hidden="true"
                />
                <h3
                    class="mt-4 font-display text-xl font-semibold text-mk-graphite-950"
                >
                    {{ feature.title }}
                </h3>
                <p class="mt-2 text-sm leading-relaxed text-mk-graphite-600">
                    {{ feature.description }}
                </p>
            </li>
        </ul>
        <p class="mt-8 text-sm text-mk-graphite-500">
            O acesso ao MK Gestão é liberado pela equipe MK para cada empresa
            cliente.
        </p>
    </section>

    <section class="border-t border-mk-graphite-100 bg-mk-sand">
        <div
            class="mx-auto grid max-w-6xl gap-8 px-4 py-16 sm:px-6 md:grid-cols-[auto_1fr_auto] md:items-center"
        >
            <FileCheck2 class="size-10 text-mk-green-600" aria-hidden="true" />
            <div>
                <h2
                    class="font-display text-2xl font-semibold text-mk-graphite-950"
                >
                    Vai abrir ou precisa regularizar a empresa?
                </h2>
                <p class="mt-2 text-mk-graphite-600">
                    A área de contabilidade da MK cuida da abertura,
                    regularização e baixa de empresas, inclusive MEI.
                </p>
            </div>
            <SiteButton :href="accounting()" variant="secondary">
                Ver contabilidade
            </SiteButton>
        </div>
    </section>
</template>
