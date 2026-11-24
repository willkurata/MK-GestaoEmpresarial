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
                class="mx-auto mb-3 grid size-20 animate-pop place-items-center rounded-full bg-ok-soft text-ok"
            >
                <CircleCheck class="size-12" />
            </span>
            <h1
                class="font-display text-3xl font-extrabold tracking-tight"
                aria-live="assertive"
            >
                {{ isReady ? 'Pronto! Pode retirar.' : 'Pagamento recebido' }}
            </h1>
            <p class="mt-1 text-ink-soft">
                {{
                    isReady
                        ? 'Pegue suas folhas na bandeja do totem.'
                        : 'Guarde o código. Ele aparece na tela do totem.'
                }}
            </p>
        </header>

        <div class="flex flex-col items-center gap-2">
            <PickupCode :code="order.pickup_code" size="xl" />
            <p class="text-sm text-ink-soft">Seu código de retirada</p>
        </div>

        <div class="rounded-3xl bg-surface p-5 ring-1 ring-line">
            <OrderTimeline :status="order.status" :progress="order.progress" />
        </div>

        <div
            v-if="!isReady"
            class="rounded-3xl bg-process-soft p-4 text-sm"
            aria-live="polite"
        >
            <p class="flex items-start gap-2 font-semibold">
                <BellRing v-if="alertsOn" class="size-5 shrink-0 text-process" />
                <Bell v-else class="size-5 shrink-0 text-process" />
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
                class="mt-3 min-h-12 w-full rounded-2xl bg-process font-bold text-on-process"
                @click="emit('enableAlerts')"
            >
                Ativar aviso
            </button>
            <p v-if="notificationsBlocked" class="mt-2 text-ink-soft">
                As notificações do navegador estão bloqueadas; o aviso na
                página continua funcionando.
            </p>
        </div>

        <p
            v-if="isReady && purgeTime"
            class="flex items-start gap-2 text-sm text-ink-soft"
        >
            <ShieldCheck class="size-5 shrink-0 text-process" />
            Seu arquivo será apagado às {{ purgeTime }}.
        </p>
        <p v-if="isReady" class="text-center text-sm text-ink-soft">
            Para imprimir outro arquivo, escaneie o QR do totem de novo.
        </p>
    </section>
</template>
