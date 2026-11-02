<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { store as storeExpense, update as updateExpense } from '@/routes/expenses';

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

const total = computed(() => props.expenses.reduce((sum, expense) => sum + Number(expense.amount), 0));

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
}

function saveExpense(): void {
    const options = {
        preserveScroll: true,
        onSuccess: resetForm,
    };

    if (editingExpenseId.value) {
        form.patch(updateExpense({ expense: editingExpenseId.value }).url, options);
        return;
    }

    form.post(storeExpense().url, options);
}

function formatAmount(amount: number | string): string {
    return Number(amount).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function formatDate(date: string): string {
    const [year, month, day] = date.slice(0, 10).split('-');

    return `${day}/${month}/${year}`;
}
</script>

<template>
    <Head title="Despesas" />
    <main class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <header>
            <p class="text-sm font-medium text-muted-foreground">FINANCEIRO</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight">Despesas</h1>
            <p class="mt-2 text-sm text-muted-foreground">Registre gastos fixos e variáveis para acompanhar o negócio.</p>
        </header>

        <section class="rounded-xl border bg-card p-5 text-card-foreground sm:p-6" aria-labelledby="expense-form-title">
            <h2 id="expense-form-title" class="text-lg font-semibold">{{ editingExpenseId ? 'Editar despesa' : 'Nova despesa' }}</h2>
            <p class="mt-1 text-sm text-muted-foreground">O lançamento fica registrado no histórico e não altera o saldo físico do caixa.</p>
            <form class="mt-5 grid gap-4 sm:grid-cols-2" @submit.prevent="saveExpense">
                <label for="expense-description" class="grid gap-2 text-sm font-medium sm:col-span-2">
                    Descrição
                    <input id="expense-description" v-model="form.description" required maxlength="255" autocomplete="off" class="h-11 rounded-md border border-input bg-background px-3 font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                    <span v-if="form.errors.description" class="font-normal text-destructive">{{ form.errors.description }}</span>
                </label>
                <label for="expense-amount" class="grid gap-2 text-sm font-medium">
                    Valor (R$)
                    <input id="expense-amount" v-model.number="form.amount" required type="number" min="0.01" step="0.01" inputmode="decimal" class="h-11 rounded-md border border-input bg-background px-3 font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                    <span v-if="form.errors.amount" class="font-normal text-destructive">{{ form.errors.amount }}</span>
                </label>
                <label for="expense-date" class="grid gap-2 text-sm font-medium">
                    Data
                    <input id="expense-date" v-model="form.expense_date" required type="date" class="h-11 rounded-md border border-input bg-background px-3 font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                    <span v-if="form.errors.expense_date" class="font-normal text-destructive">{{ form.errors.expense_date }}</span>
                </label>
                <fieldset class="grid gap-2 sm:col-span-2">
                    <legend class="text-sm font-medium">Tipo</legend>
                    <div class="flex flex-wrap gap-5">
                        <label class="flex items-center gap-2 text-sm"><input v-model="form.type" type="radio" value="fixa" class="accent-primary" /> Fixa</label>
                        <label class="flex items-center gap-2 text-sm"><input v-model="form.type" type="radio" value="variavel" class="accent-primary" /> Variável</label>
                    </div>
                    <span v-if="form.errors.type" class="text-sm text-destructive">{{ form.errors.type }}</span>
                </fieldset>
                <div class="sm:col-span-2">
                    <button type="submit" :disabled="form.processing" class="min-h-11 rounded-md bg-primary px-5 text-sm font-semibold text-primary-foreground disabled:opacity-60">
                        {{ form.processing ? 'Salvando…' : editingExpenseId ? 'Salvar alterações' : 'Registrar despesa' }}
                    </button>
                    <button v-if="editingExpenseId" type="button" class="ml-2 min-h-11 rounded-md border px-5 text-sm font-medium hover:bg-muted" @click="resetForm">Cancelar edição</button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border bg-card text-card-foreground" aria-labelledby="expense-list-title">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4 sm:px-6">
                <div>
                    <h2 id="expense-list-title" class="font-semibold">Histórico de despesas</h2>
                    <p class="mt-1 text-sm text-muted-foreground">{{ props.expenses.length }} lançamento(s)</p>
                </div>
                <p class="text-sm font-semibold">Total registrado: {{ formatAmount(total) }}</p>
            </div>
            <div v-if="props.expenses.length === 0" class="px-5 py-12 text-center sm:px-6">
                <p class="font-medium">Nenhuma despesa registrada</p>
                <p class="mt-1 text-sm text-muted-foreground">Os lançamentos feitos aparecerão aqui.</p>
            </div>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[700px] text-left text-sm">
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-medium sm:px-6">Data</th>
                            <th scope="col" class="px-5 py-3 font-medium">Descrição</th>
                            <th scope="col" class="px-5 py-3 font-medium">Tipo</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium">Valor</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="expense in props.expenses" :key="expense.id" class="border-t">
                            <td class="px-5 py-4 sm:px-6">{{ formatDate(expense.expense_date) }}</td>
                            <th scope="row" class="px-5 py-4 font-medium">{{ expense.description }}</th>
                            <td class="px-5 py-4">{{ expense.type === 'fixa' ? 'Fixa' : 'Variável' }}</td>
                            <td class="px-5 py-4 text-right font-medium">{{ formatAmount(expense.amount) }}</td>
                            <td class="px-5 py-4 text-right"><button type="button" class="min-h-9 rounded-md px-3 font-medium text-primary underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" @click="editExpense(expense)">Editar</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</template>
