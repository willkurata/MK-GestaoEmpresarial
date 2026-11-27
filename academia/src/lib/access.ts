import { parseLocalDate } from './format';
import type {
    AccessDecision,
    AccessDenialReason,
    Plan,
    Student,
} from './types';

export type AccessContext = {
    now: Date;
    occupancy: number;
    capacity: number;
    /** Dias de tolerância após o vencimento antes de bloquear a catraca. */
    graceDays: number;
};

/**
 * Regra de liberação da catraca. A mesma regra roda no gateway local
 * (modo offline, com cache) e na API — aqui fica a versão de interface.
 */
export function evaluateAccess(
    student: Student | null,
    plan: Plan | null,
    context: AccessContext,
): AccessDecision {
    if (student === null || plan === null) {
        return deny('nao_identificado');
    }

    if (student.status === 'inativo') {
        return deny('inativo');
    }

    if (student.status === 'bloqueado' || isBeyondGrace(student, context)) {
        return deny('mensalidade');
    }

    const hour = context.now.getHours();
    const [startHour, endHour] = plan.accessHours;

    if (hour < startHour || hour >= endHour) {
        return deny('fora_do_horario');
    }

    if (context.occupancy >= context.capacity) {
        return deny('lotacao');
    }

    return { allowed: true };
}

function isBeyondGrace(student: Student, context: AccessContext): boolean {
    const limit = parseLocalDate(student.dueDate);
    limit.setDate(limit.getDate() + context.graceDays + 1);

    return context.now.getTime() >= limit.getTime();
}

function deny(reason: AccessDenialReason): AccessDecision {
    return { allowed: false, reason };
}

/**
 * Mensagem exibida na tela pública da catraca. É propositalmente discreta:
 * nunca expõe dívida ou valores na frente de outras pessoas.
 */
export function publicDenialMessage(reason: AccessDenialReason): string {
    switch (reason) {
        case 'mensalidade':
        case 'inativo':
            return 'Procure a recepção. Enviamos os detalhes no seu celular.';
        case 'fora_do_horario':
            return 'Seu plano não libera este horário.';
        case 'lotacao':
            return 'Academia lotada no momento. Tente em alguns minutos.';
        case 'nao_identificado':
            return 'Não reconhecemos você. Tente o QR do app.';
    }
}

/** Rótulo interno (painel do dono / log de acesso). */
export function denialLabel(reason: AccessDenialReason): string {
    switch (reason) {
        case 'mensalidade':
            return 'Mensalidade em atraso';
        case 'inativo':
            return 'Matrícula inativa';
        case 'fora_do_horario':
            return 'Fora do horário do plano';
        case 'lotacao':
            return 'Lotação máxima';
        case 'nao_identificado':
            return 'Não identificado';
    }
}
