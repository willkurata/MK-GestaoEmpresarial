<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { SearchX } from '@lucide/vue';
import { reactive } from 'vue';
import PageHero from '@/components/site/PageHero.vue';
import PropertyCard from '@/components/site/PropertyCard.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import SiteButton from '@/components/site/SiteButton.vue';
import { useSite } from '@/composables/useSite';
import { purposeLabels, whatsappUrl } from '@/lib/site';
import { index } from '@/routes/site/properties';
import type {
    PaginationLink,
    Paginated,
    PropertyFilters,
    PropertySummary,
} from '@/types/site';

const props = defineProps<{
    properties: Paginated<PropertySummary>;
    filters: PropertyFilters;
    filter_options: { cities: string[]; types: string[] };
}>();

const site = useSite();
const form = reactive({
    finalidade: props.filters.finalidade ?? '',
    cidade: props.filters.cidade ?? '',
    tipo: props.filters.tipo ?? '',
});

const hasFilters = Object.values(props.filters).some(Boolean);

function applyFilters(): void {
    const query = Object.fromEntries(
        Object.entries(form).filter(([, value]) => value !== ''),
    );

    router.get(index.url({ query }), {}, { preserveScroll: true });
}

function clearFilters(): void {
    router.get(index.url(), {}, { preserveScroll: true });
}

function paginationLabel(link: PaginationLink): string {
    if (link.label.includes('Previous') || link.label.includes('&laquo;')) {
        return 'Anterior';
    }

    if (link.label.includes('Next') || link.label.includes('&raquo;')) {
        return 'Próxima';
    }

    return link.label;
}

const selectClass =
    'h-11 w-full rounded-md border border-mk-graphite-200 bg-white px-3 text-sm text-mk-graphite-900 focus:border-mk-green-600 focus:ring-2 focus:ring-mk-green-600/20 focus:outline-none disabled:bg-mk-graphite-50 disabled:text-mk-graphite-400';
const searchHelpMessage =
    'Olá! Estou procurando um imóvel e gostaria de ajuda.';
</script>

<template>
    <SeoHead
        title="Imóveis à venda e para alugar"
        description="Casas, apartamentos e terrenos à venda e para locação em Ubatuba e Caraguatatuba, com atendimento da MK Negócios."
    />

    <PageHero
        eyebrow="Imóveis"
        title="Imóveis em Ubatuba e Caraguatatuba"
        description="Encontre imóveis para comprar ou alugar no Litoral Norte. Cada anúncio tem um código de referência para agilizar o atendimento."
    />

    <section class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <form
            class="grid gap-3 rounded-lg border border-mk-graphite-100 bg-white p-4 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_auto]"
            role="search"
            aria-label="Filtrar imóveis"
            @submit.prevent="applyFilters"
        >
            <label
                class="grid gap-1.5 text-sm font-medium text-mk-graphite-700"
            >
                Finalidade
                <select v-model="form.finalidade" :class="selectClass">
                    <option value="">Todas</option>
                    <option
                        v-for="(label, value) in purposeLabels"
                        :key="value"
                        :value="value"
                    >
                        {{ label }}
                    </option>
                </select>
            </label>
            <label
                class="grid gap-1.5 text-sm font-medium text-mk-graphite-700"
            >
                Cidade
                <select v-model="form.cidade" :class="selectClass">
                    <option value="">Todas</option>
                    <option
                        v-for="city in filter_options.cities"
                        :key="city"
                        :value="city"
                    >
                        {{ city }}
                    </option>
                </select>
            </label>
            <label
                class="grid gap-1.5 text-sm font-medium text-mk-graphite-700"
            >
                Tipo
                <select
                    v-model="form.tipo"
                    :class="selectClass"
                    :disabled="filter_options.types.length === 0"
                >
                    <option value="">Todos</option>
                    <option
                        v-for="type in filter_options.types"
                        :key="type"
                        :value="type"
                    >
                        {{ type }}
                    </option>
                </select>
            </label>
            <div class="flex items-end gap-2">
                <button
                    type="submit"
                    class="inline-flex h-11 flex-1 items-center justify-center rounded-md bg-mk-green-700 px-5 text-sm font-semibold text-white hover:bg-mk-green-800 lg:flex-none"
                >
                    Buscar
                </button>
                <button
                    v-if="hasFilters"
                    type="button"
                    class="inline-flex h-11 items-center justify-center rounded-md px-3 text-sm font-medium text-mk-graphite-600 hover:text-mk-graphite-900"
                    @click="clearFilters"
                >
                    Limpar
                </button>
            </div>
        </form>

        <p
            v-if="properties.total > 0"
            class="mt-8 text-sm text-mk-graphite-500"
            aria-live="polite"
        >
            {{ properties.total }}
            {{
                properties.total === 1
                    ? 'imóvel encontrado'
                    : 'imóveis encontrados'
            }}
        </p>

        <div
            v-if="properties.data.length > 0"
            class="mt-4 grid gap-6 sm:grid-cols-2 lg:grid-cols-3"
        >
            <PropertyCard
                v-for="property in properties.data"
                :key="property.reference"
                :property="property"
            />
        </div>

        <div
            v-else
            class="mt-10 flex flex-col items-center rounded-lg border border-dashed border-mk-graphite-200 bg-white px-6 py-16 text-center"
        >
            <SearchX class="size-10 text-mk-graphite-300" aria-hidden="true" />
            <h2
                class="mt-4 font-display text-2xl font-semibold text-mk-graphite-950"
            >
                {{
                    hasFilters
                        ? 'Nenhum imóvel com esses filtros'
                        : 'Novos imóveis em breve'
                }}
            </h2>
            <p class="mt-2 max-w-md text-mk-graphite-600">
                {{
                    hasFilters
                        ? 'Tente ampliar a busca ou fale com a gente: podemos procurar o imóvel certo para você.'
                        : 'Estamos preparando os anúncios. Conte o que você procura e avisamos quando houver uma opção.'
                }}
            </p>
            <div class="mt-6">
                <SiteButton
                    :href="
                        whatsappUrl(site.contact.whatsapp, searchHelpMessage)
                    "
                    variant="whatsapp"
                    external
                >
                    Contar o que procuro
                </SiteButton>
            </div>
        </div>

        <nav
            v-if="properties.last_page > 1"
            aria-label="Paginação"
            class="mt-10 flex flex-wrap justify-center gap-1"
        >
            <template v-for="link in properties.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-md px-3 text-sm"
                    :class="
                        link.active
                            ? 'bg-mk-green-700 font-semibold text-white'
                            : 'text-mk-graphite-700 hover:bg-mk-sand'
                    "
                    :aria-current="link.active ? 'page' : undefined"
                >
                    {{ paginationLabel(link) }}
                </Link>
                <span
                    v-else
                    class="inline-flex min-h-10 min-w-10 items-center justify-center px-3 text-sm text-mk-graphite-300"
                >
                    {{ paginationLabel(link) }}
                </span>
            </template>
        </nav>
    </section>
</template>
