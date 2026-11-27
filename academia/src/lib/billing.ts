import { daysBetween, parseLocalDate } from './format';
import type { Student } from './types';

export type ReminderStage = 'D-3' | 'D0' | 'D+3';

/**
 * Régua de cobrança: lembrete 3 dias antes, no dia e 3 dias depois do
 * vencimento. Retorna null nos demais dias.
 */
export function reminderStage(
    dueDate: string,
    today: Date,
): ReminderStage | null {
    const days = daysBetween(today, parseLocalDate(dueDate));

    if (days === 3) {
        return 'D-3';
    }

    if (days === 0) {
        return 'D0';
    }

    if (days === -3) {
        return 'D+3';
    }

    return null;
}

export function daysOverdue(student: Student, today: Date): number {
    return Math.max(0, daysBetween(parseLocalDate(student.dueDate), today));
}

export function daysUntilDue(student: Student, today: Date): number {
    return daysBetween(today, parseLocalDate(student.dueDate));
}

export type AgingBucket = '1–15 dias' | '16–30 dias' | '30+ dias';

export const AGING_BUCKETS: AgingBucket[] = [
    '1–15 dias',
    '16–30 dias',
    '30+ dias',
];

export function agingBucket(overdueDays: number): AgingBucket | null {
    if (overdueDays <= 0) {
        return null;
    }

    if (overdueDays <= 15) {
        return '1–15 dias';
    }

    if (overdueDays <= 30) {
        return '16–30 dias';
    }

    return '30+ dias';
}

/** Aluno em risco de evasão: sumido há 14+ dias ou com pagamento atrasado. */
export function isAtRisk(student: Student, today: Date): boolean {
    if (student.status === 'inativo') {
        return false;
    }

    if (daysOverdue(student, today) > 0) {
        return true;
    }

    if (student.lastCheckInAt === null) {
        return true;
    }

    return daysBetween(new Date(student.lastCheckInAt), today) >= 14;
}
