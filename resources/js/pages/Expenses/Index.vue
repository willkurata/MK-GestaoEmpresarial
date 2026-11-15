<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import {
    index as expensesIndex,
    store as storeExpense,
    update as updateExpense,
} from '@/routes/expenses';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Despesas', href: expensesIndex() }],
    },
});

type Expense = {
    id: number;
    description: string;
    amount: string | number;
    type: 'fixa' | 'variavel';
    expense_date: string;
};

type ExpenseFields = {
    description: string;
    amount: number | null;
    type: 'fixa' | 'variavel';
    expense_date: string;
};

const props = defineProps<{ expenses: Expense[] }>();
const editingExpenseId = ref<number | null>(null);
const formSection = ref<HTMLElement | null>(null);
const currentDate = new Date();
const today = [
    currentDate.getFullYear(),
    String(currentDate.getMonth() + 1).padStart(2, '0'),
    String(currentDate.getDate()).padStart(2, '0'),
].join('-');

const form = useForm<ExpenseFields>({
    description: '',
    amount: null,
    type: 'variavel',
    expense_date: today,
});

const total = computed(() =>
    props.expenses.reduce((sum, expense) => sum + Number(expense.amount), 0),
);

function resetForm(): void {
    editingExpenseId.value = null;
    form.reset();
    form.clearErrors();
    form.type = 'variavel';
    form.expense_date = today;
}

function editExpense(expense: Expense): void {
    editingExpenseId.value = expense.id;
    form.description = expense.description;
    form.amount = Number(expense.amount);
    form.type = expense.type;
    form.expense_date = expense.expense_date.slice(0, 10);
    form.clearErrors();
    void nextTick(() => {
        formSection.value?.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
        document
            .getElementById('expense-description')
            ?.focus({ preventScroll: true });
    });
}

function saveExpense(): void {
    const options = {
        preserveScroll: true,
        onSuccess: resetForm,
    };

    if (editingExpenseId.value) {
        form.patch(
            updateExpense({ expense: editingExpenseId.value }).url,
            options,
        );
        return;
    }

    form.post(storeExpense().url, options);
}

function formatAmount(amount: number | string): string {
    return Number(amount).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });
}

function formatDate(date: string): string {
    const [year, month, day] = date.slice(0, 10).split('-');

    return `${day}/${month}/${year}`;
}
</script>

