<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { dashboard } from '@/routes/mk';
import {
    create as createProperty,
    edit as editProperty,
    index as propertiesIndex,
} from '@/routes/mk/properties';
import {
    formatPrice,
    purposeLabels,
    statusClasses,
    statusLabels,
    typeLabels,
} from '@/lib/properties';
import type { PropertyOptions, PropertyStatus } from '@/lib/properties';

defineOptions({
    layout: {
        title: 'Imóveis',
        description:
            'Estoque de imóveis para venda e aluguel do site MK Negócios.',
    },
});

type PropertyRow = {
    id: number;
    reference_code: string | null;
    title: string;
    slug: string;
    status: PropertyStatus;
    purpose: string | null;
    type: string | null;
    city: string | null;
    neighborhood: string | null;
    price: string | null;
    photos_count: number;
    cover_photo_url: string | null;
    public_url: string | null;
};

const props = defineProps<{
    properties: {
        data: PropertyRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total: number;
    };
    filters: { status: PropertyStatus | null; search: string | null };
    options: PropertyOptions;
}>();

const flash = usePage<{ flash: { success?: string } }>().props.flash;
const status = ref(props.filters.status ?? '');
const search = ref(props.filters.search ?? '');

const pageLabel = (label: string) =>
    label
        .replace('&laquo; Previous', '« Anterior')
        .replace('Next &raquo;', 'Próxima »');

function applyFilters(): void {
    router.get(
        propertiesIndex.url({
            query: {
                status: status.value || undefined,
                search: search.value.trim() || undefined,
            },
        }),
        {},
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head title="Imóveis" />
    <main
        class="min-h-screen bg-slate-50 px-5 py-8 text-slate-900 dark:bg-slate-950 dark:text-slate-100"
    >
        <div class="mx-auto max-w-6xl">
            <Link
                :href="dashboard()"
                class="text-sm text-blue-700 hover:underline dark:text-blue-300"
                >← Voltar ao painel MK</Link
            >
            <header
                class="mt-5 mb-6 flex flex-wrap items-start justify-between gap-4"
            >
                <div>
                    <p
                        class="text-sm font-semibold text-blue-700 dark:text-blue-400"
                    >
                        MK GESTÃO · MASTER
                    </p>
                    <h1 class="mt-1 text-3xl font-semibold">Imóveis</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ properties.total }} imóvel(is) encontrado(s)
                    </p>
                </div>
                <Link
                    :href="createProperty()"
                    class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white"
                    >Cadastrar imóvel</Link
                >
            </header>

            <p
                v-if="flash.success"
                role="status"
                class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200"
            >
                {{ flash.success }}
            </p>

            <form
                class="mb-5 flex flex-wrap items-end gap-3"
                @submit.prevent="applyFilters"
            >
                <label class="grid gap-1 text-sm"
                    >Buscar por título ou código
                    <input
                        v-model="search"
                        type="search"
                        maxlength="160"
                        placeholder="Ex.: Casa na praia ou MK-00012"
                        class="h-10 w-72 max-w-full rounded-md border bg-white px-3 dark:border-slate-700 dark:bg-slate-900"
                    />
                </label>
                <label class="grid gap-1 text-sm"
                    >Status
                    <select
                        v-model="status"
                        class="h-10 rounded-md border bg-white px-3 dark:border-slate-700 dark:bg-slate-900"
                        @change="applyFilters"
                    >
                        <option value="">Todos</option>
                        <option
                            v-for="option in options.statuses"
                            :key="option"
                            :value="option"
                        >
                            {{ statusLabels[option] }}
                        </option>
                    </select>
                </label>
                <button
                    class="h-10 rounded-md border bg-white px-4 text-sm dark:border-slate-700 dark:bg-slate-900"
                >
                    Filtrar
                </button>
            </form>

            <div
                class="overflow-x-auto rounded-xl border bg-white dark:border-slate-800 dark:bg-slate-900"
            >
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead
                        class="bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                    >
                        <tr>
                            <th class="px-4 py-3 font-medium">Imóvel</th>
                            <th class="px-4 py-3 font-medium">Local</th>
                            <th class="px-4 py-3 font-medium">Preço</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">
                                <span class="sr-only">Ações</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="property in properties.data"
                            :key="property.id"
                            class="border-t dark:border-slate-800"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <img
                                        v-if="property.cover_photo_url"
                                        :src="property.cover_photo_url"
                                        alt=""
                                        class="h-12 w-16 shrink-0 rounded-md object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-12 w-16 shrink-0 items-center justify-center rounded-md bg-slate-100 text-xs text-slate-400 dark:bg-slate-800"
                                    >
                                        Sem foto
                                    </div>
                                    <div>
                                        <Link
                                            :href="
                                                editProperty({
                                                    property: property.id,
                                                })
                                            "
                                            class="font-medium text-blue-700 hover:underline dark:text-blue-300"
                                            >{{ property.title }}</Link
                                        >
                                        <p
                                            class="mt-0.5 text-xs text-slate-500"
                                        >
                                            {{ property.reference_code }}
                                            <template v-if="property.purpose">
                                                ·
                                                {{
                                                    purposeLabels[
                                                        property.purpose
                                                    ]
                                                }}</template
                                            >
                                            <template v-if="property.type">
                                                ·
                                                {{
                                                    typeLabels[property.type]
                                                }}</template
                                            >
                                            ·
                                            {{ property.photos_count }} foto(s)
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                {{
                                    [property.neighborhood, property.city]
                                        .filter(Boolean)
                                        .join(', ') || '—'
                                }}
                            </td>
                            <td class="px-4 py-3">
                                {{ formatPrice(property.price) }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="statusClasses[property.status]"
                                    >{{ statusLabels[property.status] }}</span
                                >
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a
                                    v-if="property.public_url"
                                    :href="property.public_url"
                                    target="_blank"
                                    rel="noopener"
                                    class="mr-3 text-sm text-slate-600 hover:underline dark:text-slate-300"
                                    >Ver no site</a
                                >
                                <Link
                                    :href="
                                        editProperty({ property: property.id })
                                    "
                                    class="text-sm text-blue-700 hover:underline dark:text-blue-300"
                                    >Editar</Link
                                >
                            </td>
                        </tr>
                        <tr v-if="properties.data.length === 0">
                            <td
                                colspan="5"
                                class="px-5 py-10 text-center text-slate-500"
                            >
                                {{
                                    filters.status || filters.search
                                        ? 'Nenhum imóvel encontrado com esses filtros.'
                                        : 'Nenhum imóvel cadastrado ainda.'
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav
                v-if="properties.links.length > 3"
                class="mt-5 flex flex-wrap gap-1"
                aria-label="Paginação"
            >
                <template v-for="link in properties.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        class="rounded-md border px-3 py-1.5 text-sm dark:border-slate-700"
                        :class="
                            link.active
                                ? 'bg-blue-700 text-white'
                                : 'bg-white dark:bg-slate-900'
                        "
                        >{{ pageLabel(link.label) }}</Link
                    >
                    <span
                        v-else
                        class="rounded-md px-3 py-1.5 text-sm text-slate-400"
                        >{{ pageLabel(link.label) }}</span
                    >
                </template>
            </nav>
        </div>
    </main>
</template>
