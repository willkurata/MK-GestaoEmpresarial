<script setup lang="ts">
import {
    ENDING_THRESHOLD_SECONDS,
    remainingSeconds,
} from '@cc/composables/useServerClock';
import { billTotals, elapsedSeconds } from '@cc/lib/billing';
import { formatClock, formatMoney } from '@cc/lib/format';
import type { Session } from '@cc/types';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, ref } from 'vue';

/**
 * Barrinha de tempo e valor (WIL-51): mostra ao cliente, em uma linha, o
 * tempo usado e a conta acumulada. Só desenha — quem a usa faz o polling
 * com o servidor e repassa o relógio alinhado (`now`).
 */
const props = defineProps<{
    session: Session;
    now: number;
}>();

const COLLAPSED_KEY = 'cybercoffe.ticker.collapsed';

function readCollapsed(): boolean {
    try {
        return window.localStorage.getItem(COLLAPSED_KEY) === '1';
    } catch {
        return false;
    }
}

const isCollapsed = ref(readCollapsed());

function toggleCollapsed(): void {
    isCollapsed.value = !isCollapsed.value;

    try {
        window.localStorage.setItem(
            COLLAPSED_KEY,
            isCollapsed.value ? '1' : '0',
        );
    } catch {
        // Preferência só local; sem armazenamento a barra segue funcionando.
    }
}

const isOpen = computed(() => props.session.billing === 'open');
const isPaused = computed(() => props.session.status === 'paused');
const remaining = computed(() => remainingSeconds(props.session, props.now));

/** Tempo livre: relógio corrido. Pacote: comprado menos restante (sem pausas). */
const usedSeconds = computed(() =>
    isOpen.value
        ? elapsedSeconds(props.session, props.now)
        : Math.max(0, props.session.total_seconds - remaining.value),
);
const total = computed(() => billTotals(props.session, props.now).total);
const isEnding = computed(
    () =>
        !isOpen.value &&
        !isPaused.value &&
        remaining.value <= ENDING_THRESHOLD_SECONDS,
);

const dotClass = computed(() =>
    isPaused.value
        ? 'bg-violet'
        : isEnding.value
          ? 'bg-amber animate-blink'
          : 'bg-neon shadow-[0_0_6px_var(--color-neon)]',
);

const summary = computed(() => {
    const parts = [
        `Tempo usado ${formatClock(usedSeconds.value)}`,
        `conta ${formatMoney(total.value)}`,
    ];

    if (!isOpen.value) {
        parts.push(`restam ${formatClock(remaining.value)}`);
    }

    return parts.join(', ');
});
</script>

<template>
    <div
        role="group"
        :aria-label="summary"
        class="border-line-strong bg-void/90 text-ink tabular inline-flex h-7 items-center gap-2.5 border pr-1 pl-2.5 text-xs leading-none shadow-[0_0_16px_-6px_var(--color-neon)] backdrop-blur-md select-none"
    >
        <span class="size-1.5 shrink-0" :class="dotClass" aria-hidden="true" />

        <span class="text-neon font-semibold" aria-hidden="true">
            {{ formatClock(usedSeconds) }}
        </span>

        <template v-if="!isCollapsed">
            <span class="bg-line-strong h-3 w-px" aria-hidden="true" />
            <span class="text-sky font-semibold" aria-hidden="true">
                {{ formatMoney(total) }}
            </span>

            <template v-if="!isOpen">
                <span class="bg-line-strong h-3 w-px" aria-hidden="true" />
                <span
                    :class="isEnding ? 'text-amber' : 'text-dim'"
                    aria-hidden="true"
                >
                    {{ isPaused ? 'pausado' : '-' + formatClock(remaining) }}
                </span>
            </template>
        </template>

        <button
            type="button"
            class="text-dim hover:text-ink grid size-5 cursor-pointer place-items-center"
            :aria-label="isCollapsed ? 'Mostrar valor' : 'Mostrar só o tempo'"
            :aria-pressed="isCollapsed"
            @click="toggleCollapsed"
        >
            <ChevronRight v-if="isCollapsed" class="size-3.5" />
            <ChevronLeft v-else class="size-3.5" />
        </button>
    </div>
</template>
