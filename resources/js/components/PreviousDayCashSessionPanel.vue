<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { index as previousDaysIndex, resolve as resolvePreviousDay } from '@/routes/pos/previous-days';

export type PreviousDaySession = {
    id: number;
    operator: string;
    openedAt: string;
    daysOpen: number;
    openingAmount: string;
    expectedCash: string;
    pixTotal: string;
    isOwn: boolean;
    canContinue: boolean;
    canResolve: boolean;
};

type Choice = 'continue' | 'close' | 'auto_close';

const props = defineProps<{ session: PreviousDaySession; canViewReport: boolean }>();

const choice = ref<Choice>(props.session.canContinue ? 'continue' : 'close');
const form = useForm<{ action: Choice; counted_cash: number | null; open_new_session: boolean; opening_amount: number | null }>({
    action: choice.value,
    counted_cash: null,
    open_new_session: true,
    opening_amount: null,
});
const formatCurrency = (amount: number | string): string => Number(amount).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const daysLabel = computed(() => (props.session.daysOpen === 1 ? 'desde ontem' : `há ${props.session.daysOpen} dias`));
const countedDifference = computed(() => (form.counted_cash === null ? null : form.counted_cash - Number(props.session.expectedCash)));
const transferDifference = computed(() => (form.opening_amount === null ? null : form.opening_amount - Number(props.session.expectedCash)));
const submitLabel = computed(() => {
    if (choice.value === 'continue') {
        return 'Continuar no mesmo turno';
    }
    if (form.open_new_session) {
        return 'Fechar e abrir novo turno';
    }

    return choice.value === 'close' ? 'Conferir e fechar' : 'Fechar automaticamente';
});

function submit(): void {
    form.action = choice.value;
    form.transform((data) => ({
        action: data.action,
        counted_cash: data.action === 'close' ? data.counted_cash : null,
        open_new_session: data.action === 'continue' ? false : data.open_new_session,
        opening_amount: data.action === 'auto_close' && data.open_new_session ? data.opening_amount : null,
    })).post(resolvePreviousDay({ cashSession: props.session.id }).url, { preserveScroll: true });
}
</script>

<template>
    <section class="rounded-xl border border-amber-500/40 bg-amber-50 p-5 text-amber-950 sm:p-6 dark:bg-amber-950/30 dark:text-amber-50" aria-labelledby="previous-day-title">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-sm font-medium">Caixa aberto {{ daysLabel }}</p>
                <h2 id="previous-day-title" class="mt-1 text-lg font-semibold">
                    {{ props.session.isOwn ? 'Seu turno' : `Turno de ${props.session.operator}` }} não foi fechado
                </h2>
                <p class="mt-1 text-sm opacity-80">Aberto em {{ props.session.openedAt }}. Novas vendas, retiradas e estornos ficam bloqueados até você decidir o que fazer com este turno.</p>
            </div>
            <Link v-if="props.canViewReport" :href="previousDaysIndex()" class="text-sm font-medium underline underline-offset-4">Relatório de turnos anteriores</Link>
        </div>

        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
            <div class="rounded-lg bg-white/60 px-3 py-2 dark:bg-black/20"><dt class="opacity-75">Valor de abertura</dt><dd class="font-semibold">{{ formatCurrency(props.session.openingAmount) }}</dd></div>
            <div class="rounded-lg bg-white/60 px-3 py-2 dark:bg-black/20"><dt class="opacity-75">Dinheiro esperado na gaveta</dt><dd class="font-semibold">{{ formatCurrency(props.session.expectedCash) }}</dd></div>
            <div class="rounded-lg bg-white/60 px-3 py-2 dark:bg-black/20"><dt class="opacity-75">Pix no turno</dt><dd class="font-semibold">{{ formatCurrency(props.session.pixTotal) }}</dd></div>
        </dl>

        <p v-if="!props.session.canResolve" class="mt-4 text-sm font-medium" role="status">
            Peça para {{ props.session.operator }}, um Administrador ou um Moderador resolver este turno antes de abrir o caixa de hoje.
        </p>

        <form v-else class="mt-5 space-y-4" @submit.prevent="submit">
            <fieldset class="grid gap-2">
                <legend class="mb-2 text-sm font-semibold">O que fazer com este turno?</legend>
                <label v-if="props.session.canContinue" class="flex cursor-pointer gap-3 rounded-lg border border-amber-500/30 bg-white/60 p-3 text-sm dark:bg-black/20">
                    <input v-model="choice" type="radio" value="continue" class="mt-1" />
                    <span><strong>Usar o mesmo caixa aberto</strong><br /><span class="opacity-80">Continua o turno de ontem hoje. O fechamento seguirá somando tudo desde a abertura.</span></span>
                </label>
                <label class="flex cursor-pointer gap-3 rounded-lg border border-amber-500/30 bg-white/60 p-3 text-sm dark:bg-black/20">
                    <input v-model="choice" type="radio" value="close" class="mt-1" />
                    <span><strong>Conferir e fechar</strong><br /><span class="opacity-80">Conte o dinheiro da gaveta. A diferença fica registrada no turno anterior e o valor contado pode ser transferido para o novo turno.</span></span>
                </label>
                <label class="flex cursor-pointer gap-3 rounded-lg border border-amber-500/30 bg-white/60 p-3 text-sm dark:bg-black/20">
                    <input v-model="choice" type="radio" value="auto_close" class="mt-1" />
                    <span><strong>Fechar automaticamente</strong><br /><span class="opacity-80">Fecha sem contagem pelo valor esperado. O turno fica marcado como "sem conferência" no relatório.</span></span>
                </label>
            </fieldset>

            <div v-if="choice !== 'continue'" class="grid gap-4 sm:grid-cols-2">
                <label v-if="choice === 'close'" class="grid gap-2 text-sm font-medium" for="previous-day-counted">Dinheiro contado na gaveta (R$)
                    <input id="previous-day-counted" v-model.number="form.counted_cash" type="number" min="0" step="0.01" inputmode="decimal" required class="h-11 rounded-md border border-input bg-background px-3 font-normal text-foreground outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                    <span v-if="countedDifference !== null" class="font-normal">Diferença: {{ formatCurrency(countedDifference) }}</span>
                    <span v-if="form.errors.counted_cash" class="font-normal text-destructive">{{ form.errors.counted_cash }}</span>
                </label>
                <label class="flex items-center gap-2 self-end text-sm font-medium">
                    <input v-model="form.open_new_session" type="checkbox" />
                    Abrir novo turno agora em meu nome, transferindo o saldo
                </label>
                <label v-if="choice === 'auto_close' && form.open_new_session" class="grid gap-2 text-sm font-medium" for="previous-day-opening">Dinheiro contado para abrir o novo turno (R$)
                    <input id="previous-day-opening" v-model.number="form.opening_amount" type="number" min="0" step="0.01" inputmode="decimal" required class="h-11 rounded-md border border-input bg-background px-3 font-normal text-foreground outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                    <span v-if="transferDifference !== null" class="font-normal">Diferença na transferência: {{ formatCurrency(transferDifference) }}</span>
                    <span v-if="form.errors.opening_amount" class="font-normal text-destructive">{{ form.errors.opening_amount }}</span>
                </label>
            </div>
            <p v-if="choice === 'close' && form.open_new_session" class="text-sm opacity-80">O novo turno abre com o valor contado acima.</p>

            <button type="submit" :disabled="form.processing" class="min-h-11 rounded-md bg-primary px-5 text-sm font-semibold text-primary-foreground disabled:opacity-60">
                {{ form.processing ? 'Salvando…' : submitLabel }}
            </button>
        </form>
    </section>
</template>
