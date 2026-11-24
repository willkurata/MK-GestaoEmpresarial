import { remainingSeconds } from '@cc/composables/useServerClock';
import type { Session, StationStatus } from '@cc/types';

/**
 * Regras do programa cliente da máquina (WIL-49). O servidor é a fonte da
 * verdade (`GET agent/heartbeat` devolve `locked`), mas o terminal também
 * bloqueia sozinho no segundo em que o pacote acaba, sem esperar o próximo
 * heartbeat.
 */

export interface TerminalSnapshot {
    station: { id: number; name: string; status: StationStatus };
    /** Decisão do servidor: sem sessão ativa ou sessão expirada. */
    locked: boolean;
    session: Session | null;
    server_time: string;
}

export type LockReason = 'maintenance' | 'no_session' | 'time_up';

export type TerminalState =
    | { locked: true; reason: LockReason }
    | { locked: false; session: Session };

export function terminalState(
    snapshot: TerminalSnapshot,
    nowMs: number,
): TerminalState {
    if (snapshot.station.status === 'maintenance') {
        return { locked: true, reason: 'maintenance' };
    }

    const session = snapshot.session;

    if (!session || session.status === 'ended') {
        return { locked: true, reason: 'no_session' };
    }

    if (snapshot.locked) {
        return { locked: true, reason: 'time_up' };
    }

    if (
        session.billing === 'package' &&
        session.status === 'active' &&
        remainingSeconds(session, nowMs) <= 0
    ) {
        return { locked: true, reason: 'time_up' };
    }

    return { locked: false, session };
}

/** Avisos antes do bloqueio do pacote: 5 min e 1 min. */
export function lockWarning(
    session: Session,
    nowMs: number,
): 'five_minutes' | 'one_minute' | null {
    if (session.billing !== 'package' || session.status !== 'active') {
        return null;
    }

    const seconds = remainingSeconds(session, nowMs);

    if (seconds <= 60) {
        return 'one_minute';
    }

    return seconds <= 5 * 60 ? 'five_minutes' : null;
}

/** Telefone só com dígitos, como o backend normaliza (10 a 13 dígitos). */
export function normalizePhone(phone: string): string {
    return phone.replace(/\D/g, '');
}

export function isValidPhone(phone: string): boolean {
    const digits = normalizePhone(phone);

    return digits.length >= 10 && digits.length <= 13;
}
