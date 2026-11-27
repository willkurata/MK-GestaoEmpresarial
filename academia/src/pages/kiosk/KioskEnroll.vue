<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed, ref } from 'vue';
import PixPayment from '../../components/PixPayment.vue';
import KioskLayout from '../../layouts/KioskLayout.vue';
import { formatCurrency, firstName } from '../../lib/format';
import { enrollStudent, state } from '../../lib/store';

/** Matrícula self-service: plano → dados → pagamento → pronto. */
const step = ref<1 | 2 | 3>(1);
const planId = ref<string | null>(null);
const name = ref('');
const phone = ref('');
const acceptedTerms = ref(false);
const facialConsent = ref(false);
const studentId = ref<string | null>(null);

const steps = ['Plano', 'Seus dados', 'Pagamento'];

const canContinue = computed(
    () =>
        name.value.trim().split(/\s+/).length >= 2 &&
        phone.value.replace(/\D/g, '').length >= 10 &&
        acceptedTerms.value,
);

function choosePlan(id: string): void {
    planId.value = id;
    step.value = 2;
}

function confirmData(): void {
    if (!canContinue.value || planId.value === null) {
        return;
    }

    studentId.value = enrollStudent(
        name.value.trim(),
        planId.value,
        phone.value,
    ).id;
    step.value = 3;
}
</script>

<template>
    <KioskLayout title="Matrícula" :idle-seconds="120">
        <ol class="mb-8 flex gap-3" aria-label="Etapas">
            <li
                v-for="(label, index) in steps"
                :key="label"
                class="flex flex-1 items-center gap-3"
            >
                <span
                    class="grid size-10 shrink-0 place-items-center rounded-full font-bold"
                    :class="
                        index + 1 < step
                            ? 'text-ink-950 bg-lime-400'
                            : index + 1 === step
                              ? 'bg-ink-100 text-ink-950'
                              : 'bg-ink-800 text-ink-400'
                    "
                >
                    <Check v-if="index + 1 < step" class="size-5" />
                    <template v-else>{{ index + 1 }}</template>
                </span>
                <span
                    class="text-lg"
                    :class="
                        index + 1 === step ? 'font-semibold' : 'text-ink-400'
                    "
                    >{{ label }}</span
                >
            </li>
        </ol>

        <!-- 1. Plano -->
        <div v-if="step === 1" class="grid gap-5 md:grid-cols-3">
            <button
                v-for="plan in state.plans"
                :key="plan.id"
                type="button"
                class="border-ink-800 bg-ink-900 flex min-h-64 flex-col justify-between rounded-3xl border p-7 text-left hover:border-lime-400 active:scale-[0.98]"
                @click="choosePlan(plan.id)"
            >
                <span>
                    <span class="font-display block text-3xl font-bold">{{
                        plan.name
                    }}</span>
                    <span class="text-ink-300 mt-2 block text-lg"
                        >Acesso das {{ plan.accessHours[0] }}h às
                        {{ plan.accessHours[1] }}h</span
                    >
                    <span
                        v-if="plan.id === 'aulas'"
                        class="text-ink-300 mt-1 block text-lg"
                        >+ aulas coletivas com reserva</span
                    >
                </span>
                <span
                    class="font-display tabular text-4xl font-bold text-lime-400"
                >
                    {{ formatCurrency(plan.monthlyPriceInCents)
                    }}<span class="text-ink-400 text-lg">/mês</span>
                </span>
            </button>
        </div>

        <!-- 2. Dados -->
        <form
            v-else-if="step === 2"
            class="mx-auto w-full max-w-2xl space-y-5"
            @submit.prevent="confirmData"
        >
            <label class="block">
                <span class="text-ink-300 text-lg">Nome completo</span>
                <input
                    v-model="name"
                    type="text"
                    autocomplete="name"
                    class="border-ink-700 bg-ink-900 mt-2 h-16 w-full rounded-2xl border px-5 text-2xl outline-none focus:border-lime-400"
                />
            </label>
            <label class="block">
                <span class="text-ink-300 text-lg"
                    >Celular com DDD (para o WhatsApp)</span
                >
                <input
                    v-model="phone"
                    type="tel"
                    inputmode="tel"
                    autocomplete="tel"
                    class="border-ink-700 bg-ink-900 tabular mt-2 h-16 w-full rounded-2xl border px-5 text-2xl outline-none focus:border-lime-400"
                />
            </label>
            <label
                class="bg-ink-900 flex items-start gap-4 rounded-2xl p-4 text-lg"
            >
                <input
                    v-model="facialConsent"
                    type="checkbox"
                    class="mt-1 size-6 shrink-0 accent-lime-400"
                />
                <span>
                    Quero entrar com reconhecimento facial.
                    <span class="text-ink-400 block text-base"
                        >Opcional. Biometria é dado sensível: usada só para
                        liberar a catraca e apagada se você cancelar. Sem isso,
                        use o QR do app.</span
                    >
                </span>
            </label>
            <label
                class="bg-ink-900 flex items-start gap-4 rounded-2xl p-4 text-lg"
            >
                <input
                    v-model="acceptedTerms"
                    type="checkbox"
                    class="mt-1 size-6 shrink-0 accent-lime-400"
                />
                <span>Li e aceito o contrato e a política de privacidade.</span>
            </label>
            <div class="flex gap-4">
                <button
                    type="button"
                    class="border-ink-700 min-h-16 flex-1 rounded-2xl border text-xl font-semibold"
                    @click="step = 1"
                >
                    Voltar
                </button>
                <button
                    type="submit"
                    class="text-ink-950 min-h-16 flex-[2] rounded-2xl bg-lime-400 text-xl font-bold disabled:opacity-40"
                    :disabled="!canContinue"
                >
                    Continuar para o pagamento
                </button>
            </div>
        </form>

        <!-- 3. Pagamento -->
        <div v-else-if="studentId" class="mx-auto w-full max-w-xl space-y-4">
            <p class="text-center text-2xl">
                Quase lá, {{ firstName(name) }}! Pague a primeira mensalidade
                para liberar a catraca.
            </p>
            <PixPayment :student-id="studentId" size="kiosk" />
        </div>
    </KioskLayout>
</template>
