<script setup lang="ts">
import { CircleCheck, Copy, Repeat } from '@lucide/vue';
import { computed, ref } from 'vue';
import { accessCode, codeMatrix } from '../lib/accessCode';
import { formatCurrency } from '../lib/format';
import { confirmPayment, findStudent, planFor } from '../lib/store';
import { showToast } from '../lib/toast';

/**
 * Pagamento da mensalidade por Pix com oferta de Pix Automático.
 * O QR é ilustrativo: o QR dinâmico real vem do PSP escolhido no backend.
 */
const props = defineProps<{ studentId: string; size?: 'app' | 'kiosk' }>();
const emit = defineEmits<{ paid: [] }>();

const student = computed(() => findStudent(props.studentId));
const plan = computed(() => planFor(student.value));
const enableAutomatic = ref(!student.value?.hasPixAutomatico);
const paid = ref(false);

const matrix = computed(() =>
    codeMatrix(accessCode(`pix-${props.studentId}`, 1), 25),
);

function copyCode(): void {
    void navigator.clipboard?.writeText('00020126-PIX-DEMONSTRACAO');
    showToast({
        title: 'Código Pix copiado',
        description: 'Cole no app do seu banco.',
        tone: 'ok',
    });
}

function simulateConfirmation(): void {
    confirmPayment(props.studentId, enableAutomatic.value);
    paid.value = true;
    emit('paid');
}
</script>

<template>
    <div v-if="student && plan" class="space-y-4">
        <div
            v-if="!paid"
            class="border-ink-800 bg-ink-900 rounded-3xl border p-5 text-center"
        >
            <p class="text-ink-400 text-sm">
                Mensalidade · plano {{ plan.name }}
            </p>
            <p
                class="font-display tabular mt-1 font-bold"
                :class="size === 'kiosk' ? 'text-6xl' : 'text-4xl'"
            >
                {{ formatCurrency(plan.monthlyPriceInCents) }}
            </p>

            <div class="mx-auto mt-5 w-fit rounded-2xl bg-white p-3">
                <svg
                    :viewBox="`0 0 ${matrix.length} ${matrix.length}`"
                    :class="size === 'kiosk' ? 'size-64' : 'size-44'"
                    shape-rendering="crispEdges"
                    role="img"
                    aria-label="QR Code Pix ilustrativo"
                >
                    <template v-for="(row, rowIndex) in matrix" :key="rowIndex">
                        <rect
                            v-for="(dark, colIndex) in row"
                            v-show="dark"
                            :key="colIndex"
                            :x="colIndex"
                            :y="rowIndex"
                            width="1"
                            height="1"
                            fill="#0E1116"
                        />
                    </template>
                </svg>
            </div>
            <p class="text-ink-400 mt-2 text-xs">
                QR ilustrativo — o QR dinâmico real vem do provedor de pagamento
            </p>

            <button
                type="button"
                class="border-ink-700 mx-auto mt-4 flex min-h-11 items-center gap-2 rounded-xl border px-4 text-sm font-medium"
                @click="copyCode"
            >
                <Copy class="size-4" /> Copiar código Pix
            </button>
        </div>

        <label
            v-if="!paid && !student.hasPixAutomatico"
            class="flex cursor-pointer items-start gap-3 rounded-2xl border p-4 transition-colors"
            :class="
                enableAutomatic
                    ? 'border-lime-400/60 bg-lime-400/5'
                    : 'border-ink-800 bg-ink-900'
            "
        >
            <input
                v-model="enableAutomatic"
                type="checkbox"
                class="mt-1 size-5 shrink-0 accent-lime-400"
            />
            <span>
                <span class="flex items-center gap-2 font-semibold"
                    ><Repeat class="size-4 text-lime-400" /> Ativar Pix
                    Automático</span
                >
                <span class="text-ink-300 mt-1 block text-sm">
                    Você autoriza uma vez no app do banco, com valor máximo de
                    {{ formatCurrency(plan.monthlyPriceInCents) }}, e as
                    próximas mensalidades são pagas no vencimento. Cancele
                    quando quiser.
                </span>
            </span>
        </label>

        <button
            v-if="!paid"
            type="button"
            class="border-ink-600 text-ink-300 w-full rounded-2xl border border-dashed font-medium hover:border-lime-400 hover:text-lime-400"
            :class="size === 'kiosk' ? 'min-h-16 text-lg' : 'min-h-12 text-sm'"
            @click="simulateConfirmation"
        >
            Simular confirmação do banco (demonstração)
        </button>

        <div
            v-else
            class="animate-pop rounded-3xl border border-lime-400/50 bg-lime-400/5 p-6 text-center"
        >
            <CircleCheck class="mx-auto size-14 text-lime-400" />
            <p class="font-display mt-3 text-2xl font-bold">
                Pagamento confirmado
            </p>
            <p class="text-ink-300 mt-1">
                {{ enableAutomatic ? 'Pix Automático ativado. ' : '' }}Acesso
                liberado na catraca.
            </p>
        </div>
    </div>
</template>
