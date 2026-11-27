<script setup lang="ts">
import { ArrowLeft, Hand } from '@lucide/vue';
import DemoBadge from '../components/DemoBadge.vue';
import { useIdleReset } from '../composables/useIdleReset';
import { navigate } from '../lib/router';
import { state } from '../lib/store';
import { showToast } from '../lib/toast';

const props = withDefaults(
    defineProps<{ title?: string; idleSeconds?: number; home?: boolean }>(),
    {
        idleSeconds: 30,
        home: false,
    },
);

/** Totem: volta ao início sozinho depois de um tempo sem toque. */
const { secondsLeft } = useIdleReset(props.idleSeconds, () => {
    if (!props.home) {
        navigate('/totem');
    }
});

function callReception(): void {
    showToast({
        title: 'Recepção chamada',
        description: 'Alguém da equipe já vem te ajudar.',
        tone: 'info',
    });
}
</script>

<template>
    <div class="bg-ink-950 flex min-h-dvh flex-col select-none">
        <header
            class="flex items-center justify-between gap-4 px-6 py-5 sm:px-10"
        >
            <div class="flex items-center gap-4">
                <a
                    v-if="!home"
                    href="#/totem"
                    class="bg-ink-800 text-ink-100 grid size-16 place-items-center rounded-2xl active:scale-95"
                    aria-label="Voltar ao início"
                >
                    <ArrowLeft class="size-8" />
                </a>
                <div>
                    <p class="text-ink-400 text-sm">{{ state.gym.name }}</p>
                    <h1 class="font-display text-2xl font-bold sm:text-3xl">
                        {{ title ?? 'Bem-vindo!' }}
                    </h1>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <DemoBadge />
                <button
                    type="button"
                    class="border-ink-700 flex min-h-16 items-center gap-2 rounded-2xl border px-5 text-lg font-semibold active:scale-95"
                    @click="callReception"
                >
                    <Hand class="size-6" />
                    <span class="hidden sm:inline">Chamar recepção</span>
                </button>
            </div>
        </header>

        <main class="flex flex-1 flex-col px-6 pb-6 sm:px-10">
            <slot />
        </main>

        <footer
            v-if="!home"
            class="text-ink-400 px-6 pb-5 text-center text-sm sm:px-10"
            aria-live="polite"
        >
            <span v-if="secondsLeft <= 10"
                >Voltando ao início em {{ secondsLeft }}s — toque na tela para
                continuar</span
            >
            <span v-else
                >Toque em voltar ou aguarde para retornar ao início</span
            >
        </footer>
    </div>
</template>
