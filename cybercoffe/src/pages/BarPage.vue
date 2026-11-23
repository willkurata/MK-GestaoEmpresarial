<script setup lang="ts">
import { api, errorMessage } from '@cc/api';
import SessionTicker from '@cc/components/SessionTicker.vue';
import { usePolling } from '@cc/composables/usePolling';
import { syncServerClock, useServerNow } from '@cc/composables/useServerClock';
import type { Station } from '@cc/types';
import { computed, ref } from 'vue';

/**
 * Tela só com a barrinha (WIL-51), para o programa cliente abrir numa janela
 * pequena "sempre no topo" enquanto o cliente usa a máquina. O servidor é a
 * fonte da verdade: polling a cada 5 s, relógio local só interpola.
 */
const props = defineProps<{ stationId: number | null }>();

const station = ref<Station | null>(null);
const loadError = ref<string | null>(null);
const now = useServerNow();

const session = computed(() => {
    const current = station.value?.session ?? null;

    return current && current.status !== 'ended' ? current : null;
});

async function load(): Promise<void> {
    if (props.stationId === null) {
        return;
    }

    try {
        const response = await api.getStation(props.stationId);
        syncServerClock(response.server_time);
        station.value = response.data;
        loadError.value = null;
    } catch (error) {
        loadError.value = errorMessage(error);
    }
}

usePolling(load, 5000);
</script>

<template>
    <div class="flex min-h-dvh items-start justify-end p-1">
        <SessionTicker v-if="session" :session="session" :now="now" />
        <p
            v-else
            role="status"
            class="border-line bg-void/90 text-dim inline-flex h-7 items-center gap-2 border px-2.5 text-xs leading-none"
        >
            <span
                class="size-1.5"
                :class="loadError ? 'bg-danger' : 'bg-idle'"
                aria-hidden="true"
            />
            {{
                loadError
                    ? 'Sem conexão com o servidor'
                    : station
                      ? `${station.name} · livre`
                      : 'Carregando…'
            }}
        </p>
    </div>
</template>
