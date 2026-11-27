import { daysBetween, toLocalDateString } from './format';

export type WeekSummary = {
    weekStart: string;
    sessions: number;
};

/** Segunda-feira da semana da data informada. */
export function startOfWeek(date: Date): Date {
    const result = new Date(
        date.getFullYear(),
        date.getMonth(),
        date.getDate(),
    );
    const weekday = (result.getDay() + 6) % 7;
    result.setDate(result.getDate() - weekday);

    return result;
}

/**
 * Agrupa check-ins por semana (segunda a domingo), da mais antiga para a
 * mais recente, sempre retornando `weeks` semanas.
 */
export function weeklySessions(
    checkIns: string[],
    today: Date,
    weeks: number,
): WeekSummary[] {
    const currentWeek = startOfWeek(today);
    const days = new Set(
        checkIns.map((iso) => toLocalDateString(new Date(iso))),
    );

    return Array.from({ length: weeks }, (_, index) => {
        const weekStart = new Date(currentWeek);
        weekStart.setDate(weekStart.getDate() - (weeks - 1 - index) * 7);
        let sessions = 0;

        for (let offset = 0; offset < 7; offset++) {
            const day = new Date(weekStart);
            day.setDate(day.getDate() + offset);

            if (days.has(toLocalDateString(day))) {
                sessions++;
            }
        }

        return { weekStart: toLocalDateString(weekStart), sessions };
    });
}

/**
 * Sequência de semanas consecutivas atingindo a meta. A semana atual só
 * conta se já atingiu a meta (não quebra a sequência enquanto está em curso).
 */
export function weeklyStreak(
    checkIns: string[],
    today: Date,
    weeklyGoal: number,
): number {
    const summaries = weeklySessions(checkIns, today, 52);
    let streak = 0;

    for (let index = summaries.length - 1; index >= 0; index--) {
        const summary = summaries[index]!;
        const isCurrentWeek = index === summaries.length - 1;

        if (summary.sessions >= weeklyGoal) {
            streak++;
        } else if (!isCurrentWeek) {
            break;
        }
    }

    return streak;
}

export function daysSince(iso: string | null, today: Date): number | null {
    return iso === null ? null : daysBetween(new Date(iso), today);
}
