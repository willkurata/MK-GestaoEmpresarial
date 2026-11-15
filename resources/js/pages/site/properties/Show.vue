<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Bath,
    BedDouble,
    Car,
    Check,
    Home,
    Mail,
    MapPin,
    Ruler,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import SeoHead from '@/components/site/SeoHead.vue';
import SiteButton from '@/components/site/SiteButton.vue';
import { useSite, useWhatsappContext } from '@/composables/useSite';
import {
    formatPrice,
    propertyWhatsappMessage,
    purposeLabels,
    statusLabels,
    whatsappUrl,
} from '@/lib/site';
import { index } from '@/routes/site/properties';
import type { PropertyDetail } from '@/types/site';

const props = defineProps<{ property: PropertyDetail }>();

const site = useSite();
const activePhotoIndex = ref(0);
const activePhoto = computed(
    () => props.property.photos[activePhotoIndex.value] ?? props.property.cover,
);
const whatsappMessage = computed(() => propertyWhatsappMessage(props.property));
useWhatsappContext(() => whatsappMessage.value);
const emailHref = computed(
    () =>
        `mailto:${site.value.contact.email}?subject=${encodeURIComponent(`Interesse no imóvel ${props.property.reference}`)}`,
);
const isAvailable = computed(() => props.property.status === 'disponivel');
const seoDescription = computed(() =>
    `${props.property.type} para ${purposeLabels[props.property.purpose].toLowerCase()} em ${props.property.city}. ${props.property.description}`.slice(
        0,
        160,
    ),
);

type Fact = { icon: typeof Bath; label: string };

const facts = computed<Fact[]>(() => {
    const { bedrooms, bathrooms, parking_spaces, area_m2 } = props.property;
    const list: Fact[] = [];
    const plural = (count: number, one: string, many: string): string =>
        `${count} ${count === 1 ? one : many}`;

    if (bedrooms) {
        list.push({
            icon: BedDouble,
            label: plural(bedrooms, 'quarto', 'quartos'),
        });
    }

    if (bathrooms) {
        list.push({
            icon: Bath,
            label: plural(bathrooms, 'banheiro', 'banheiros'),
        });
    }

    if (parking_spaces) {
        list.push({
            icon: Car,
            label: plural(parking_spaces, 'vaga', 'vagas'),
        });
    }

    if (area_m2) {
        list.push({
            icon: Ruler,
            label: `${Number(area_m2).toLocaleString('pt-BR')} m²`,
        });
    }

    return list;
});
</script>

