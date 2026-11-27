import { computed, reactive } from 'vue';
import {
    DEMO_CURRENT_STUDENT_ID,
    DEMO_GYM,
    DEMO_PLANS,
    demoAccessEvents,
    demoCheckInHistory,
    demoClasses,
    demoNotifications,
    demoStudents,
} from '../data/demo';
import { evaluateAccess } from './access';
import { daysOverdue, isAtRisk } from './billing';
import { bookClass, cancelBooking } from './classes';
import { toLocalDateString, parseLocalDate } from './format';
import type {
    AccessDecision,
    AccessDirection,
    AccessEvent,
    AppNotification,
    BookingResult,
    Campaign,
    CampaignAudience,
    IdentificationMethod,
    NotificationPreferences,
    Plan,
    Student,
} from './types';

/**
 * Estado do frontend enquanto a API (WIL-43) não existe. As ações abaixo
 * representam os endpoints esperados; trocar a implementação por chamadas
 * HTTP não muda as telas.
 */
const today = new Date();
const students = demoStudents(today);

export const state = reactive({
    gym: { ...DEMO_GYM },
    plans: DEMO_PLANS as Plan[],
    students,
    currentStudentId: DEMO_CURRENT_STUDENT_ID,
    checkInHistory: demoCheckInHistory(today),
    classes: demoClasses(today),
    notifications: demoNotifications(today),
    preferences: {
        push: true,
        whatsapp: true,
        email: false,
        promotions: true,
        quietHours: true,
    } as NotificationPreferences,
    accessEvents: demoAccessEvents(students, today),
    occupancy: 47,
    gatewayOnline: true,
    campaigns: [] as Campaign[],
});

export const currentStudent = computed(() =>
    state.students.find((student) => student.id === state.currentStudentId)!,
);

export const unreadCount = computed(
    () =>
        state.notifications.filter((notification) => !notification.read).length,
);

export function planFor(student: Student | null): Plan | null {
    if (student === null) {
        return null;
    }

    return state.plans.find((plan) => plan.id === student.planId) ?? null;
}

export function findStudent(studentId: string | null): Student | null {
    return state.students.find((student) => student.id === studentId) ?? null;
}

/* ------------------------------------------------------------------ */
/* Acesso / catraca                                                    */
/* ------------------------------------------------------------------ */

const channel =
    typeof BroadcastChannel === 'undefined'
        ? null
        : new BroadcastChannel('mk-academia-access');

type ChannelMessage = { type: 'access'; event: AccessEvent; occupancy: number };

channel?.addEventListener(
    'message',
    (message: MessageEvent<ChannelMessage>) => {
        if (message.data.type === 'access') {
            applyAccessEvent(message.data.event, message.data.occupancy);
        }
    },
);

function applyAccessEvent(event: AccessEvent, occupancy: number): void {
    if (state.accessEvents.some((existing) => existing.id === event.id)) {
        return;
    }

    state.accessEvents.unshift(event);
    state.accessEvents.splice(60);
    state.occupancy = occupancy;

    const student = findStudent(event.studentId);

    if (student && event.allowed && event.direction === 'entrada') {
        student.lastCheckInAt = event.at;
    }
}

export type AccessAttempt = {
    event: AccessEvent;
    decision: AccessDecision;
    student: Student | null;
};

/**
 * Processa uma identificação vinda da catraca, do totem ou do app.
 * Em produção: gateway → API → evento via WebSocket (Reverb).
 */
export function registerAccess(
    studentId: string | null,
    method: IdentificationMethod,
    direction: AccessDirection = 'entrada',
): AccessAttempt {
    const student = findStudent(studentId);
    const now = new Date();
    const decision: AccessDecision =
        direction === 'saida' && student !== null
            ? { allowed: true }
            : evaluateAccess(student, planFor(student), {
                  now,
                  occupancy: state.occupancy,
                  capacity: state.gym.capacity,
                  graceDays: state.gym.graceDays,
              });

    const event: AccessEvent = {
        id: `e-${now.getTime()}-${Math.random().toString(36).slice(2, 7)}`,
        studentId: student?.id ?? null,
        studentName: student?.name ?? 'Não identificado',
        direction,
        method,
        allowed: decision.allowed,
        reason: decision.allowed ? undefined : decision.reason,
        at: now.toISOString(),
    };

    let occupancy = state.occupancy;

    if (decision.allowed) {
        occupancy = Math.max(0, occupancy + (direction === 'entrada' ? 1 : -1));
    }

    applyAccessEvent(event, occupancy);
    channel?.postMessage({
        type: 'access',
        event,
        occupancy,
    } satisfies ChannelMessage);

    if (
        decision.allowed &&
        direction === 'entrada' &&
        student?.id === state.currentStudentId
    ) {
        state.checkInHistory.push(event.at);
    }

    if (
        !decision.allowed &&
        decision.reason === 'mensalidade' &&
        student !== null
    ) {
        notify(
            {
                kind: 'mensalidade',
                title: 'Acesso bloqueado pela mensalidade',
                body: 'Pague por Pix e a catraca libera na hora. O valor não aparece na tela da catraca.',
                action: { label: 'Pagar agora', to: '/aluno/pagamento' },
            },
            student.id,
        );
    }

    return { event, decision, student };
}

