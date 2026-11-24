<script setup lang="ts">
import { FileImage, FileText, RotateCcw } from '@lucide/vue';
import CopiesStepper from '@totem/components/CopiesStepper.vue';
import SegmentedControl from '@totem/components/SegmentedControl.vue';
import SheetPreview from '@totem/components/SheetPreview.vue';
import { formatBytes, formatMoney, plural } from '@totem/lib/format';
import { parsePageRange } from '@totem/lib/pricing';
import type {
    ColorMode,
    OrderFile,
    PagesPerSheet,
    PrinterStatus,
    PrintOptions,
    Pricing,
} from '@totem/types';
import { computed, ref } from 'vue';

const props = defineProps<{
    file: OrderFile;
    pricing: Pricing;
    printer: PrinterStatus | null;
    imageUrl: string | null;
    serverError: string | null;
}>();

const emit = defineEmits<{ replace: [] }>();

const options = defineModel<PrintOptions>({ required: true });

const pageCount = computed(() => props.file.page_count ?? 1);
const isImage = computed(() => props.file.mime.startsWith('image/'));
const rangeMode = ref<'all' | 'custom'>(
    options.value.page_range ? 'custom' : 'all',
);
const rangeText = ref(options.value.page_range ?? '');

const range = computed(() =>
    parsePageRange(
        rangeMode.value === 'all' ? null : rangeText.value,
        pageCount.value,
    ),
);

const previewPages = computed(() =>
    range.value.ok
        ? range.value.pages
        : Array.from({ length: pageCount.value }, (_, index) => index + 1),
);

function patch(partial: Partial<PrintOptions>): void {
    options.value = { ...options.value, ...partial };
}

const color = computed({
    get: () => options.value.color,
    set: (value: ColorMode) => patch({ color: value }),
});

const duplex = computed({
    get: () => options.value.duplex,
    set: (value: boolean) => patch({ duplex: value }),
});

const perSheet = computed({
    get: () => options.value.pages_per_sheet,
    set: (value: PagesPerSheet) => patch({ pages_per_sheet: value }),
});

const copies = computed({
    get: () => options.value.copies,
    set: (value: number) => patch({ copies: value }),
});

const mode = computed({
    get: () => rangeMode.value,
    set: (value: 'all' | 'custom') => {
        rangeMode.value = value;
        applyRange();
    },
});

function applyRange(): void {
    const text = rangeMode.value === 'all' ? '' : rangeText.value.trim();
    const parsed = parsePageRange(text || null, pageCount.value);

    if (parsed.ok) {
        patch({ page_range: text || null });
    }
}

const supportsColor = computed(() => props.printer?.supports_color ?? true);
const supportsDuplex = computed(() => props.printer?.supports_duplex ?? true);
</script>

<template>
    <section class="animate-rise space-y-6">
        <header>
            <h1 class="font-display text-3xl font-extrabold tracking-tight">
                Como vai sair?
            </h1>
            <p class="mt-1 text-ink-soft">
                O preço muda na hora, conforme você escolhe.
            </p>
        </header>

        <div
            class="flex items-center gap-3 rounded-2xl bg-surface p-3 ring-1 ring-line"
        >
            <span
                class="grid size-11 shrink-0 place-items-center rounded-xl bg-process-soft text-process"
            >
                <FileImage v-if="isImage" class="size-5" />
                <FileText v-else class="size-5" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate font-semibold">{{ file.name }}</p>
                <p class="font-mono text-xs text-ink-soft">
                    {{ plural(pageCount, 'página', 'páginas') }} ·
                    {{ formatBytes(file.size_bytes) }}
                </p>
            </div>
            <button
                type="button"
                class="flex min-h-12 items-center gap-1.5 rounded-xl px-3 text-sm font-semibold text-process"
                @click="emit('replace')"
            >
                <RotateCcw class="size-4" />
                Trocar
            </button>
        </div>

        <SheetPreview
            :options="options"
            :pages="previewPages"
            :image-url="imageUrl"
        />

        <SegmentedControl
            v-model="color"
            label="Cor"
            :options="[
                {
                    value: 'bw',
                    label: 'Preto e branco',
                    hint: `${formatMoney(pricing.bw_per_side_cents)}/face`,
                },
                {
                    value: 'color',
                    label: 'Colorido',
                    hint: `${formatMoney(pricing.color_per_side_cents)}/face`,
                    disabled: !supportsColor,
                },
            ]"
        />

        <SegmentedControl
            v-if="pageCount > 1"
            v-model="duplex"
            label="Lados da folha"
            :options="[
                { value: false, label: 'Só frente' },
                {
                    value: true,
                    label: 'Frente e verso',
                    hint: 'economiza papel',
                    disabled: !supportsDuplex,
                },
            ]"
        />

        <SegmentedControl
            v-if="pageCount > 1"
            v-model="perSheet"
            label="Páginas por face"
            :options="[
                { value: 1, label: '1' },
                { value: 2, label: '2' },
                { value: 4, label: '4' },
            ]"
        />

        <div v-if="pageCount > 1" class="space-y-3">
            <SegmentedControl
                v-model="mode"
                label="Quais páginas"
                :options="[
                    {
                        value: 'all',
                        label: 'Todas',
                        hint: plural(pageCount, 'página', 'páginas'),
                    },
                    { value: 'custom', label: 'Escolher' },
                ]"
            />
            <div v-if="rangeMode === 'custom'">
                <label for="page-range" class="sr-only">Páginas</label>
                <input
                    id="page-range"
                    v-model="rangeText"
                    type="text"
                    inputmode="numeric"
                    autocomplete="off"
                    :placeholder="`Ex.: 1-3, 5 (de 1 a ${pageCount})`"
                    :aria-invalid="!range.ok"
                    aria-describedby="page-range-help"
                    class="min-h-12 w-full rounded-2xl bg-surface px-4 font-mono text-lg ring-1 ring-line focus:ring-2 focus:ring-process focus:outline-none"
                    @input="applyRange"
                />
                <p
                    id="page-range-help"
                    :class="[
                        'mt-1.5 text-sm',
                        range.ok ? 'text-ink-soft' : 'font-semibold text-danger',
                    ]"
                >
                    {{
                        range.ok
                            ? `${plural(range.pages.length, 'página escolhida', 'páginas escolhidas')}.`
                            : range.message
                    }}
                </p>
            </div>
        </div>

        <CopiesStepper v-model="copies" :max="pricing.max_copies" />

        <p
            v-if="serverError"
            role="alert"
            class="rounded-2xl bg-danger-soft px-4 py-3 font-semibold text-danger"
        >
            {{ serverError }}
        </p>
    </section>
</template>
