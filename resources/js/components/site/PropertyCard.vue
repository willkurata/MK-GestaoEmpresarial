<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Bath, BedDouble, Car, Home, Ruler } from '@lucide/vue';
import { formatPrice, purposeLabels, statusLabels } from '@/lib/site';
import { show } from '@/routes/site/properties';
import type { PropertySummary } from '@/types/site';

const { property } = defineProps<{ property: PropertySummary }>();
</script>

<template>
    <article
        class="group relative flex flex-col overflow-hidden rounded-lg border border-mk-graphite-100 bg-white transition-shadow hover:shadow-lg hover:shadow-mk-graphite-950/5"
    >
        <div class="relative aspect-[4/3] overflow-hidden bg-mk-sand">
            <img
                v-if="property.cover"
                :src="property.cover.url"
                :alt="property.cover.alt"
                loading="lazy"
                class="size-full object-cover transition-transform duration-500 group-hover:scale-105"
            />
            <div
                v-else
                class="flex size-full items-center justify-center text-mk-graphite-300"
            >
                <Home class="size-10" aria-hidden="true" />
            </div>
            <span
                class="absolute top-3 left-3 rounded bg-white/95 px-2 py-1 text-xs font-semibold text-mk-graphite-900"
            >
                {{ purposeLabels[property.purpose] }}
            </span>
            <span
                v-if="property.status !== 'disponivel'"
                class="absolute top-3 right-3 rounded bg-mk-graphite-900 px-2 py-1 text-xs font-semibold text-white"
            >
                {{ statusLabels[property.status] }}
            </span>
        </div>

        <div class="flex flex-1 flex-col p-5">
            <p
                class="text-xs font-medium tracking-wide text-mk-graphite-500 uppercase"
            >
                {{ property.type }} ·
                {{ property.neighborhood ? `${property.neighborhood}, ` : ''
                }}{{ property.city }}
            </p>
            <h3
                class="mt-2 font-display text-xl leading-snug font-semibold text-mk-graphite-950"
            >
                <Link
                    :href="show(property.slug)"
                    class="after:absolute after:inset-0 after:rounded-lg focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-mk-green-700"
                >
                    {{ property.title }}
                </Link>
            </h3>

            <ul
                class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-sm text-mk-graphite-600"
            >
                <li v-if="property.bedrooms" class="flex items-center gap-1.5">
                    <BedDouble class="size-4" aria-hidden="true" />
                    {{ property.bedrooms }}
                    {{ property.bedrooms === 1 ? 'quarto' : 'quartos' }}
                </li>
                <li v-if="property.bathrooms" class="flex items-center gap-1.5">
                    <Bath class="size-4" aria-hidden="true" />
                    {{ property.bathrooms }}
                    {{ property.bathrooms === 1 ? 'banheiro' : 'banheiros' }}
                </li>
                <li
                    v-if="property.parking_spaces"
                    class="flex items-center gap-1.5"
                >
                    <Car class="size-4" aria-hidden="true" />
                    {{ property.parking_spaces }}
                    {{ property.parking_spaces === 1 ? 'vaga' : 'vagas' }}
                </li>
                <li v-if="property.area_m2" class="flex items-center gap-1.5">
                    <Ruler class="size-4" aria-hidden="true" />
                    {{ Number(property.area_m2).toLocaleString('pt-BR') }} m²
                </li>
            </ul>

            <div class="mt-auto flex items-end justify-between gap-3 pt-5">
                <p class="text-lg font-semibold text-mk-green-700">
                    {{ formatPrice(property.price) }}
                    <span
                        v-if="property.purpose === 'locacao' && property.price"
                        class="text-sm font-normal text-mk-graphite-500"
                        >/mês</span
                    >
                </p>
                <p class="text-xs text-mk-graphite-400">
                    Ref. {{ property.reference }}
                </p>
            </div>
        </div>
    </article>
</template>
