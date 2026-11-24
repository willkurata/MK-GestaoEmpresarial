<script setup lang="ts">
import { Monitor, RotateCcw, Smartphone } from '@lucide/vue';
import { api } from '@totem/api';
import {
    DEMO_PRINTER_SCENARIOS,
    readDemoPrinter,
    resetDemo,
    setDemoPrinter,
} from '@totem/api/demo';
import type { DemoPrinterScenario } from '@totem/api/demo';
import BrandMark from '@totem/components/BrandMark.vue';
import SegmentedControl from '@totem/components/SegmentedControl.vue';
import { ref, watch } from 'vue';

/**
 * Página de entrada para desenvolvimento e demonstração: abre a tela do
 * totem e permite simular estados da impressora.
 */
const totemCode = ref('LOJA01');
const scenario = ref<DemoPrinterScenario>(readDemoPrinter());
const cleared = ref(false);

watch(scenario, (value) => setDemoPrinter(value));

const scenarioOptions = (
    Object.keys(DEMO_PRINTER_SCENARIOS) as DemoPrinterScenario[]
).map((value) => ({ value, label: DEMO_PRINTER_SCENARIOS[value] }));

function clearOrders(): void {
    resetDemo();
    cleared.value = true;
    window.setTimeout(() => (cleared.value = false), 2000);
}
</script>

<template>
    <main class="mx-auto max-w-2xl space-y-8 px-4 py-10">
        <BrandMark size="lg" />

        <section class="space-y-2">
            <h1 class="font-display text-3xl font-extrabold tracking-tight">
                Totem de Impressão Autônoma 24h
            </h1>
            <p class="text-ink-soft">
                Frontend do totem (WIL-47).
                <template v-if="api.isDemo">
                    Rodando em <strong>modo demonstração</strong>: os pedidos
                    ficam neste navegador. Abra a tela do totem numa aba e
                    use o link "abrir no navegador (demo)" abaixo do QR para
                    abrir o fluxo do celular em outra aba. Um celular de
                    verdade na mesma rede também abre o fluxo pelo QR, mas a
                    fila do totem só mostra pedidos feitos neste navegador.
                </template>
                <template v-else>Conectado ao servidor real.</template>
            </p>
        </section>

        <section class="grid gap-3 sm:grid-cols-2">
            <a
                :href="`#/totem/${totemCode}`"
                class="flex min-h-24 items-center gap-4 rounded-3xl bg-surface p-5 ring-1 ring-line hover:ring-process"
            >
                <Monitor class="size-8 shrink-0 text-process" />
                <span>
                    <span class="block font-bold">Tela do totem</span>
                    <span class="block text-sm text-ink-soft">
                        16:9, sem toque (modo quiosque)
                    </span>
                </span>
            </a>
            <div
                class="flex min-h-24 items-center gap-4 rounded-3xl bg-surface p-5 ring-1 ring-line"
            >
                <Smartphone class="size-8 shrink-0 text-process" />
                <span>
                    <span class="block font-bold">Página do celular</span>
                    <span class="block text-sm text-ink-soft">
                        Abre pelo QR da tela do totem (token de 60 s)
                    </span>
                </span>
            </div>
        </section>

        <section class="space-y-2">
            <label for="totem-code" class="text-sm font-semibold text-ink-soft"
                >Código do totem</label
            >
            <input
                id="totem-code"
                v-model="totemCode"
                class="min-h-12 w-full rounded-2xl bg-surface px-4 font-mono ring-1 ring-line focus:ring-2 focus:ring-process focus:outline-none"
            />
        </section>

        <section v-if="api.isDemo" class="space-y-4">
            <SegmentedControl
                v-model="scenario"
                label="Simular impressora"
                :options="scenarioOptions"
            />
            <button
                type="button"
                class="flex min-h-12 items-center gap-2 rounded-2xl px-4 font-semibold text-process ring-1 ring-line"
                @click="clearOrders"
            >
                <RotateCcw class="size-4" />
                {{ cleared ? 'Pedidos apagados' : 'Apagar pedidos de teste' }}
            </button>
        </section>
    </main>
</template>
