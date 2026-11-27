import { describe, expect, it } from 'vitest';
import { evaluateAccess, publicDenialMessage } from './access';
import {
    accessCode,
    accessCodeWindow,
    secondsUntilRotation,
} from './accessCode';
import { agingBucket, isAtRisk, reminderStage } from './billing';
import { bookClass, cancelBooking } from './classes';
import { toLocalDateString } from './format';
import { weeklySessions, weeklyStreak } from './training';
import type { GymClass, Plan, Student } from './types';

const plan: Plan = {
    id: 'livre',
    name: 'Livre',
    monthlyPriceInCents: 10000,
    accessHours: [6, 22],
};

function student(overrides: Partial<Student> = {}): Student {
    return {
        id: 's1',
        name: 'Aluno Teste',
        planId: 'livre',
        status: 'ativo',
        dueDate: '2026-11-30',
        lastCheckInAt: '2026-11-22T08:00:00',
        phone: '',
        hasPixAutomatico: false,
        ...overrides,
    };
}

const context = {
    now: new Date(2026, 10, 23, 9, 0),
    occupancy: 10,
    capacity: 100,
    graceDays: 3,
};

describe('evaluateAccess', () => {
    it('libera aluno em dia dentro do horário do plano', () => {
        expect(evaluateAccess(student(), plan, context)).toEqual({
            allowed: true,
        });
    });

    it('respeita a tolerância antes de bloquear por mensalidade', () => {
        const withinGrace = student({ dueDate: '2026-11-20' });
        const beyondGrace = student({ dueDate: '2026-11-19' });

        expect(evaluateAccess(withinGrace, plan, context).allowed).toBe(true);
        expect(evaluateAccess(beyondGrace, plan, context)).toEqual({
            allowed: false,
            reason: 'mensalidade',
        });
    });

    it('bloqueia fora do horário, com lotação e sem identificação', () => {
        expect(
            evaluateAccess(student(), plan, {
                ...context,
                now: new Date(2026, 10, 23, 22, 30),
            }),
        ).toEqual({
            allowed: false,
            reason: 'fora_do_horario',
        });
        expect(
            evaluateAccess(student(), plan, { ...context, occupancy: 100 }),
        ).toEqual({ allowed: false, reason: 'lotacao' });
        expect(evaluateAccess(null, null, context)).toEqual({
            allowed: false,
            reason: 'nao_identificado',
        });
    });

    it('nunca expõe valores ou dívida na mensagem pública', () => {
        expect(publicDenialMessage('mensalidade')).not.toMatch(
            /R\$|dívida|atras/i,
        );
    });
});

describe('billing', () => {
    const today = new Date(2026, 10, 23);

    it('marca as etapas da régua D-3, D0 e D+3', () => {
        expect(reminderStage('2026-11-26', today)).toBe('D-3');
        expect(reminderStage('2026-11-23', today)).toBe('D0');
        expect(reminderStage('2026-11-20', today)).toBe('D+3');
        expect(reminderStage('2026-11-25', today)).toBeNull();
    });

    it('agrupa atrasos por faixa', () => {
        expect(agingBucket(0)).toBeNull();
        expect(agingBucket(15)).toBe('1–15 dias');
        expect(agingBucket(16)).toBe('16–30 dias');
        expect(agingBucket(31)).toBe('30+ dias');
    });

    it('considera em risco quem sumiu há 14+ dias ou está atrasado', () => {
        expect(
            isAtRisk(student({ lastCheckInAt: '2026-11-10T08:00:00' }), today),
        ).toBe(false);
        expect(
            isAtRisk(student({ lastCheckInAt: '2026-11-09T08:00:00' }), today),
        ).toBe(true);
        expect(isAtRisk(student({ dueDate: '2026-11-22' }), today)).toBe(true);
        expect(
            isAtRisk(
                student({ status: 'inativo', lastCheckInAt: null }),
                today,
            ),
        ).toBe(false);
    });
});

describe('reserva de aulas', () => {
    function gymClass(capacity: number, booked: string[] = []): GymClass {
        return {
            id: 'c1',
            name: 'Yoga',
            coach: '',
            room: '',
            startsAt: '2026-11-23T18:00:00',
            durationMinutes: 50,
            capacity,
            bookedStudentIds: [...booked],
            waitlistStudentIds: [],
        };
    }

    it('confirma com vaga e coloca na fila quando lotada', () => {
        const yoga = gymClass(1);

        expect(bookClass(yoga, 'a')).toEqual({ status: 'confirmada' });
        expect(bookClass(yoga, 'b')).toEqual({ status: 'fila', position: 1 });
        expect(bookClass(yoga, 'b')).toEqual({ status: 'ja_inscrito' });
    });

    it('promove o primeiro da fila quando alguém cancela', () => {
        const yoga = gymClass(1, ['a']);
        bookClass(yoga, 'b');
        bookClass(yoga, 'c');

        expect(cancelBooking(yoga, 'a')).toBe('b');
        expect(yoga.bookedStudentIds).toEqual(['b']);
        expect(yoga.waitlistStudentIds).toEqual(['c']);
    });

    it('sair da fila não promove ninguém', () => {
        const yoga = gymClass(1, ['a']);
        bookClass(yoga, 'b');

        expect(cancelBooking(yoga, 'b')).toBeNull();
        expect(yoga.bookedStudentIds).toEqual(['a']);
    });
});

describe('histórico de treino', () => {
    const today = new Date(2026, 10, 25); // quarta-feira

    function day(offset: number): string {
        const date = new Date(today);
        date.setDate(date.getDate() + offset);
        date.setHours(7);

        return date.toISOString();
    }

    it('agrupa check-ins por semana começando na segunda', () => {
        const weeks = weeklySessions(
            [day(-2), day(-1), day(-1), day(-7)],
            today,
            2,
        );

        expect(weeks.map((week) => week.weekStart)).toEqual([
            '2026-11-16',
            toLocalDateString(new Date(2026, 10, 23)),
        ]);
        expect(weeks.map((week) => week.sessions)).toEqual([1, 2]);
    });

    it('não quebra a sequência pela semana em andamento', () => {
        const lastTwoWeeks = [-9, -8, -7, -16, -15, -14].map(day);

        expect(weeklyStreak(lastTwoWeeks, today, 3)).toBe(2);
        expect(
            weeklyStreak([...lastTwoWeeks, day(-2), day(-1), day(0)], today, 3),
        ).toBe(3);
    });
});

describe('QR rotativo', () => {
    it('muda o código a cada janela de 30 s', () => {
        const start = Date.UTC(2026, 10, 23, 12, 0, 0);

        expect(accessCodeWindow(start)).toBe(accessCodeWindow(start + 29_000));
        expect(accessCode('s1', accessCodeWindow(start))).not.toBe(
            accessCode('s1', accessCodeWindow(start + 30_000)),
        );
        expect(secondsUntilRotation(start + 10_000)).toBe(20);
    });
});