<template>
    <SeoHead
        :title="`${property.title} (Ref. ${property.reference})`"
        :description="seoDescription"
        :image="property.cover?.url"
    />

    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
        <Link
            :href="index()"
            class="inline-flex items-center gap-2 text-sm font-medium text-mk-graphite-600 hover:text-mk-green-700"
        >
            <ArrowLeft class="size-4" aria-hidden="true" />
            Voltar para imóveis
        </Link>

        <div class="mt-6 grid gap-10 lg:grid-cols-[1fr_360px]">
            <div>
                <div
                    class="relative aspect-[16/10] overflow-hidden rounded-lg bg-mk-sand"
                >
                    <img
                        v-if="activePhoto"
                        :src="activePhoto.url"
                        :alt="activePhoto.alt"
                        class="size-full object-cover"
                    />
                    <div
                        v-else
                        class="flex size-full items-center justify-center text-mk-graphite-300"
                    >
                        <Home class="size-14" aria-hidden="true" />
                    </div>
                    <span
                        v-if="!isAvailable"
                        class="absolute top-4 left-4 rounded bg-mk-graphite-900 px-3 py-1.5 text-sm font-semibold text-white"
                    >
                        {{ statusLabels[property.status] }}
                    </span>
                </div>

                <ul
                    v-if="property.photos.length > 1"
                    class="mt-3 flex gap-2 overflow-x-auto pb-2"
                    aria-label="Fotos do imóvel"
                >
                    <li
                        v-for="(photo, photoIndex) in property.photos"
                        :key="photo.url"
                        class="shrink-0"
                    >
                        <button
                            type="button"
                            class="block h-16 w-24 overflow-hidden rounded-md ring-offset-2 focus-visible:ring-2 focus-visible:ring-mk-green-700 focus-visible:outline-none"
                            :class="
                                photoIndex === activePhotoIndex
                                    ? 'ring-2 ring-mk-green-600'
                                    : 'opacity-75 hover:opacity-100'
                            "
                            :aria-label="`Ver foto ${photoIndex + 1} de ${property.photos.length}`"
                            :aria-pressed="photoIndex === activePhotoIndex"
                            @click="activePhotoIndex = photoIndex"
                        >
                            <img
                                :src="photo.url"
                                alt=""
                                loading="lazy"
                                class="size-full object-cover"
                            />
                        </button>
                    </li>
                </ul>

                <div class="mt-8">
                    <p
                        class="text-xs font-semibold tracking-[0.2em] text-mk-green-600 uppercase"
                    >
                        {{ property.type }} ·
                        {{ purposeLabels[property.purpose] }}
                    </p>
                    <h1
                        class="mt-2 font-display text-3xl leading-tight font-semibold text-mk-graphite-950 sm:text-4xl"
                    >
                        {{ property.title }}
                    </h1>
                    <p
                        class="mt-3 flex items-center gap-2 text-mk-graphite-600"
                    >
                        <MapPin class="size-4 shrink-0" aria-hidden="true" />
                        {{ property.location_label }}
                    </p>
                </div>

                <ul
                    v-if="facts.length > 0"
                    class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4"
                >
                    <li
                        v-for="fact in facts"
                        :key="fact.label"
                        class="flex items-center gap-2 rounded-md border border-mk-graphite-100 bg-white px-3 py-3 text-sm text-mk-graphite-700"
                    >
                        <component
                            :is="fact.icon"
                            class="size-5 text-mk-green-600"
                            aria-hidden="true"
                        />
                        {{ fact.label }}
                    </li>
                </ul>

                <section class="mt-10">
                    <h2
                        class="font-display text-2xl font-semibold text-mk-graphite-950"
                    >
                        Sobre o imóvel
                    </h2>
                    <p
                        class="mt-4 leading-relaxed whitespace-pre-line text-mk-graphite-700"
                    >
                        {{ property.description }}
                    </p>
                </section>

                <section v-if="property.features.length > 0" class="mt-10">
                    <h2
                        class="font-display text-2xl font-semibold text-mk-graphite-950"
                    >
                        Características
                    </h2>
                    <ul class="mt-4 grid gap-2 sm:grid-cols-2">
                        <li
                            v-for="feature in property.features"
                            :key="feature"
                            class="flex items-start gap-2 text-mk-graphite-700"
                        >
                            <Check
                                class="mt-0.5 size-4 shrink-0 text-mk-green-600"
                                aria-hidden="true"
                            />
                            {{ feature }}
                        </li>
                    </ul>
                </section>

                <section class="mt-10">
                    <h2
                        class="font-display text-2xl font-semibold text-mk-graphite-950"
                    >
                        Localização
                    </h2>
                    <p class="mt-3 text-mk-graphite-700">
                        {{ property.location_label }}
                    </p>
                    <p class="mt-1 text-sm text-mk-graphite-500">
                        Por segurança, o endereço exato é informado no
                        atendimento.
                    </p>
                </section>
            </div>

            <aside class="lg:sticky lg:top-24 lg:self-start">
                <div
                    class="rounded-lg border border-mk-graphite-100 bg-white p-6"
                >
                    <p class="text-sm text-mk-graphite-500">
                        {{
                            property.purpose === 'locacao'
                                ? 'Aluguel mensal'
                                : 'Valor de venda'
                        }}
                    </p>
                    <p class="mt-1 text-3xl font-semibold text-mk-green-700">
                        {{ formatPrice(property.price) }}
                    </p>
                    <dl
                        v-if="property.condo_fee || property.iptu"
                        class="mt-4 space-y-1 text-sm text-mk-graphite-600"
                    >
                        <div
                            v-if="property.condo_fee"
                            class="flex justify-between"
                        >
                            <dt>Condomínio</dt>
                            <dd>{{ formatPrice(property.condo_fee) }}</dd>
                        </div>
                        <div v-if="property.iptu" class="flex justify-between">
                            <dt>IPTU</dt>
                            <dd>{{ formatPrice(property.iptu) }}</dd>
                        </div>
                    </dl>
                    <p
                        class="mt-4 rounded bg-mk-sand px-3 py-2 text-sm text-mk-graphite-700"
                    >
                        Código de referência:
                        <strong class="font-semibold">{{
                            property.reference
                        }}</strong>
                    </p>

                    <div class="mt-6 grid gap-3">
                        <SiteButton
                            :href="
                                whatsappUrl(
                                    site.contact.whatsapp,
                                    whatsappMessage,
                                )
                            "
                            variant="whatsapp"
                            external
                        >
                            {{
                                isAvailable
                                    ? 'Tenho interesse'
                                    : 'Quero um imóvel parecido'
                            }}
                        </SiteButton>
                        <SiteButton :href="emailHref" variant="secondary">
                            <Mail class="size-4" aria-hidden="true" />
                            Enviar e-mail
                        </SiteButton>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>
