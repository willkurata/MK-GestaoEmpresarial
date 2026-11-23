<script setup lang="ts">
import { api, errorMessage } from '@cc/api';
import FlashMessage from '@cc/components/FlashMessage.vue';
import PixelBlocks from '@cc/components/PixelBlocks.vue';
import PlanCard from '@cc/components/PlanCard.vue';
import StationCard from '@cc/components/StationCard.vue';
import { useFlash } from '@cc/composables/useFlash';
import { syncServerClock } from '@cc/composables/useServerClock';
import { formatDuration, formatMoney, TIER_LABEL } from '@cc/lib/format';
import { machinePath, navigate } from '@cc/router';
import type { PaymentMethod, Plan, Station, StationTier } from '@cc/types';
import { ArrowRight, Coins, RefreshCw, Store } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{ preselectedStationId: number | null }>();

const stations = ref<Station[]>([]);
const plans = ref<Plan[]>([]);
const isLoading = ref(true);
const loadError = ref<string | null>(null);
const isSubmitting = ref(false);

const selectedStationId = ref<number | null>(props.preselectedStationId);
const selectedPlanId = ref<number | null>(null);
const customerName = ref('');
const paymentMethod = ref<PaymentMethod>('counter');
const tierFilter = ref<StationTier | 'all'>('all');

const { flash, show, dismiss } = useFlash();

const filteredStations = computed(() =>
    tierFilter.value === 'all'
        ? stations.value
        : stations.value.filter((station) => station.tier === tierFilter.value),
);

const freeCount = computed(
    () => stations.value.filter((station) => station.status === 'free').length,
);

const selectedStation = computed(
    () =>
        stations.value.find(
            (station) => station.id === selectedStationId.value,
        ) ?? null,
);

const selectedPlan = computed(
    () => plans.value.find((plan) => plan.id === selectedPlanId.value) ?? null,
);

const canSubmit = computed(
    () =>
        selectedStation.value?.status === 'free' &&
        selectedPlan.value !== null &&
        !isSubmitting.value,
);

const tierOptions: { value: StationTier | 'all'; label: string }[] = [
    { value: 'all', label: 'Todas' },
    { value: 'gamer', label: TIER_LABEL.gamer },
    { value: 'standard', label: TIER_LABEL.standard },
    { value: 'console', label: TIER_LABEL.console },
];

async function load(): Promise<void> {
    isLoading.value = true;
    loadError.value = null;

    try {
        const [stationResponse, planResponse] = await Promise.all([
            api.listStations(),
            api.listPlans(),
        ]);
        syncServerClock(stationResponse.server_time);
        stations.value = stationResponse.data;
        plans.value = planResponse.data;
        selectedPlanId.value ??=
            plans.value.find((plan) => plan.highlight)?.id ?? null;
    } catch (error) {
        loadError.value = errorMessage(error);
    } finally {
        isLoading.value = false;
    }
}

async function reserve(): Promise<void> {
    if (!canSubmit.value || !selectedStation.value || !selectedPlan.value) {
        return;
    }

    isSubmitting.value = true;

    try {
        const { data } = await api.checkin({
            station_id: selectedStation.value.id,
            plan_id: selectedPlan.value.id,
            customer_name: customerName.value.trim() || null,
            payment_method: paymentMethod.value,
        });
        navigate(machinePath(data.station_id));
    } catch (error) {
        show('error', errorMessage(error));
        await load();
    } finally {
        isSubmitting.value = false;
    }
}

watch(
    () => props.preselectedStationId,
    (stationId) => (selectedStationId.value = stationId),
);

onMounted(load);
</script>

