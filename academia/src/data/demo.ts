/**
 * DADOS DE DEMONSTRAÇÃO
 *
 * Nada aqui é dado real de academia, aluno ou faturamento. O frontend ainda
 * não tem backend (ver WIL-43); estes registros existem só para exercitar as
 * telas e são sempre exibidos com o selo "Demonstração". Quando a API
 * existir, este módulo é substituído pelas respostas dela.
 */
import { toLocalDateString } from '../lib/format';
import type {
    AccessEvent,
    AppNotification,
    GymClass,
    Plan,
    RevenueMonth,
    Student,
    StudentStatus,
} from '../lib/types';

export const DEMO_GYM = {
    name: 'Academia Demonstração',
    capacity: 120,
    graceDays: 3,
};

export const DEMO_PLANS: Plan[] = [
    {
        id: 'livre',
        name: 'Livre',
        monthlyPriceInCents: 12990,
        accessHours: [5, 23],
    },
    {
        id: 'diurno',
        name: 'Diurno',
        monthlyPriceInCents: 8990,
        accessHours: [6, 16],
    },
    {
        id: 'aulas',
        name: 'Livre + Aulas',
        monthlyPriceInCents: 15990,
        accessHours: [5, 23],
    },
];

const FIRST_NAMES = [
    'Ana',
    'Bruno',
    'Carla',
    'Diego',
    'Elisa',
    'Felipe',
    'Gabriela',
    'Heitor',
    'Isabela',
    'João',
    'Larissa',
    'Marcos',
    'Natália',
    'Otávio',
    'Paula',
    'Rafael',
    'Sofia',
    'Thiago',
    'Vitória',
    'Yuri',
];

const LAST_NAMES = [
    'Almeida',
    'Barbosa',
    'Costa',
    'Duarte',
    'Ferreira',
    'Gomes',
    'Lima',
    'Moreira',
    'Nunes',
    'Oliveira',
    'Pereira',
    'Ribeiro',
    'Santos',
    'Teixeira',
];

/** PRNG determinístico (mulberry32) para a demonstração ser estável. */
function seededRandom(seed: number): () => number {
    let state = seed;

    return () => {
        state = (state + 0x6d2b79f5) | 0;
        let value = Math.imul(state ^ (state >>> 15), 1 | state);
        value = (value + Math.imul(value ^ (value >>> 7), 61 | value)) ^ value;

        return ((value ^ (value >>> 14)) >>> 0) / 4294967296;
    };
}

function addDays(date: Date, days: number): Date {
    const result = new Date(date);
    result.setDate(result.getDate() + days);

    return result;
}

function atTime(date: Date, hour: number, minute = 0): Date {
    const result = new Date(date);
    result.setHours(hour, minute, 0, 0);

    return result;
}

export const DEMO_CURRENT_STUDENT_ID = 's1';

export function demoStudents(today: Date): Student[] {
    const random = seededRandom(44);
    const students: Student[] = [];

    for (let index = 0; index < 48; index++) {
        const first = FIRST_NAMES[index % FIRST_NAMES.length]!;
        const last = LAST_NAMES[Math.floor(random() * LAST_NAMES.length)]!;
        const roll = random();
        let status: StudentStatus = 'ativo';
        let dueOffset = Math.floor(random() * 28) + 1;

        if (roll > 0.93) {
            status = 'inativo';
            dueOffset = -60;
        } else if (roll > 0.8) {
            status = 'inadimplente';
            dueOffset = -Math.floor(random() * 40) - 1;
        }

        const lastSeenDays = Math.floor(random() * (roll > 0.7 ? 30 : 6));

        students.push({
            id: `s${index + 1}`,
            name: `${first} ${last}`,
            planId: DEMO_PLANS[index % DEMO_PLANS.length]!.id,
            status,
            dueDate: toLocalDateString(addDays(today, dueOffset)),
            lastCheckInAt:
                index % 17 === 16
                    ? null
                    : atTime(
                          addDays(today, -lastSeenDays),
                          7 + (index % 12),
                      ).toISOString(),
            phone: `(11) 9${String(1000 + index).padStart(4, '0')}-0000`,
            hasPixAutomatico: random() > 0.55,
        });
    }

    // Aluno logado no app: em dia, vencimento em 3 dias (dispara D-3).
    students[0] = {
        ...students[0]!,
        name: 'Ana Ribeiro',
        planId: 'aulas',
        status: 'ativo',
        dueDate: toLocalDateString(addDays(today, 3)),
        hasPixAutomatico: false,
    };

    // Alunos fixos para testar a catraca.
    students[1] = {
        ...students[1]!,
        name: 'Bruno Costa',
        status: 'inadimplente',
        dueDate: toLocalDateString(addDays(today, -12)),
    };
    students[2] = {
        ...students[2]!,
        name: 'Carla Duarte',
        planId: 'diurno',
        status: 'ativo',
    };

    return students;
}

/**
 * Histórico de check-ins do aluno logado: rotina de seg/qua/sex com faltas
 * ocasionais e um treino extra de vez em quando, nas últimas 12 semanas.
 */
export function demoCheckInHistory(today: Date): string[] {
    const random = seededRandom(7);
    const history: string[] = [];

    for (let daysAgo = 84; daysAgo >= 1; daysAgo--) {
        const day = addDays(today, -daysAgo);
        const isRoutineDay = [1, 3, 5].includes(day.getDay());
        const trained = isRoutineDay
            ? random() > (daysAgo > 35 ? 0.3 : 0.08)
            : random() > 0.88;

        if (trained) {
            history.push(
                atTime(day, 6 + Math.floor(random() * 3), 30).toISOString(),
            );
        }
    }

    return history;
}

