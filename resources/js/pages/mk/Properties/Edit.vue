<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PropertyForm from '@/components/mk/PropertyForm.vue';
import { update as updateCover } from '@/routes/mk/properties/cover';
import {
    destroy as destroyPhoto,
    reorder as reorderPhotos,
    store as storePhotos,
} from '@/routes/mk/properties/photos';
import { update as updateStatus } from '@/routes/mk/properties/status';
import {
    destroy as destroyProperty,
    index as propertiesIndex,
    update as updateProperty,
} from '@/routes/mk/properties';
import {
    requirementLabels,
    statusClasses,
    statusLabels,
} from '@/lib/properties';
import type {
    PropertyOptions,
    PropertyPhoto,
    PropertyStatus,
} from '@/lib/properties';

defineOptions({
    layout: {
        title: 'Editar imóvel',
        description: 'Dados, fotos e status do imóvel.',
    },
});

const props = defineProps<{
    property: {
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
        description: string | null;
        bedrooms: number | null;
        bathrooms: number | null;
        parking_spaces: number | null;
        area: string | null;
        features: string[];
        cover_photo_id: number | null;
        published_at: string | null;
        photos: PropertyPhoto[];
        missing_requirements: string[];
        allowed_statuses: PropertyStatus[];
        public_url: string | null;
    };
    options: PropertyOptions;
}>();

const page = usePage<{
    flash: { success?: string };
    errors: Record<string, string>;
}>();
const flash = computed(() => page.props.flash);
const errors = computed(() => page.props.errors);
const isDraft = computed(() => props.property.status === 'rascunho');
const remainingPhotos = computed(
    () => props.options.max_photos - props.property.photos.length,
);

const fileInput = ref<HTMLInputElement | null>(null);
const photoForm = useForm<{ photos: File[] }>({ photos: [] });