/* ------------------------------------------------------------------ */
/* Pagamentos                                                          */
/* ------------------------------------------------------------------ */

/** Confirma o pagamento da mensalidade e avança o vencimento em um mês. */
export function confirmPayment(
    studentId: string,
    enablePixAutomatico: boolean,
): void {
    const student = findStudent(studentId);

    if (student === null) {
        return;
    }

    const due = parseLocalDate(student.dueDate);
    const base = due.getTime() < Date.now() ? new Date() : due;
    base.setMonth(base.getMonth() + 1);
    student.dueDate = toLocalDateString(base);
    student.status = 'ativo';
    student.hasPixAutomatico = student.hasPixAutomatico || enablePixAutomatico;

    notify(
        {
            kind: 'mensalidade',
            title: 'Pagamento confirmado',
            body: enablePixAutomatico
                ? 'Pix Automático ativado. As próximas mensalidades serão debitadas no vencimento.'
                : 'Obrigado! Seu acesso está liberado.',
        },
        studentId,
    );
}

/* ------------------------------------------------------------------ */
/* Aulas                                                               */
/* ------------------------------------------------------------------ */

export function reserveClass(
    classId: string,
    studentId = state.currentStudentId,
): BookingResult | null {
    const gymClass = state.classes.find((item) => item.id === classId);

    return gymClass ? bookClass(gymClass, studentId) : null;
}

export function cancelClass(
    classId: string,
    studentId = state.currentStudentId,
): void {
    const gymClass = state.classes.find((item) => item.id === classId);

    if (!gymClass) {
        return;
    }

    const promoted = cancelBooking(gymClass, studentId);

    if (promoted !== null) {
        notify(
            {
                kind: 'aula',
                title: `Vaga liberada em ${gymClass.name}`,
                body: 'Você saiu da fila de espera e sua vaga está confirmada.',
            },
            promoted,
        );
    }
}

/* ------------------------------------------------------------------ */
/* Matrícula (totem / recepção)                                        */
/* ------------------------------------------------------------------ */

export function enrollStudent(
    name: string,
    planId: string,
    phone: string,
): Student {
    const student: Student = {
        id: `s${state.students.length + 1}`,
        name,
        planId,
        status: 'ativo',
        dueDate: toLocalDateString(new Date()),
        lastCheckInAt: null,
        phone,
        hasPixAutomatico: false,
    };

    state.students.push(student);

    return student;
}

/* ------------------------------------------------------------------ */
/* Notificações e campanhas                                            */
/* ------------------------------------------------------------------ */

/**
 * Registra uma notificação. Só a caixa do aluno logado é exibida nesta
 * demonstração; para os demais, a notificação seria enviada pelos canais
 * escolhidos (push, WhatsApp, e-mail).
 */
export function notify(
    notification: Omit<AppNotification, 'id' | 'createdAt' | 'read'>,
    studentId: string,
): void {
    if (studentId !== state.currentStudentId) {
        return;
    }

    if (notification.kind === 'promocao' && !state.preferences.promotions) {
        return;
    }

    state.notifications.unshift({
        ...notification,
        id: `n-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`,
        createdAt: new Date().toISOString(),
        read: false,
    });
}

export function markAllNotificationsRead(): void {
    state.notifications.forEach((notification) => {
        notification.read = true;
    });
}

export function audienceStudents(audience: CampaignAudience): Student[] {
    const now = new Date();

    return state.students.filter((student) => {
        switch (audience) {
            case 'todos':
                return student.status !== 'inativo';
            case 'ativos':
                return student.status === 'ativo';
            case 'inadimplentes':
                return (
                    student.status !== 'inativo' &&
                    daysOverdue(student, now) > 0
                );
            case 'em_risco':
                return isAtRisk(student, now);
        }
    });
}

export function sendCampaign(
    campaign: Omit<Campaign, 'id' | 'sentAt' | 'recipients'>,
): Campaign {
    const recipients = audienceStudents(campaign.audience);
    const sent: Campaign = {
        ...campaign,
        id: `cp-${Date.now()}`,
        sentAt: new Date().toISOString(),
        recipients: recipients.length,
    };

    state.campaigns.unshift(sent);

    if (recipients.some((student) => student.id === state.currentStudentId)) {
        notify(
            {
                kind:
                    campaign.audience === 'inadimplentes'
                        ? 'mensalidade'
                        : 'promocao',
                title: campaign.title,
                body: campaign.message,
            },
            state.currentStudentId,
        );
    }

    return sent;
}

export function todayString(): string {
    return toLocalDateString(new Date());
}