<template>
    <Head title="Despesas" />
    <main
        class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6 lg:p-8"
    >
        <PageHeader
            eyebrow="Financeiro"
            title="Despesas"
            description="Registre gastos fixos (estruturais, como contas de consumo) e variáveis (eventuais)."
        />

        <section
            ref="formSection"
            class="scroll-mt-4 rounded-xl border bg-card p-5 text-card-foreground sm:p-6"
            :class="editingExpenseId ? 'ring-2 ring-primary/40' : ''"
            aria-labelledby="expense-form-title"
        >
            <h2 id="expense-form-title" class="text-lg font-semibold">
                {{ editingExpenseId ? 'Editar despesa' : 'Nova despesa' }}
            </h2>
            <p class="mt-1 text-sm text-muted-foreground">
                O lançamento fica registrado no histórico e não altera o saldo
                físico do caixa.
            </p>
            <form
                class="mt-5 grid gap-4 sm:grid-cols-2"
                @submit.prevent="saveExpense"
            >
                <label
                    for="expense-description"
                    class="grid gap-2 text-sm font-medium sm:col-span-2"
                >
                    Descrição
                    <input
                        id="expense-description"
                        v-model="form.description"
                        required
                        maxlength="255"
                        autocomplete="off"
                        class="h-11 rounded-md border border-input bg-background px-3 font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    />
                    <span
                        v-if="form.errors.description"
                        class="font-normal text-destructive"
                        >{{ form.errors.description }}</span
                    >
                </label>
                <label
                    for="expense-amount"
                    class="grid gap-2 text-sm font-medium"
                >
                    Valor (R$)
                    <input
                        id="expense-amount"
                        v-model.number="form.amount"
                        required
                        type="number"
                        min="0.01"
                        step="0.01"
                        inputmode="decimal"
                        class="h-11 rounded-md border border-input bg-background px-3 font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    />
                    <span
                        v-if="form.errors.amount"
                        class="font-normal text-destructive"
                        >{{ form.errors.amount }}</span
                    >
                </label>
                <label
                    for="expense-date"
                    class="grid gap-2 text-sm font-medium"
                >
                    Data
                    <input
                        id="expense-date"
                        v-model="form.expense_date"
                        required
                        type="date"
                        class="h-11 rounded-md border border-input bg-background px-3 font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    />
                    <span
                        v-if="form.errors.expense_date"
                        class="font-normal text-destructive"
                        >{{ form.errors.expense_date }}</span
                    >
                </label>
                <fieldset class="grid gap-2 sm:col-span-2">
                    <legend class="text-sm font-medium">Tipo</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label
                            v-for="option in [
                                {
                                    value: 'fixa',
                                    label: 'Fixa',
                                    hint: 'Estrutural ou recorrente',
                                },
                                {
                                    value: 'variavel',
                                    label: 'Variável',
                                    hint: 'Eventual',
                                },
                            ] as const"
                            :key="option.value"
                            class="flex min-h-11 cursor-pointer items-center gap-3 rounded-md border px-3 py-2 text-sm has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring"
                            :class="
                                form.type === option.value
                                    ? 'border-primary bg-primary/5'
                                    : 'hover:bg-muted'
                            "
                        >
                            <input
                                v-model="form.type"
                                type="radio"
                                name="expense_type"
                                :value="option.value"
                                class="accent-primary"
                            />
                            <span
                                ><span class="font-medium">{{
                                    option.label
                                }}</span>
                                <span class="text-muted-foreground"
                                    >· {{ option.hint }}</span
                                ></span
                            >
                        </label>
                    </div>
                    <span
                        v-if="form.errors.type"
                        class="text-sm text-destructive"
                        >{{ form.errors.type }}</span
                    >
                </fieldset>
                <div class="flex flex-wrap gap-3 sm:col-span-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="min-h-11 rounded-md bg-primary px-5 text-sm font-semibold text-primary-foreground disabled:opacity-60"
                    >
                        {{
                            form.processing
                                ? 'Salvando…'
                                : editingExpenseId
                                  ? 'Salvar alterações'
                                  : 'Registrar despesa'
                        }}
                    </button>
                    <button
                        v-if="editingExpenseId"
                        type="button"
                        class="min-h-11 rounded-md border px-5 text-sm font-medium hover:bg-muted"
                        @click="resetForm"
                    >
                        Cancelar edição
                    </button>
                </div>
            </form>
        </section>

        <section
            class="overflow-hidden rounded-xl border bg-card text-card-foreground"
            aria-labelledby="expense-list-title"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4 sm:px-6"
            >
                <div>
                    <h2 id="expense-list-title" class="font-semibold">
                        Histórico de despesas
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ props.expenses.length }} lançamento(s)
                    </p>
                </div>
                <p class="text-sm font-semibold">
                    Total registrado: {{ formatAmount(total) }}
                </p>
            </div>
            <div
                v-if="props.expenses.length === 0"
                class="px-5 py-12 text-center sm:px-6"
            >
                <p class="font-medium">Nenhuma despesa registrada</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Registre a primeira despesa pelo formulário acima.
                </p>
            </div>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[700px] text-left text-sm">
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th
                                scope="col"
                                class="px-5 py-3 font-medium sm:px-6"
                            >
                                Data
                            </th>
                            <th scope="col" class="px-5 py-3 font-medium">
                                Descrição
                            </th>
                            <th scope="col" class="px-5 py-3 font-medium">
                                Tipo
                            </th>
                            <th
                                scope="col"
                                class="px-5 py-3 text-right font-medium"
                            >
                                Valor
                            </th>
                            <th
                                scope="col"
                                class="px-5 py-3 text-right font-medium"
                            >
                                Ação
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="expense in props.expenses"
                            :key="expense.id"
                            class="border-t"
                            :class="
                                editingExpenseId === expense.id
                                    ? 'bg-primary/5'
                                    : ''
                            "
                        >
                            <td class="px-5 py-4 sm:px-6">
                                {{ formatDate(expense.expense_date) }}
                            </td>
                            <th scope="row" class="px-5 py-4 font-medium">
                                {{ expense.description }}
                            </th>
                            <td class="px-5 py-4">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="
                                        expense.type === 'fixa'
                                            ? 'bg-primary/10 text-primary'
                                            : 'bg-warning/15 text-warning'
                                    "
                                    >{{
                                        expense.type === 'fixa'
                                            ? 'Fixa'
                                            : 'Variável'
                                    }}</span
                                >
                            </td>
                            <td
                                class="px-5 py-4 text-right font-medium tabular-nums"
                            >
                                {{ formatAmount(expense.amount) }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                <button
                                    type="button"
                                    class="min-h-9 rounded-md px-3 font-medium text-primary underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    @click="editExpense(expense)"
                                >
                                    Editar<span class="sr-only">
                                        {{ expense.description }}</span
                                    >
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</template>
