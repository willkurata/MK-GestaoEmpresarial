<script setup lang="ts">
import { LoaderCircle, ScanLine } from '@lucide/vue';
import { api, errorMessage, rememberHandle } from '@totem/api';
import BrandMark from '@totem/components/BrandMark.vue';
import { navigate } from '@totem/router';
import { onMounted, ref } from 'vue';

/**
 * Destino do QR do totem. Troca o token do QR (prova de presença, ~60 s)
 * por um pedido em rascunho e segue para o fluxo do pedido.
 */
const props = defineProps<{ totemCode: string; token: string }>();

const error = ref<string | null>(null);

onMounted(async () => {
    if (!props.token) {
        error.value = 'QR Code incompleto. Escaneie de novo.';

        return;
    }

    try {
        const handle = await api.openSession(props.totemCode, props.token);
        rememberHandle(handle);
        // `replace`: voltar não reabre um QR já usado.
        navigate(`/pedido/${handle.order_id}`, true);
    } catch (failure) {
        error.value = errorMessage(failure);
    }
});
</script>

<template>
    <main
        class="mx-auto flex min-h-dvh max-w-md flex-col items-center justify-center gap-8 px-4 text-center"
    >
        <BrandMark size="lg" />
        <div v-if="error" class="space-y-4" role="alert">
            <span
                class="bg-warn-soft text-warn mx-auto grid size-16 place-items-center rounded-2xl"
            >
                <ScanLine class="size-8" />
            </span>
            <h1 class="font-display text-2xl font-extrabold">{{ error }}</h1>
            <p class="text-ink-soft">
                O QR da tela do totem muda a cada minuto, para garantir que você
                está na frente dele.
            </p>
        </div>
        <p
            v-else
            class="flex items-center gap-2 text-lg font-semibold"
            aria-live="polite"
        >
            <LoaderCircle class="text-process size-6 animate-spin" />
            Conectando ao totem…
        </p>
    </main>
</template>