export function demoClasses(today: Date): GymClass[] {
    const templates = [
        {
            name: 'Funcional',
            coach: 'Prof. Lucas',
            room: 'Sala 1',
            hour: 7,
            capacity: 16,
        },
        {
            name: 'Spinning',
            coach: 'Profa. Renata',
            room: 'Bike',
            hour: 12,
            capacity: 20,
        },
        {
            name: 'Yoga',
            coach: 'Profa. Marina',
            room: 'Sala 2',
            hour: 18,
            capacity: 14,
        },
        {
            name: 'HIIT 30',
            coach: 'Prof. Lucas',
            room: 'Sala 1',
            hour: 19,
            capacity: 12,
        },
        {
            name: 'Pilates solo',
            coach: 'Profa. Marina',
            room: 'Sala 2',
            hour: 20,
            capacity: 10,
        },
    ];
    const random = seededRandom(19);
    const classes: GymClass[] = [];

    for (let dayOffset = 0; dayOffset < 3; dayOffset++) {
        templates.forEach((template, index) => {
            const isFull = (dayOffset + index) % 4 === 0;
            const booked = isFull
                ? template.capacity
                : Math.floor(random() * template.capacity * 0.8);

            classes.push({
                id: `c${dayOffset}-${index}`,
                name: template.name,
                coach: template.coach,
                room: template.room,
                startsAt: atTime(
                    addDays(today, dayOffset),
                    template.hour,
                ).toISOString(),
                durationMinutes: template.name === 'HIIT 30' ? 30 : 50,
                capacity: template.capacity,
                bookedStudentIds: Array.from(
                    { length: booked },
                    (_, seat) => `s${seat + 5}`,
                ),
                waitlistStudentIds: isFull ? ['s9', 's10'] : [],
            });
        });
    }

    return classes;
}

export function demoNotifications(today: Date): AppNotification[] {
    return [
        {
            id: 'n1',
            kind: 'mensalidade',
            title: 'Sua mensalidade vence em 3 dias',
            body: 'Pague com Pix agora ou ative o Pix Automático e esqueça o boleto.',
            createdAt: atTime(today, 8).toISOString(),
            read: false,
            action: { label: 'Pagar agora', to: '/aluno/pagamento' },
        },
        {
            id: 'n2',
            kind: 'promocao',
            title: 'Traga um amigo em dezembro',
            body: 'Quem indicar um amigo ganha 50% na próxima mensalidade.',
            createdAt: atTime(addDays(today, -1), 10).toISOString(),
            read: false,
        },
        {
            id: 'n3',
            kind: 'aula',
            title: 'Yoga começa em 1 hora',
            body: 'Sala 2 com Profa. Marina. Cancelar libera sua vaga para a fila.',
            createdAt: atTime(addDays(today, -2), 17).toISOString(),
            read: true,
        },
    ];
}

/** Faturamento mensal previsto x recebido — últimos 6 meses. */
export function demoRevenue(today: Date): RevenueMonth[] {
    const random = seededRandom(3);

    return Array.from({ length: 6 }, (_, index) => {
        const date = new Date(
            today.getFullYear(),
            today.getMonth() - (5 - index),
            1,
        );
        // Escala coerente com os ~45 alunos ativos da demonstração.
        const expected =
            540_000 + index * 9_000 + Math.floor(random() * 15_000);
        const isCurrentMonth = index === 5;
        const ratio = isCurrentMonth ? 0.71 : 0.86 + random() * 0.1;

        return {
            month: date
                .toLocaleDateString('pt-BR', { month: 'short' })
                .replace('.', ''),
            expectedInCents: expected,
            receivedInCents: Math.round(expected * ratio),
        };
    });
}

export const HEATMAP_HOURS = Array.from(
    { length: 17 },
    (_, index) => index + 6,
);
export const HEATMAP_DAYS = ['seg', 'ter', 'qua', 'qui', 'sex', 'sáb', 'dom'];

/** Média de pessoas na academia por dia da semana × hora (6h–22h). */
export function demoOccupancy(): number[][] {
    const random = seededRandom(11);

    return HEATMAP_DAYS.map((_, dayIndex) =>
        HEATMAP_HOURS.map((hour) => {
            const weekend = dayIndex >= 5;
            const morningPeak =
                Math.exp(-((hour - 7) ** 2) / 3) * (weekend ? 30 : 70);
            const eveningPeak =
                Math.exp(-((hour - 19) ** 2) / 4) * (weekend ? 25 : 105);
            const weekendMidday = weekend
                ? Math.exp(-((hour - 10) ** 2) / 6) * 55
                : 0;
            const base = 12 + random() * 10;

            return Math.round(base + morningPeak + eveningPeak + weekendMidday);
        }),
    );
}

export function demoAccessEvents(
    students: Student[],
    now: Date,
): AccessEvent[] {
    const events: AccessEvent[] = [];
    const methods = ['facial', 'qr', 'facial', 'rfid'] as const;

    for (let index = 0; index < 8; index++) {
        const student = students[(index * 5 + 3) % students.length]!;
        const at = new Date(now.getTime() - (index + 1) * 4 * 60_000);
        const allowed = student.status === 'ativo';

        events.push({
            id: `e-demo-${index}`,
            studentId: student.id,
            studentName: student.name,
            direction: index % 3 === 2 ? 'saida' : 'entrada',
            method: methods[index % methods.length]!,
            allowed,
            reason: allowed ? undefined : 'mensalidade',
            at: at.toISOString(),
        });
    }

    return events;
}
