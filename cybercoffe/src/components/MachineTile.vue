<script setup lang="ts">
import StatusBadge from '@cc/components/StatusBadge.vue';
import { formatClock, TIER_LABEL } from '@cc/lib/format';
import { machinePath } from '@cc/router';
import {
    displayStatus,
    remainingSeconds,
} from '@cc/composables/useServerClock';
import type { Station } from '@cc/types';
import { ExternalLink, Pause, Play, Plus, Power, Wrench } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    station: Station;
    now: number;
    busy: boolean;
}>();

defineEmits<{
    extend: [station: Station];
    togglePause: [station: Station];
    end: [station: Station];
    toggleMaintenance: [station: Station];
}>();

const status = computed(() =>
    displayStatus(props.station.status, props.station.session, props.now),
);

const remaining = computed(() =>
    props.station.session
        ? remainingSeconds(props.station.session, props.now)
        : 0,
);

const progress = computed(() => {
    const session = props.station.session;

    return session && session.total_seconds > 0
        ? Math.min(100, (remaining.value / session.total_seconds) * 100)
        : 0;
});

const accent = computed(
    () =>
        ({
            free: 'bg-neon',
            in_use: 'bg-sky',
            ending: 'bg-amber',
            paused: 'bg-violet',
            reserved: 'bg-violet',
            maintenance: 'bg-idle',
            offline: 'bg-danger',
        })[status.value],
);
</script>

<template>
    <article
        class="panel pixel-corners relative flex flex-col gap-4 overflow-hidden p-4"
    >
        <span
            aria-hidden="true"
            class="absolute inset-y-0 left-0 w-1"
            :class="accent"
        />

        <header class="flex items-start justify-between gap-2 pl-1">
            <div>
                <h3 class="text-lg font-bold tracking-wide">
                    {{ station.name }}
                </h3>
                <p class="text-dim text-xs">
                    {{ TIER_LABEL[station.tier] }} · {{ station.specs }}
                </p>
            </div>
            <StatusBadge :status="status" />
        </header>

        <div class="pl-1">
            <template v-if="station.session">
                <div class="flex items-baseline justify-between gap-2">
                    <span class="text-dim truncate text-sm">
                        {{ station.session.customer_name ?? 'Cliente avulso' }}
                    </span>
                    <span
                        class="tabular text-xl font-semibold"
                        :class="status === 'ending' ? 'text-amber' : 'text-ink'"
                    >
                        {{ formatClock(remaining) }}
                    </span>
                </div>
                <div class="bg-line mt-2 h-1.5">
                    <div
                        class="h-full transition-[width] duration-1000 ease-linear"
                        :class="accent"
                        :style="{ width: `${progress}%` }"
                    />
                </div>
            </template>
            <p v-else class="text-dim text-sm">
                {{
                    station.status === 'maintenance'
                        ? 'Fora de uso até liberar.'
                        : 'Pronta para check-in.'
                }}
            </p>
        </div>

        <footer class="mt-auto flex flex-wrap gap-2 pl-1">
            <template v-if="station.session">
                <button
                    type="button"
                    class="btn btn-ghost min-h-9 px-3 text-xs"
                    :disabled="busy"
                    @click="$emit('extend', station)"
                >
                    <Plus class="size-3.5" /> 30 min
                </button>
                <button
                    type="button"
                    class="btn btn-ghost min-h-9 px-3 text-xs"
                    :disabled="busy"
                    :aria-label="
                        station.session.status === 'paused'
                            ? `Retomar ${station.name}`
                            : `Pausar ${station.name}`
                    "
                    @click="$emit('togglePause', station)"
                >
                    <Play
                        v-if="station.session.status === 'paused'"
                        class="size-3.5"
                    />
                    <Pause v-else class="size-3.5" />
                </button>
                <button
                    type="button"
                    class="btn btn-danger min-h-9 px-3 text-xs"
                    :disabled="busy"
                    :aria-label="`Encerrar ${station.name}`"
                    @click="$emit('end', station)"
                >
                    <Power class="size-3.5" />
                </button>
            </template>
            <button
                v-else
                type="button"
                class="btn btn-ghost min-h-9 px-3 text-xs"
                :disabled="busy"
                @click="$emit('toggleMaintenance', station)"
            >
                <Wrench class="size-3.5" />
                {{
                    station.status === 'maintenance' ? 'Liberar' : 'Manutenção'
                }}
            </button>
            <a
                :href="`#${machinePath(station.id)}`"
                class="btn btn-ghost ml-auto min-h-9 px-3 text-xs"
                :aria-label="`Abrir tela da ${station.name}`"
            >
                <ExternalLink class="size-3.5" />
            </a>
        </footer>
    </article>
</template>
