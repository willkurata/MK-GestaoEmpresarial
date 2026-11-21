<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { purposeLabels, typeLabels } from '@/lib/properties';
import type { PropertyOptions } from '@/lib/properties';

export type PropertyFormValues = {
    title: string;
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
};

const props = defineProps<{
    initial?: Partial<PropertyFormValues>;
    action: {
        url: string;
        method: 'get' | 'post' | 'put' | 'patch' | 'delete';
    };
    options: PropertyOptions;
    submitLabel: string;
    listingFieldsRequired?: boolean;
}>();

const form = useForm({
    title: props.initial?.title ?? '',
    purpose: props.initial?.purpose ?? '',
    type: props.initial?.type ?? '',
    city: props.initial?.city ?? '',
    neighborhood: props.initial?.neighborhood ?? '',
    price: props.initial?.price ?? '',
    description: props.initial?.description ?? '',
    bedrooms: props.initial?.bedrooms ?? null,
    bathrooms: props.initial?.bathrooms ?? null,
    parking_spaces: props.initial?.parking_spaces ?? null,
    area: props.initial?.area ?? '',
    features: (props.initial?.features ?? []).join('\n'),
});

const blankToNull = (value: unknown) => (value === '' ? null : value);

function submit(): void {
    form.transform((data) => ({
        ...Object.fromEntries(
            Object.entries(data).map(([key, value]) => [
                key,
                blankToNull(value),
            ]),
        ),
        features: data.features
            .split('\n')
            .map((feature) => feature.trim())
            .filter((feature) => feature !== ''),
    })).submit(props.action, { preserveScroll: true });
}

const featureError = () =>
    Object.entries(form.errors).find(([key]) =>
        key.startsWith('features'),
    )?.[1];
const inputClass =
    'h-10 rounded-md border bg-transparent px-3 dark:border-slate-700';
const selectClass =
    'h-10 rounded-md border bg-white px-3 dark:border-slate-700 dark:bg-slate-950';
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <section
            class="space-y-4 rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
        >
            <div>
                <h2 class="font-semibold">Dados do imóvel</h2>
                <p class="mt-1 text-sm text-slate-500">
                    <template v-if="listingFieldsRequired"
                        >Este imóvel está no site: os campos marcados com * são
                        obrigatórios.</template
                    >
                    <template v-else
                        >Para salvar o rascunho basta o título. Os campos com *
                        serão exigidos na publicação.</template
                    >
                </p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="grid gap-2 text-sm sm:col-span-2"
                    >Título *
                    <input
                        v-model="form.title"
                        name="title"
                        required
                        maxlength="160"
                        :class="inputClass"
                    />
                    <InputError :message="form.errors.title" />
                </label>
                <label class="grid gap-2 text-sm"
                    >Finalidade *
                    <select
                        v-model="form.purpose"
                        name="purpose"
                        :required="listingFieldsRequired"
                        :class="selectClass"
                    >
                        <option value="">Selecione</option>
                        <option
                            v-for="purpose in options.purposes"
                            :key="purpose"
                            :value="purpose"
                        >
                            {{ purposeLabels[purpose] ?? purpose }}
                        </option>
                    </select>
                    <InputError :message="form.errors.purpose" />
                </label>
                <label class="grid gap-2 text-sm"
                    >Tipo *
                    <select
                        v-model="form.type"
                        name="type"
                        :required="listingFieldsRequired"
                        :class="selectClass"
                    >
                        <option value="">Selecione</option>
                        <option
                            v-for="type in options.types"
                            :key="type"
                            :value="type"
                        >
                            {{ typeLabels[type] ?? type }}
                        </option>
                    </select>
                    <InputError :message="form.errors.type" />
                </label>
                <label class="grid gap-2 text-sm"
                    >Cidade *
                    <input
                        v-model="form.city"
                        name="city"
                        maxlength="120"
                        :required="listingFieldsRequired"
                        :class="inputClass"
                    />
                    <InputError :message="form.errors.city" />
                </label>
                <label class="grid gap-2 text-sm"
                    >Bairro *
                    <input
                        v-model="form.neighborhood"
                        name="neighborhood"
                        maxlength="120"
                        :required="listingFieldsRequired"
                        :class="inputClass"
                    />
                    <InputError :message="form.errors.neighborhood" />
                </label>
                <label class="grid gap-2 text-sm"
                    >Preço (R$) *
                    <input
                        v-model="form.price"
                        name="price"
                        type="number"
                        min="0.01"
                        step="0.01"
                        inputmode="decimal"
                        placeholder="450000.00"
                        :required="listingFieldsRequired"
                        :class="inputClass"
                    />
                    <span class="text-xs text-slate-500"
                        >Para aluguel, informe o valor mensal.</span
                    >
                    <InputError :message="form.errors.price" />
                </label>
                <label class="grid gap-2 text-sm"
                    >Área (m²)
                    <input
                        v-model="form.area"
                        name="area"
                        type="number"
                        min="0.01"
                        step="0.01"
                        inputmode="decimal"
                        :class="inputClass"
                    />
                    <InputError :message="form.errors.area" />
                </label>
                <div class="grid grid-cols-3 gap-3 sm:col-span-2">
                    <label class="grid gap-2 text-sm"
                        >Quartos
                        <input
                            v-model.number="form.bedrooms"
                            name="bedrooms"
                            type="number"
                            min="0"
                            max="999"
                            :class="inputClass"
                        />
                        <InputError :message="form.errors.bedrooms" />
                    </label>
                    <label class="grid gap-2 text-sm"
                        >Banheiros
                        <input
                            v-model.number="form.bathrooms"
                            name="bathrooms"
                            type="number"
                            min="0"
                            max="999"
                            :class="inputClass"
                        />
                        <InputError :message="form.errors.bathrooms" />
                    </label>
                    <label class="grid gap-2 text-sm"
                        >Vagas
                        <input
                            v-model.number="form.parking_spaces"
                            name="parking_spaces"
                            type="number"
                            min="0"
                            max="999"
                            :class="inputClass"
                        />
                        <InputError :message="form.errors.parking_spaces" />
                    </label>
                </div>
                <label class="grid gap-2 text-sm sm:col-span-2"
                    >Descrição *
                    <textarea
                        v-model="form.description"
                        name="description"
                        rows="6"
                        maxlength="10000"
                        :required="listingFieldsRequired"
                        class="rounded-md border bg-transparent px-3 py-2 dark:border-slate-700"
                    />
                    <InputError :message="form.errors.description" />
                </label>
                <label class="grid gap-2 text-sm sm:col-span-2"
                    >Características
                    <textarea
                        v-model="form.features"
                        name="features"
                        rows="4"
                        placeholder="Piscina&#10;Churrasqueira&#10;Próximo à praia"
                        class="rounded-md border bg-transparent px-3 py-2 dark:border-slate-700"
                    />
                    <span class="text-xs text-slate-500"
                        >Uma por linha, até {{ options.max_features }} itens de
                        60 caracteres.</span
                    >
                    <InputError :message="featureError()" />
                </label>
            </div>
        </section>
        <button
            type="submit"
            :disabled="form.processing"
            class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60"
        >
            {{ form.processing ? 'Salvando…' : submitLabel }}
        </button>
    </form>
</template>
