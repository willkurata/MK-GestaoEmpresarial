import { computed } from 'vue';
import { DEMO_GYM, demoOccupancy, demoRevenue } from '../data/demo';
import { agingBucket, AGING_BUCKETS, daysOverdue, isAtRisk } from './billing';
import { daysBetween } from './format';
import { planFor, state } from './store';
import type { Student } from './types';

/**
 * Agregações dos relatórios do dono. Faturamento e ocupação histórica são
 * de demonstração (sem backend); inadimplência, risco e ativos são
 * calculados a partir dos alunos carregados.
 */
const today = new Date();

export const revenueMonths = demoRevenue(today);
export const occupancyGrid = demoOccupancy();
export const gymCapacity = DEMO_GYM.capacity;

export type OverdueStudent = {
    student: Student;
    days: number;
    amountInCents: number;
};

export const overdueStudents = computed<OverdueStudent[]>(() =>
    state.students
        .filter((student) => student.status !== 'inativo')
        .map((student) => ({
            student,
            days: daysOverdue(student, new Date()),
            amountInCents: planFor(student)?.monthlyPriceInCents ?? 0,
        }))
        .filter((item) => item.days > 0)
        .sort((a, b) => b.days - a.days),
);

export const overdueTotalInCents = computed(() =>
    overdueStudents.value.reduce(
        (total, item) => total + item.amountInCents,
        0,
    ),
);

export const agingSummary = computed(() =>
    AGING_BUCKETS.map((bucket) => {
        const items = overdueStudents.value.filter(
            (item) => agingBucket(item.days) === bucket,
        );

        return {
            label: bucket,
            value: items.reduce((total, item) => total + item.amountInCents, 0),
            count: items.length,
        };
    }),
);

export const activeStudents = computed(() =>
    state.students.filter((student) => student.status !== 'inativo'),
);

export const atRiskStudents = computed(() =>
    state.students
        .filter(
            (student) =>
                isAtRisk(student, new Date()) &&
                daysOverdue(student, new Date()) === 0,
        )
        .map((student) => ({
            student,
            daysAway:
                student.lastCheckInAt === null
                    ? null
                    : daysBetween(new Date(student.lastCheckInAt), new Date()),
        }))
        .sort((a, b) => (b.daysAway ?? 999) - (a.daysAway ?? 999)),
);

export const revenueByPlan = computed(() =>
    state.plans.map((plan) => {
        const students = activeStudents.value.filter(
            (student) => student.planId === plan.id,
        );

        return {
            label: plan.name,
            value: students.length * plan.monthlyPriceInCents,
            count: students.length,
        };
    }),
);

export const pixAutomaticoShare = computed(() => {
    const active = activeStudents.value;

    return active.length === 0
        ? 0
        : active.filter((student) => student.hasPixAutomatico).length /
              active.length;
});
