<script setup lang="ts">
import { CircleAlert, ScanLine } from '@lucide/vue';
import type { Order } from '@totem/types';
import { computed } from 'vue';

/** Saídas do fluxo: falha, estorno, expiração ou cancelamento. */
const props = defineProps<{ order: Order; helpContact: string | null }>();

const content = computed(() => {
    switch (props.order.status) {
        case 'refunded':
            return {
                title: 'Não deu para imprimir',
                body: 'Devolvemos o valor do Pix para a sua conta.',
            };
        case 'failed':
            return {
                title: 'Algo deu errado na impressão',
                body:
                    props.order.failure_reason ??
                    'Já avisamos o suporte. Se você pagou, o valor será devolvido.',
            };
        case 'expired':
            return {
                title: 'O tempo para pagar acabou',
                body: 'Nada foi cobrado. Escaneie o QR do totem para começar de novo.',
            };
        default:
            return {
                title: 'Pedido cancelado',
                body: 'Nada foi cobrado e o arquivo foi descartado.',
            };
    }
});
</script>

<template>
    <section class="animate-rise space-y-5 text-center">
        <span
            class="bg-warn-soft text-warn mx-auto grid size-20 place-items-center rounded-full"
        >
            <CircleAlert class="size-11" />
        </span>
        <h1
            class="font-display text-3xl font-extrabold tracking-tight"
            aria-live="assertive"
        >
            {{ content.title }}
        </h1>
        <p class="text-ink-soft">{{ content.body }}</p>
        <p
            class="bg-surface ring-line mx-auto flex max-w-xs items-center justify-center gap-2 rounded-2xl p-4 text-sm ring-1"
        >
            <ScanLine class="text-process size-5 shrink-0" />
            Para imprimir, escaneie o QR na tela do totem.
        </p>
        <p v-if="helpContact" class="text-ink-soft text-sm">
            Precisa de ajuda? {{ helpContact }}
        </p>
    </section>
</template>
