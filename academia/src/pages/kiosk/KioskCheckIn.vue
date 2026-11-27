<script setup lang="ts">
import { CircleCheck, CircleX } from '@lucide/vue';
import { ref } from 'vue';
import KioskIdentify from '../../components/KioskIdentify.vue';
import KioskLayout from '../../layouts/KioskLayout.vue';
import { publicDenialMessage } from '../../lib/access';
import { firstName } from '../../lib/format';
import { navigate } from '../../lib/router';
import type { AccessAttempt } from '../../lib/store';
import { registerAccess } from '../../lib/store';

const attempt = ref<AccessAttempt | null>(null);

function onIdentified(studentId: string, method: 'facial' | 'totem'): void {
    attempt.value = registerAccess(studentId, method);

    if (attempt.value.decision.allowed) {
        window.setTimeout(() => navigate('/totem'), 6000);
    }
}
</script>

<template>
    <KioskLayout title="Check-in">
        <KioskIdentify v-if="attempt === null" @identified="onIdentified" />

        <div
            v-else-if="attempt.decision.allowed"
            class="animate-pop flex flex-1 flex-col items-center justify-center text-center"
        >
            <CircleCheck class="size-32 text-lime-400" stroke-width="1.5" />
            <p class="font-display mt-6 text-5xl font-bold">Check-in feito!</p>
            <p class="text-ink-300 mt-3 text-2xl">
                Bom treino, {{ firstName(attempt.student?.name ?? '') }}. A
                catraca está liberada.
            </p>
        </div>

        <div
            v-else
            class="animate-pop flex flex-1 flex-col items-center justify-center text-center"
        >
            <CircleX class="text-danger size-28" stroke-width="1.5" />
            <p class="font-display mt-6 text-4xl font-bold">
                {{
                    attempt.student
                        ? `Olá, ${firstName(attempt.student.name)}`
                        : 'Não foi possível liberar'
                }}
            </p>
            <p class="text-ink-300 mt-3 max-w-xl text-2xl">
                {{
                    attempt.decision.allowed
                        ? ''
                        : publicDenialMessage(attempt.decision.reason)
                }}
            </p>
            <a
                v-if="
                    !attempt.decision.allowed &&
                    attempt.decision.reason === 'mensalidade'
                "
                href="#/totem/pagar"
                class="text-ink-950 mt-8 flex min-h-16 items-center rounded-2xl bg-lime-400 px-8 text-xl font-bold"
            >
                Regularizar agora com Pix
            </a>
        </div>
    </KioskLayout>
</template>