<template>
    <div class="mx-auto max-w-7xl px-4 pt-8 pb-32 sm:px-6 lg:pb-16">
        <section class="animate-rise relative mb-10">
            <PixelBlocks
                class="absolute -top-2 right-0 hidden md:grid"
                :columns="14"
                :rows="5"
                :size="10"
                :seed="3"
            />
            <p class="eyebrow">Passo 1 de 2 · Check-in</p>
            <h1
                class="mt-3 max-w-2xl text-4xl leading-[1.05] font-bold tracking-tight sm:text-5xl"
            >
                Escolha sua estação.<br />
                <span class="text-neon">Aperte o play.</span>
            </h1>
            <p class="text-dim mt-4 max-w-xl">
                <span class="tabular text-ink font-semibold">{{
                    freeCount
                }}</span>
                de {{ stations.length }} máquinas livres agora. O tempo só
                começa a contar quando você confirma.
            </p>
        </section>

        <div
            v-if="loadError"
            class="panel pixel-corners flex flex-col items-start gap-4 p-6"
        >
            <p class="text-danger">{{ loadError }}</p>
            <button type="button" class="btn btn-ghost" @click="load">
                <RefreshCw class="size-4" /> Tentar de novo
            </button>
        </div>

        <div v-else class="grid gap-8 lg:grid-cols-[1fr_360px]">
            <div class="flex flex-col gap-10">
                <!-- Estações -->
                <section aria-labelledby="stations-title">
                    <div
                        class="mb-4 flex flex-wrap items-end justify-between gap-3"
                    >
                        <h2
                            id="stations-title"
                            class="text-lg font-semibold tracking-wide"
                        >
                            Estação
                        </h2>
                        <div
                            role="group"
                            aria-label="Filtrar por tipo"
                            class="flex flex-wrap gap-1"
                        >
                            <button
                                v-for="option in tierOptions"
                                :key="option.value"
                                type="button"
                                :aria-pressed="tierFilter === option.value"
                                class="px-3 py-1.5 text-xs font-medium tracking-wide uppercase transition-colors"
                                :class="
                                    tierFilter === option.value
                                        ? 'bg-ink text-void'
                                        : 'text-dim ring-line-strong hover:text-ink ring-1 ring-inset'
                                "
                                @click="tierFilter = option.value"
                            >
                                {{ option.label }}
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="isLoading && stations.length === 0"
                        class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4"
                    >
                        <div
                            v-for="index in 8"
                            :key="index"
                            class="pixel-corners bg-panel-2 h-32 animate-pulse"
                        />
                    </div>
                    <div
                        v-else
                        role="radiogroup"
                        aria-labelledby="stations-title"
                        class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4"
                    >
                        <StationCard
                            v-for="station in filteredStations"
                            :key="station.id"
                            :station="station"
                            :selected="station.id === selectedStationId"
                            @select="selectedStationId = $event"
                        />
                    </div>
                </section>

                <!-- Planos -->
                <section aria-labelledby="plans-title">
                    <h2
                        id="plans-title"
                        class="mb-4 text-lg font-semibold tracking-wide"
                    >
                        Plano
                    </h2>
                    <div
                        role="radiogroup"
                        aria-labelledby="plans-title"
                        class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                    >
                        <PlanCard
                            v-for="plan in plans"
                            :key="plan.id"
                            :plan="plan"
                            :selected="plan.id === selectedPlanId"
                            @select="selectedPlanId = $event"
                        />
                    </div>
                    <p v-if="api.isDemo" class="text-dim mt-3 text-xs">
                        Planos e preços de exemplo (modo demonstração).
                    </p>
                </section>
            </div>

            <!-- Resumo -->
            <aside class="lg:sticky lg:top-24 lg:self-start">
                <form
                    class="panel pixel-corners flex flex-col gap-5 p-5"
                    @submit.prevent="reserve"
                >
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold tracking-wide">
                            Resumo
                        </h2>
                        <PixelBlocks
                            :columns="5"
                            :rows="2"
                            :size="6"
                            :seed="11"
                        />
                    </div>

                    <dl class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="eyebrow">Estação</dt>
                            <dd class="mt-1 text-xl font-bold">
                                {{ selectedStation?.name ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="eyebrow">Tempo</dt>
                            <dd class="tabular mt-1 text-xl font-bold">
                                {{
                                    selectedPlan
                                        ? formatDuration(selectedPlan.minutes)
                                        : '—'
                                }}
                            </dd>
                        </div>
                    </dl>

                    <label class="flex flex-col gap-1.5">
                        <span class="eyebrow">Seu nome (opcional)</span>
                        <input
                            v-model="customerName"
                            type="text"
                            maxlength="60"
                            autocomplete="given-name"
                            placeholder="Como chamar você no salão"
                            class="bg-void text-ink ring-line-strong placeholder:text-dim/70 focus:ring-neon h-11 border-0 px-3 text-sm ring-1 outline-none ring-inset focus:ring-2"
                        />
                    </label>

                    <fieldset class="flex flex-col gap-2">
                        <legend class="eyebrow mb-1.5">Pagamento</legend>
                        <div class="grid grid-cols-2 gap-2">
                            <label
                                v-for="option in [
                                    {
                                        value: 'counter' as const,
                                        label: 'No balcão',
                                        hint: 'Pix ou dinheiro',
                                        icon: Store,
                                    },
                                    {
                                        value: 'credit' as const,
                                        label: 'Crédito',
                                        hint: 'Da sua carteira',
                                        icon: Coins,
                                    },
                                ]"
                                :key="option.value"
                                class="has-focus-visible:outline-neon flex cursor-pointer flex-col gap-1 p-3 text-sm ring-inset has-focus-visible:outline-2"
                                :class="
                                    paymentMethod === option.value
                                        ? 'bg-sky/10 ring-sky ring-2'
                                        : 'bg-panel-2 ring-line-strong ring-1'
                                "
                            >
                                <input
                                    v-model="paymentMethod"
                                    type="radio"
                                    name="payment"
                                    :value="option.value"
                                    class="sr-only"
                                />
                                <component
                                    :is="option.icon"
                                    class="size-4"
                                    :class="
                                        paymentMethod === option.value
                                            ? 'text-sky'
                                            : 'text-dim'
                                    "
                                />
                                <span class="font-semibold">{{
                                    option.label
                                }}</span>
                                <span class="text-dim text-xs">{{
                                    option.hint
                                }}</span>
                            </label>
                        </div>
                    </fieldset>

                    <div
                        class="border-line-strong flex items-end justify-between border-t border-dashed pt-4"
                    >
                        <span class="eyebrow">Total</span>
                        <span class="tabular text-neon text-3xl font-bold">
                            {{
                                selectedPlan
                                    ? formatMoney(selectedPlan.price_cents)
                                    : '—'
                            }}
                        </span>
                    </div>

                    <FlashMessage
                        v-if="flash"
                        :tone="flash.tone"
                        :message="flash.message"
                        @dismiss="dismiss"
                    />

                    <button
                        type="submit"
                        class="btn btn-neon h-14 w-full text-base"
                        :disabled="!canSubmit"
                    >
                        {{ isSubmitting ? 'Reservando…' : 'Reservar' }}
                        <ArrowRight v-if="!isSubmitting" class="size-5" />
                    </button>
                    <p
                        v-if="!selectedStation"
                        class="text-dim -mt-2 text-center text-xs"
                    >
                        Selecione uma estação livre para continuar.
                    </p>
                    <p
                        v-else-if="paymentMethod === 'counter'"
                        class="text-dim -mt-2 text-center text-xs"
                    >
                        O atendente confirma o pagamento no balcão.
                    </p>
                </form>
            </aside>
        </div>
    </div>
</template>
