<script setup lang="ts">
import { FileText, LoaderCircle, ShieldCheck, Upload } from '@lucide/vue';
import { formatBytes } from '@totem/lib/format';
import type { Pricing, UploadProgress } from '@totem/types';
import { computed, ref } from 'vue';

const props = defineProps<{
    pricing: Pricing | null;
    uploading: boolean;
    progress: UploadProgress | null;
    fileName: string | null;
    error: string | null;
}>();

const emit = defineEmits<{ pick: [file: File] }>();

const input = ref<HTMLInputElement | null>(null);
const dragging = ref(false);

const accept = computed(() =>
    (props.pricing?.accepted_extensions ?? ['pdf', 'jpg', 'jpeg', 'png'])
        .map((extension) => `.${extension}`)
        .join(','),
);

const kinds = computed(() =>
    (props.pricing?.accepted_extensions ?? ['pdf', 'jpg', 'png'])
        .filter((extension) => extension !== 'jpeg')
        .map((extension) => extension.toUpperCase())
        .join(', '),
);

const percent = computed(() =>
    props.progress && props.progress.total > 0
        ? Math.round((props.progress.loaded / props.progress.total) * 100)
        : 0,
);

function choose(files: FileList | null | undefined): void {
    const file = files?.[0];

    if (file) {
        emit('pick', file);
    }
}

function onDrop(event: DragEvent): void {
    dragging.value = false;
    choose(event.dataTransfer?.files);
}
</script>

<template>
    <section class="animate-rise space-y-5">
        <header>
            <h1 class="font-display text-3xl font-extrabold tracking-tight">
                O que vamos imprimir?
            </h1>
            <p class="text-ink-soft mt-1">
                Escolha um arquivo do celular, da galeria ou da nuvem.
            </p>
        </header>

        <div
            v-if="uploading"
            class="border-process bg-process-soft rounded-3xl border-2 p-6"
            aria-live="polite"
        >
            <div class="flex items-center gap-3">
                <LoaderCircle
                    class="text-process size-6 shrink-0 animate-spin"
                />
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold">{{ fileName }}</p>
                    <p class="text-ink-soft font-mono text-sm">
                        <template v-if="progress && percent < 100">
                            Enviando… {{ percent }}% ·
                            {{ formatBytes(progress.loaded) }} de
                            {{ formatBytes(progress.total) }}
                        </template>
                        <template v-else>Contando as páginas…</template>
                    </p>
                </div>
            </div>
            <div
                class="bg-surface mt-4 h-2 overflow-hidden rounded-full"
                role="progressbar"
                :aria-valuenow="percent"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-label="Envio do arquivo"
            >
                <div
                    class="bg-process h-full rounded-full transition-[width] duration-200"
                    :style="{ width: `${percent}%` }"
                />
            </div>
        </div>

        <label
            v-else
            :class="[
                'flex min-h-64 cursor-pointer flex-col items-center justify-center gap-4 rounded-3xl border-2 border-dashed p-6 text-center transition-colors',
                dragging
                    ? 'border-process bg-process-soft'
                    : 'border-line bg-surface hover:border-process',
            ]"
            @dragover.prevent="dragging = true"
            @dragleave="dragging = false"
            @drop.prevent="onDrop"
        >
            <span
                class="bg-process text-on-process grid size-16 place-items-center rounded-2xl"
            >
                <Upload class="size-8" />
            </span>
            <span>
                <span class="block text-lg font-bold">Escolher arquivo</span>
                <span class="text-ink-soft mt-1 block text-sm">
                    {{ kinds }} · até {{ pricing?.max_file_mb ?? 25 }} MB
                </span>
            </span>
            <input
                ref="input"
                type="file"
                class="sr-only"
                :accept="accept"
                @change="choose(($event.target as HTMLInputElement).files)"
            />
        </label>

        <p
            v-if="error"
            role="alert"
            class="bg-danger-soft text-danger rounded-2xl px-4 py-3 font-semibold"
        >
            {{ error }}
        </p>

        <ul class="text-ink-soft space-y-2 text-sm">
            <li class="flex gap-2">
                <FileText class="text-process size-5 shrink-0" />
                Documento do Word? Salve como PDF antes de enviar.
            </li>
            <li class="flex gap-2">
                <ShieldCheck class="text-process size-5 shrink-0" />
                O arquivo é apagado alguns minutos depois de impresso.
            </li>
        </ul>
    </section>
</template>