function uploadPhotos(event: Event): void {
    const files = Array.from((event.target as HTMLInputElement).files ?? []);

    if (files.length === 0) {
        return;
    }

    photoForm.photos = files;
    photoForm.submit(storePhotos({ property: props.property.id }), {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            photoForm.reset();

            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
}

const visitOptions = { preserveScroll: true } as const;

function changeStatus(status: PropertyStatus): void {
    router.visit(updateStatus({ property: props.property.id }), {
        data: { status },
        ...visitOptions,
    });
}

function setCover(photo: PropertyPhoto): void {
    router.visit(updateCover({ property: props.property.id }), {
        data: { photo_id: photo.id },
        ...visitOptions,
    });
}

function movePhoto(index: number, offset: number): void {
    const ids = props.property.photos.map((photo) => photo.id);
    const target = index + offset;
    [ids[index], ids[target]] = [ids[target], ids[index]];
    router.visit(reorderPhotos({ property: props.property.id }), {
        data: { photo_ids: ids },
        ...visitOptions,
    });
}

function removePhoto(photo: PropertyPhoto): void {
    if (confirm('Remover esta foto? O arquivo será apagado.')) {
        router.visit(
            destroyPhoto({ property: props.property.id, photo: photo.id }),
            visitOptions,
        );
    }
}

function removeProperty(): void {
    if (
        confirm(
            'Excluir este rascunho e todas as fotos? Esta ação não pode ser desfeita.',
        )
    ) {
        router.visit(destroyProperty({ property: props.property.id }));
    }
}

const statusActionLabels: Record<PropertyStatus, string> = {
    rascunho: 'Voltar para rascunho (tirar do site)',
    publicado: 'Publicar no site',
    vendido: 'Marcar como vendido',
    alugado: 'Marcar como alugado',
};
</script>

<template>
    <Head :title="property.title" />
    <main
        class="min-h-screen bg-slate-50 px-5 py-8 text-slate-900 dark:bg-slate-950 dark:text-slate-100"
    >
        <div class="mx-auto max-w-5xl">
            <Link
                :href="propertiesIndex()"
                class="text-sm text-blue-700 hover:underline dark:text-blue-300"
                >← Voltar aos imóveis</Link
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
                    <h1 class="mt-1 text-3xl font-semibold">
                        {{ property.title }}
                    </h1>
                    <p
                        class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-500"
                    >
                        <span
                            class="font-mono font-semibold text-slate-700 dark:text-slate-200"
                            >{{ property.reference_code }}</span
                        >
                        <span
                            class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                            :class="statusClasses[property.status]"
                            >{{ statusLabels[property.status] }}</span
                        >
                    </p>
                </div>
            </header>

            <p
                v-if="flash.success"
                role="status"
                class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200"
            >
                {{ flash.success }}
            </p>

            <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
                <div class="space-y-6">
                    <PropertyForm
                        :key="property.id"
                        :initial="property"
                        :action="updateProperty({ property: property.id })"
                        :options="options"
                        :listing-fields-required="!isDraft"
                        submit-label="Salvar alterações"
                    />

                    <section
                        class="space-y-4 rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div>
                                <h2 class="font-semibold">Fotos</h2>
                                <p class="mt-1 text-sm text-slate-500">
                                    JPG, PNG ou WebP até 8 MB. Até
                                    {{ options.max_photos }} fotos; restam
                                    {{ remainingPhotos }}. A capa aparece
                                    primeiro no site.
                                </p>
                            </div>
                            <label
                                class="cursor-pointer rounded-lg border px-4 py-2 text-sm font-medium dark:border-slate-700"
                                :class="{
                                    'pointer-events-none opacity-50':
                                        remainingPhotos <= 0 ||
                                        photoForm.processing,
                                }"
                            >
                                {{
                                    photoForm.processing
                                        ? 'Enviando…'
                                        : 'Adicionar fotos'
                                }}
                                <input
                                    ref="fileInput"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    multiple
                                    class="sr-only"
                                    :disabled="
                                        remainingPhotos <= 0 ||
                                        photoForm.processing
                                    "
                                    @change="uploadPhotos"
                                />
                            </label>
                        </div>
                        <progress
                            v-if="photoForm.progress"
                            :value="photoForm.progress.percentage"
                            max="100"
                            class="w-full"
                        />
                        <InputError
                            :message="
                                errors.photos ??
                                Object.entries(photoForm.errors).find(([key]) =>
                                    key.startsWith('photos'),
                                )?.[1]
                            "
                        />
                        <InputError
                            :message="errors.photo_ids ?? errors.photo_id"
                        />

                        <ul
                            v-if="property.photos.length"
                            class="grid gap-4 sm:grid-cols-2 md:grid-cols-3"
                        >
                            <li
                                v-for="(photo, index) in property.photos"
                                :key="photo.id"
                                class="overflow-hidden rounded-lg border dark:border-slate-700"
                            >
                                <div class="relative">
                                    <img
                                        :src="photo.url"
                                        :alt="`Foto ${index + 1}`"
                                        class="aspect-[4/3] w-full object-cover"
                                    />
                                    <span
                                        v-if="
                                            photo.id === property.cover_photo_id
                                        "
                                        class="absolute top-2 left-2 rounded-full bg-blue-700 px-2 py-0.5 text-xs font-semibold text-white"
                                        >Capa</span
                                    >
                                </div>
                                <div
                                    class="flex flex-wrap items-center gap-1 p-2 text-xs"
                                >
                                    <button
                                        type="button"
                                        :disabled="index === 0"
                                        class="rounded border px-2 py-1 disabled:opacity-40 dark:border-slate-700"
                                        aria-label="Mover para a esquerda"
                                        @click="movePhoto(index, -1)"
                                    >
                                        ←
                                    </button>
                                    <button
                                        type="button"
                                        :disabled="
                                            index === property.photos.length - 1
                                        "
                                        class="rounded border px-2 py-1 disabled:opacity-40 dark:border-slate-700"
                                        aria-label="Mover para a direita"
                                        @click="movePhoto(index, 1)"
                                    >
                                        →
                                    </button>
                                    <button
                                        v-if="
                                            photo.id !== property.cover_photo_id
                                        "
                                        type="button"
                                        class="rounded border px-2 py-1 dark:border-slate-700"
                                        @click="setCover(photo)"
                                    >
                                        Usar como capa
                                    </button>
                                    <button
                                        type="button"
                                        class="ml-auto rounded border border-red-300 px-2 py-1 text-red-700 dark:border-red-900 dark:text-red-300"
                                        @click="removePhoto(photo)"
                                    >
                                        Remover
                                    </button>
                                </div>
                            </li>
                        </ul>
                        <p
                            v-else
                            class="rounded-lg border border-dashed px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700"
                        >
                            Nenhuma foto ainda. É preciso ao menos uma para
                            publicar.
                        </p>
                    </section>
                </div>

                <aside class="space-y-6">
                    <section
                        class="space-y-3 rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <h2 class="font-semibold">Status</h2>
                        <div
                            v-if="property.missing_requirements.length"
                            class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100"
                        >
                            Para publicar falta:
                            {{
                                property.missing_requirements
                                    .map(
                                        (field) =>
                                            requirementLabels[field] ?? field,
                                    )
                                    .join(', ')
                            }}.
                        </div>
                        <InputError :message="errors.status" />
                        <div class="grid gap-2">
                            <button
                                v-for="status in property.allowed_statuses.filter(
                                    (option) => option !== property.status,
                                )"
                                :key="status"
                                type="button"
                                :disabled="
                                    status !== 'rascunho' &&
                                    property.missing_requirements.length > 0
                                "
                                class="rounded-lg border px-3 py-2 text-left text-sm disabled:opacity-50 dark:border-slate-700"
                                :class="
                                    status === 'publicado'
                                        ? 'border-blue-700 bg-blue-700 font-semibold text-white'
                                        : ''
                                "
                                @click="changeStatus(status)"
                            >
                                {{ statusActionLabels[status] }}
                            </button>
                        </div>
                        <p class="text-xs text-slate-500">
                            Vendido só vale para venda; alugado só para aluguel.
                        </p>
                    </section>

                    <section
                        class="space-y-2 rounded-xl border bg-white p-5 text-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <h2 class="font-semibold">Endereço no site</h2>
                        <p class="text-slate-500">
                            Código de referência:
                            <span
                                class="font-mono font-semibold text-slate-800 dark:text-slate-100"
                                >{{ property.reference_code }}</span
                            >
                        </p>
                        <p class="break-all text-slate-500">
                            Caminho:
                            <span class="font-mono"
                                >/imoveis/{{ property.slug }}</span
                            >
                        </p>
                        <a
                            v-if="property.public_url"
                            :href="property.public_url"
                            target="_blank"
                            rel="noopener"
                            class="inline-block text-blue-700 hover:underline dark:text-blue-300"
                            >Abrir página pública ↗</a
                        >
                        <p v-else-if="isDraft" class="text-slate-500">
                            Rascunhos não aparecem no site. O endereço muda com
                            o título só enquanto o imóvel for rascunho.
                        </p>
                        <p v-else class="text-slate-500">
                            O link direto aparece aqui quando o site público
                            estiver instalado neste ambiente.
                        </p>
                    </section>

                    <section
                        class="space-y-2 rounded-xl border bg-white p-5 text-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <h2 class="font-semibold">Excluir</h2>
                        <template v-if="isDraft">
                            <p class="text-slate-500">
                                Apaga o rascunho e todas as fotos.
                            </p>
                            <button
                                type="button"
                                class="rounded-lg border border-red-300 px-3 py-2 text-red-700 dark:border-red-900 dark:text-red-300"
                                @click="removeProperty"
                            >
                                Excluir rascunho
                            </button>
                        </template>
                        <p v-else class="text-slate-500">
                            Só rascunhos podem ser excluídos. Para tirar o
                            imóvel do ar, volte-o para rascunho.
                        </p>
                        <InputError :message="errors.property" />
                    </section>
                </aside>
            </div>
        </div>
    </main>
</template>
