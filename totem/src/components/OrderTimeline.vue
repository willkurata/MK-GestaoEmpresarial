<script setup lang="ts">
import { Check } from '@lucide/vue';
import type { OrderStatus, PrintProgress } from '@totem/types';
import { computed } from 'vue';

const props = defineProps<{
    status: OrderStatus;
    progress: PrintProgress | null;
}>();

const steps = [
    { key: 'paid', label: 'Pagamento confirmado' },
    { key: 'queued', label: 'Na fila da impressora' },
    { key: 'printing', label: 'Imprimindo' },
    { key: 'ready', label: 'Pronto para retirar' },
] as const;

const currentIndex = computed(() => {
    switch (props.status) {
        case 'queued':
            return 1;
        case 'printing':
            return 2;
        case 'ready':
            return 4;
        default:
            return 0;
    }
});

const percent = computed(() =>
    props.progress && props.progress.sheets_total > 0
        ? Math.round(
              (props.progress.sheets_done / props.progress.sheets_total) * 100,
          )
        : 0,
);

const currentSheet = computed(() =>
    props.progress
        ? Math.min(props.progress.sheets_done + 1, props.progress.sheets_total)
        : 0,
);
</script>

<template>
    <ol>
        <li
            v-for="(step, index) in steps"
            :key="step.key"
            class="relative flex gap-3 pb-5 last:pb-0"
            :aria-current="index === currentIndex ? 'step' : undefined"
        >
            <span
                v-if="index < steps.length - 1"
                aria-hidden="true"
                :class="[
                    'absolute top-8 left-[15px] h-[calc(100%-2rem)] w-0.5',
                    index < currentIndex ? 'bg-process' : 'bg-line',
                ]"
            />
            <span
                :class="[
                    'relative z-10 grid size-8 shrink-0 place-items-center rounded-full text-sm font-bold',
                    index < currentIndex
                        ? 'bg-process text-on-process'
                        : index === currentIndex
                          ? 'bg-process-soft text-process ring-process ring-2'
                          : 'bg-surface-2 text-ink-soft',
                ]"
            >
                <Check v-if="index < currentIndex" class="size-4" />
                <span v-else>{{ index + 1 }}</span>
            </span>
            <div class="min-w-0 flex-1 pt-1">
                <p
                    :class="[
                        'font-semibold',
                        index > currentIndex ? 'text-ink-soft' : '',
                    ]"
                >
                    {{ step.label }}
                </p>
                <div
                    v-if="
                        step.key === 'printing' &&
                        index === currentIndex &&
                        progress
                    "
                    class="mt-2"
                >
                    <div
                        class="bg-surface-2 h-2 overflow-hidden rounded-full"
                        role="progressbar"
                        :aria-valuenow="percent"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-label="Progresso da impressão"
                    >
                        <div
                            class="bg-process h-full rounded-full transition-[width] duration-700"
                            :style="{ width: `${percent}%` }"
                        />
                    </div>
                    <p class="text-ink-soft mt-1 font-mono text-xs">
                        Folha {{ currentSheet }} de {{ progress.sheets_total }}
                    </p>
                </div>
            </div>
        </li>
    </ol>
</template>
