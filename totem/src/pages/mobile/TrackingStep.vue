<script setup lang="ts">
import { Bell, BellRing, CircleCheck, ShieldCheck } from '@lucide/vue';
import OrderTimeline from '@totem/components/OrderTimeline.vue';
import PickupCode from '@totem/components/PickupCode.vue';
import type { Order } from '@totem/types';
import { computed } from 'vue';

const props = defineProps<{
    order: Order;
    alertsOn: boolean;
    notificationsBlocked: boolean;
}>();

const emit = defineEmits<{ enableAlerts: [] }>();

const isReady = computed(() => props.order.status === 'ready');

const purgeTime = computed(() =>
    props.order.file_purge_at
        ? new Date(props.order.file_purge_at).toLocaleTimeString('pt-BR', {
              hour: '2-digit',
              minute: '2-digit',
          })
        : null,
);
</script>

<template>
    <section class="animate-rise space-y-6">
        <header class="text-center">
            <span
                v-if="isReady"
                class="animate-pop bg-ok-soft text-ok mx-auto mb-3 grid size-20 place-items-center rounded-full"
            >
                <CircleCheck class="size-12" />
            </span>
            <h1
                class="font-display text-3xl font-extrabold tracking-tight"
                aria-live="assertive"
            >
                {{ isReady ? 'Pronto! Pode retirar.' : 'Pagamento recebido' }}
            </h1>
            <p class="text-ink-soft mt-1">
                {{
                    isReady
                        ? 'Pegue suas folhas na bandeja do totem.'
                        : 'Guarde o código. Ele aparece na tela do totem.'
                }}
            </p>
        </header>

        <div class="flex flex-col items-center gap-2">
            <PickupCode :code="order.pickup_code" size="xl" />
            <p class="text-ink-soft text-sm">Seu código de retirada</p>
        </div>

        <div class="bg-surface ring-line rounded-3xl p-5 ring-1">
            <OrderTimeline :status="order.status" :progress="order.progress" />
        </div>

        <div
            v-if="!isReady"
            class="bg-process-soft rounded-3xl p-4 text-sm"
            aria-live="polite"
        >
            <p class="flex items-start gap-2 font-semibold">
                <BellRing
                    v-if="alertsOn"
                    class="text-process size-5 shrink-0"
                />
                <Bell v-else class="text-process size-5 shrink-0" />
                <span v-if="alertsOn">
                    Avisos ligados: o celular vibra e toca quando ficar pronto.
                    Deixe esta página aberta.
                </span>
                <span v-else>
                    Quer um aviso quando ficar pronto? Deixe esta página aberta
                    e ative o som.
                </span>
            </p>
            <button
                v-if="!alertsOn"
                type="button"
                class="bg-process text-on-process mt-3 min-h-12 w-full rounded-2xl font-bold"
                @click="emit('enableAlerts')"
            >
                Ativar aviso
            </button>
            <p v-if="notificationsBlocked" class="text-ink-soft mt-2">
                As notificações do navegador estão bloqueadas; o aviso na página
                continua funcionando.
            </p>
        </div>

        <p
            v-if="isReady && purgeTime"
            class="text-ink-soft flex items-start gap-2 text-sm"
        >
            <ShieldCheck class="text-process size-5 shrink-0" />
            Seu arquivo será apagado às {{ purgeTime }}.
        </p>
        <p v-if="isReady" class="text-ink-soft text-center text-sm">
            Para imprimir outro arquivo, escaneie o QR do totem de novo.
        </p>
    </section>
</template>
