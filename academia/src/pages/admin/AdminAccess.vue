<script setup lang="ts">
import { Activity, DoorOpen, ShieldAlert, Wifi, WifiOff } from '@lucide/vue';
import { computed, ref } from 'vue';
import AccessLog from '../../components/AccessLog.vue';
import Card from '../../components/Card.vue';
import KpiCard from '../../components/KpiCard.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
import { state } from '../../lib/store';

const onlyDenied = ref(false);

const events = computed(() =>
    state.accessEvents.filter((event) => !onlyDenied.value || !event.allowed),
);
const entries = computed(
    () =>
        state.accessEvents.filter(
            (event) => event.allowed && event.direction === 'entrada',
        ).length,
);
const denied = computed(
    () => state.accessEvents.filter((event) => !event.allowed).length,
);

const devices = computed(() => [
    {
        name: 'Catraca 1 · entrada',
        model: 'Leitor facial + QR',
        online: state.gatewayOnline,
    },
    {
        name: 'Catraca 2 · saída',
        model: 'Leitor QR + cartão',
        online: state.gatewayOnline,
    },
    { name: 'Totem recepção', model: 'Touch 21"', online: true },
]);
</script>

<template>
    <AdminLayout
        title="Acesso"
        subtitle="Catracas, totem e registros em tempo real"
    >
        <template #actions>
            <a
                href="#/catraca"
                target="_blank"
                rel="noopener"
                class="bg-ink-950 hidden min-h-9 items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-white sm:flex"
            >
                <DoorOpen class="size-4" /> Abrir tela da catraca
            </a>
        </template>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-3 lg:gap-4">
            <KpiCard
                label="Na academia agora"
                :value="String(state.occupancy)"
                :hint="`capacidade ${state.gym.capacity}`"
                :icon="Activity"
                tone="live"
            />
            <KpiCard
                label="Entradas no registro"
                :value="String(entries)"
                hint="desde que a tela abriu"
                :icon="DoorOpen"
            />
            <KpiCard
                label="Bloqueios"
                :value="String(denied)"
                hint="mensalidade, horário ou lotação"
                :icon="ShieldAlert"
                tone="warn"
            />
        </div>

        <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
            <Card
                class="lg:col-span-2"
                title="Registro de acessos"
                description="Atualiza sozinho quando alguém passa na catraca (WebSocket em produção)"
            >
                <template #actions>
                    <label
                        class="flex items-center gap-2 text-sm text-slate-600"
                    >
                        <input
                            v-model="onlyDenied"
                            type="checkbox"
                            class="size-4 accent-lime-700"
                        />
                        Só bloqueios
                    </label>
                </template>
                <AccessLog :events="events" />
            </Card>

            <Card title="Equipamentos">
                <ul class="space-y-3">
                    <li
                        v-for="device in devices"
                        :key="device.name"
                        class="border-line flex items-center gap-3 rounded-xl border p-3"
                    >
                        <span
                            class="grid size-9 place-items-center rounded-lg"
                            :class="
                                device.online
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'text-warn-ink bg-amber-50'
                            "
                        >
                            <Wifi v-if="device.online" class="size-4" />
                            <WifiOff v-else class="size-4" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ device.name }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ device.model }}
                            </p>
                        </div>
                        <span
                            class="text-xs font-medium"
                            :class="
                                device.online
                                    ? 'text-emerald-700'
                                    : 'text-warn-ink'
                            "
                        >
                            {{
                                device.online
                                    ? 'Online'
                                    : 'Offline (lista local)'
                            }}
                        </span>
                    </li>
                </ul>
                <p class="mt-4 text-xs text-slate-500">
                    Sem internet, o gateway local continua liberando pela lista
                    de alunos em dia e sincroniza os registros depois.
                </p>
            </Card>
        </div>
    </AdminLayout>
</template>
