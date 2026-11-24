import { openTimeCents } from '@cc/lib/billing';
import type { TerminalSnapshot } from '@cc/lib/terminal';
import {
    isValidPhone,
    lockWarning,
    normalizePhone,
    terminalState,
} from '@cc/lib/terminal';
import type { Session } from '@cc/types';
import { describe, expect, it } from 'vitest';

const NOW = new Date('2026-11-23T20:00:00Z').getTime();

function session(overrides: Partial<Session> = {}): Session {
    return {
        id: 1,
        station_id: 1,
        plan_id: 1,
        customer_name: null,
        billing: 'package',
        hourly_rate_cents: null,
        status: 'active',
        started_at: new Date(NOW - 30 * 60_000).toISOString(),
        ends_at: new Date(NOW + 30 * 60_000).toISOString(),
        closed_at: null,
        total_seconds: 3600,
        paused_remaining_seconds: null,
        credit_cents: 0,
        time_charged_cents: 800,
        items: [],
        prints: [],
        price_per_page_cents: 100,
        paid_cents: 800,
        ...overrides,
    };
}

function snapshot(overrides: Partial<TerminalSnapshot> = {}): TerminalSnapshot {
    return {
        station: { id: 1, name: 'PC 01', status: 'in_use' },
        locked: false,
        session: session(),
        server_time: new Date(NOW).toISOString(),
        ...overrides,
    };
}

describe('terminalState', () => {
    it('libera a máquina com pacote ativo dentro do tempo', () => {
        expect(terminalState(snapshot(), NOW)).toMatchObject({ locked: false });
    });

    it('bloqueia sem sessão', () => {
        expect(
            terminalState(snapshot({ session: null, locked: true }), NOW),
        ).toEqual({ locked: true, reason: 'no_session' });
    });

    it('bloqueia sozinha quando o pacote zera, antes do próximo heartbeat', () => {
        const expired = snapshot({
            session: session({ ends_at: new Date(NOW - 1000).toISOString() }),
        });

        expect(terminalState(expired, NOW)).toEqual({
            locked: true,
            reason: 'time_up',
        });
    });

    it('respeita o bloqueio decidido pelo servidor', () => {
        expect(terminalState(snapshot({ locked: true }), NOW)).toEqual({
            locked: true,
            reason: 'time_up',
        });
    });

    it('bloqueia em manutenção mesmo com sessão', () => {
        const maintenance = snapshot({
            station: { id: 1, name: 'PC 01', status: 'maintenance' },
        });

        expect(terminalState(maintenance, NOW)).toEqual({
            locked: true,
            reason: 'maintenance',
        });
    });

    it('tempo livre não tem fim previsto e continua liberado', () => {
        const open = snapshot({
            session: session({
                billing: 'open',
                ends_at: null,
                total_seconds: 0,
                hourly_rate_cents: 600,
                started_at: new Date(NOW - 5 * 3600_000).toISOString(),
            }),
        });

        expect(terminalState(open, NOW)).toMatchObject({ locked: false });
    });
});

describe('lockWarning', () => {
    it('avisa com 5 minutos e com 1 minuto', () => {
        const at = (seconds: number) =>
            lockWarning(
                session({
                    ends_at: new Date(NOW + seconds * 1000).toISOString(),
                }),
                NOW,
            );

        expect(at(10 * 60)).toBeNull();
        expect(at(5 * 60)).toBe('five_minutes');
        expect(at(60)).toBe('one_minute');
    });

    it('não avisa no tempo livre', () => {
        expect(
            lockWarning(session({ billing: 'open', ends_at: null }), NOW),
        ).toBeNull();
    });
});

describe('cobrança do tempo livre', () => {
    it('cobra por minuto iniciado, mínimo 1', () => {
        // R$ 6,00/h = 10 centavos por minuto.
        expect(openTimeCents(600, 0)).toBe(10);
        expect(openTimeCents(600, 61)).toBe(20);
        expect(openTimeCents(600, 3600)).toBe(600);
    });
});

describe('telefone do cliente', () => {
    it('normaliza para dígitos como o backend', () => {
        expect(normalizePhone('(11) 91234-5678')).toBe('11912345678');
        expect(isValidPhone('(11) 91234-5678')).toBe(true);
        expect(isValidPhone('91234-5678')).toBe(false);
    });
});
