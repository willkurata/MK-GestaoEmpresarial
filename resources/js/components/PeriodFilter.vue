<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

type Filters = { period: string; startDate: string; endDate: string };

const props = defineProps<{
    url: string;
    filters: Filters;
    label?: string;
}>();

const form = useForm({
    period: props.filters.period,
    start_date: props.filters.startDate,
    end_date: props.filters.endDate,
});

// Mantém o formulário alinhado ao período que o servidor realmente aplicou.
watch(
    () => props.filters,
    (filters) => {
        form.period = filters.period;
        form.start_date = filters.startDate;
        form.end_date = filters.endDate;
    },
);

const quickPeriods = [
    { value: 'today', label: 'Hoje' },
    { value: 'last_7_days', label: '7 dias' },
    { value: 'this_month', label: 'Este mês' },
    { value: 'last_month', label: 'Mês anterior' },
    { value: 'custom', label: 'Personalizado' },
];

function applyFilters(): void {
    form.get(props.url, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function selectPeriod(period: string): void {
    form.period = period;

    if (period !== 'custom') {
        applyFilters();
    }
}
</script>

<template>
    <form
        class="flex flex-col gap-2 sm:items-end"
        :aria-label="label ?? 'Filtro de período'"
        :aria-busy="form.processing"
        @submit.prevent="applyFilters"
    >
        <div
            class="inline-flex flex-wrap gap-1 rounded-lg border bg-card p-1"
            role="group"
        >
            <button
                v-for="period in quickPeriods"
                :key="period.value"
                type="button"
                class="min-h-9 rounded-md px-3 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-60"
                :class="
                    form.period === period.value
                        ? 'bg-primary text-primary-foreground shadow-sm'
                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                "
                :aria-pressed="form.period === period.value"
                :disabled="form.processing"
                @click="selectPeriod(period.value)"
            >
                {{ period.label }}
            </button>
        </div>
        <div
            v-if="form.period === 'custom'"
            class="flex flex-wrap items-end gap-2"
        >
            <label class="grid gap-1 text-xs font-medium text-muted-foreground">
                De
                <input
                    v-model="form.start_date"
                    type="date"
                    required
                    class="h-10 rounded-md border border-input bg-background px-2 text-sm text-foreground outline-none focus-visible:ring-2 focus-visible:ring-ring"
                />
                <span
                    v-if="form.errors.start_date"
                    class="font-normal text-destructive"
                    >{{ form.errors.start_date }}</span
                >
            </label>
            <label class="grid gap-1 text-xs font-medium text-muted-foreground">
                Até
                <input
                    v-model="form.end_date"
                    type="date"
                    required
                    class="h-10 rounded-md border border-input bg-background px-2 text-sm text-foreground outline-none focus-visible:ring-2 focus-visible:ring-ring"
                />
                <span
                    v-if="form.errors.end_date"
                    class="font-normal text-destructive"
                    >{{ form.errors.end_date }}</span
                >
            </label>
            <button
                type="submit"
                :disabled="form.processing"
                class="min-h-10 rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground disabled:opacity-60"
            >
                {{ form.processing ? 'Aplicando…' : 'Aplicar' }}
            </button>
        </div>
        <p
            v-if="form.processing"
            class="text-xs text-muted-foreground"
            role="status"
        >
            Atualizando dados…
        </p>
    </form>
</template>
