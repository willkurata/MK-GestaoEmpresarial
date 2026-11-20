<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { MapPin, MessageCircle, Search } from '@lucide/vue';
import { reactive } from 'vue';
import { useSite } from '@/composables/useSite';
import { whatsappUrl } from '@/lib/site';
import { index as properties } from '@/routes/site/properties';
import type { PropertyPurpose } from '@/types/site';

/**
 * Hero da home (WIL-29): foto grande do Litoral Norte em tela cheia, com
 * véu escuro para o texto branco ficar legível, e um cartão de busca de
 * imóveis com fundo branco sólido (nunca transparente). A busca usa só os
 * filtros que a vitrine já aceita (finalidade e cidade) e leva para a
 * listagem de imóveis com eles aplicados.
 */
const site = useSite();

const purposes: { value: PropertyPurpose | ''; label: string }[] = [
    { value: 'venda', label: 'Comprar' },
    { value: 'locacao', label: 'Alugar' },
    { value: '', label: 'Todos' },
];

const search = reactive<{ finalidade: PropertyPurpose | ''; cidade: string }>({
    finalidade: 'venda',
    cidade: '',
});

function submitSearch(): void {
    const query = Object.fromEntries(
        Object.entries(search).filter(([, value]) => value !== ''),
    );

    router.get(properties.url({ query }));
}
</script>

<template>
    <section
        aria-labelledby="home-hero-title"
        class="relative isolate flex min-h-[min(calc(100svh-4.5rem),56rem)] items-end overflow-hidden bg-mk-graphite-950"
    >
        <img
            src="/images/imovel-praia.jpg"
            alt=""
            fetchpriority="high"
            decoding="async"
            class="absolute inset-0 -z-20 size-full object-cover object-[30%_center]"
        />
        <div
            aria-hidden="true"
            class="absolute inset-0 -z-10 bg-linear-to-t from-mk-graphite-950/85 via-mk-graphite-950/45 to-mk-graphite-950/20"
        />
        <div
            aria-hidden="true"
            class="absolute inset-0 -z-10 bg-linear-to-r from-mk-green-950/60 via-transparent to-transparent"
        />

        <div
            class="mx-auto w-full max-w-7xl px-4 pt-32 pb-12 sm:px-6 sm:pb-16 lg:px-8 lg:pb-20"
        >
            <p
                class="inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-1.5 text-[0.7rem] font-semibold tracking-[0.2em] text-white uppercase ring-1 ring-white/25 backdrop-blur-sm sm:text-xs"
            >
                <MapPin class="size-3.5" aria-hidden="true" />
                Ubatuba · Caraguatatuba
            </p>
            <h1
                id="home-hero-title"
                class="mt-6 max-w-3xl text-4xl leading-[1.08] font-medium tracking-tight text-balance text-white sm:text-6xl lg:text-7xl"
            >
                O seu lugar no Litoral Norte começa aqui.
            </h1>
            <p
                class="mt-6 max-w-xl text-lg leading-8 text-white/85 sm:text-xl sm:leading-9"
            >
                Imóveis para comprar ou alugar, contabilidade e o MK Gestão App
                para o seu negócio — com a mesma equipe.
            </p>

            <form
                role="search"
                aria-label="Buscar imóveis"
                class="mt-10 rounded-[1.75rem] bg-white p-3 shadow-2xl shadow-mk-graphite-950/30 sm:mt-12 sm:p-4 lg:max-w-4xl"
                @submit.prevent="submitSearch"
            >
                <div
                    class="grid gap-3 md:grid-cols-[auto_minmax(0,1fr)_auto] md:items-stretch"
                >
                    <fieldset
                        class="flex rounded-full bg-mk-graphite-50 p-1 ring-1 ring-mk-graphite-100"
                    >
                        <legend class="sr-only">Finalidade</legend>
                        <label
                            v-for="purpose in purposes"
                            :key="purpose.label"
                            class="relative flex-1 cursor-pointer"
                        >
                            <input
                                v-model="search.finalidade"
                                type="radio"
                                name="finalidade"
                                :value="purpose.value"
                                class="peer sr-only"
                            />
                            <span
                                class="flex h-12 items-center justify-center rounded-full px-5 text-sm font-semibold text-mk-graphite-600 transition-colors peer-checked:bg-mk-green-700 peer-checked:text-white peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-mk-green-700 hover:text-mk-graphite-950 peer-checked:hover:text-white"
                            >
                                {{ purpose.label }}
                            </span>
                        </label>
                    </fieldset>

                    <label
                        class="flex h-14 items-center gap-3 rounded-full px-5 ring-1 ring-mk-graphite-200 transition focus-within:ring-2 focus-within:ring-mk-green-600"
                    >
                        <MapPin
                            class="size-5 shrink-0 text-mk-green-700"
                            aria-hidden="true"
                        />
                        <span class="sr-only">Cidade</span>
                        <select
                            v-model="search.cidade"
                            class="h-full w-full cursor-pointer appearance-none bg-transparent text-base text-mk-graphite-900 focus:outline-none"
                        >
                            <option value="">Todas as cidades</option>
                            <option
                                v-for="city in site.cities"
                                :key="city"
                                :value="city"
                            >
                                {{ city }}
                            </option>
                        </select>
                    </label>

                    <button
                        type="submit"
                        class="inline-flex h-14 items-center justify-center gap-2 rounded-full bg-mk-green-700 px-8 text-base font-semibold text-white transition-colors hover:bg-mk-green-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mk-green-700"
                    >
                        <Search class="size-5" aria-hidden="true" />
                        Buscar imóveis
                    </button>
                </div>
            </form>

            <p class="mt-6 text-sm text-white/80">
                Quer vender ou alugar o seu imóvel?
                <a
                    :href="
                        whatsappUrl(
                            site.contact.whatsapp,
                            'Olá! Vim pelo site da MK Negócios e quero anunciar o meu imóvel.',
                        )
                    "
                    target="_blank"
                    rel="noopener noreferrer"
                    class="ml-1 inline-flex items-center gap-1.5 font-semibold text-white underline decoration-white/40 underline-offset-4 hover:decoration-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                >
                    <MessageCircle class="size-4" aria-hidden="true" />
                    Anuncie com a MK
                </a>
            </p>
        </div>
    </section>
</template>
