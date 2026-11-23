import type { Session } from '@cc/types';

/**
 * Cálculo da conta da sessão no front. Espelha a regra do backend (WIL-34):
 * tempo livre cobra por minuto iniciado, mínimo 1, à tarifa/hora congelada
 * no check-in. O valor exibido durante a sessão é uma prévia; o valor final
 * é sempre o que o servidor devolve no encerramento.
 */

export function elapsedSeconds(session: Session, nowMs: number): number {
    const end = session.closed_at
        ? new Date(session.closed_at).getTime()
        : nowMs;

    return Math.max(
        0,
        Math.floor((end - new Date(session.started_at).getTime()) / 1000),
    );
}

export function billableMinutes(seconds: number): number {
    return Math.max(1, Math.ceil(seconds / 60));
}

export function openTimeCents(
    hourlyRateCents: number,
    seconds: number,
): number {
    return Math.round((billableMinutes(seconds) * hourlyRateCents) / 60);
}

export function itemsCents(session: Session): number {
    return session.items.reduce(
        (sum, item) => sum + item.quantity * item.unit_price_cents,
        0,
    );
}

export function printedPages(session: Session): number {
    return session.prints.reduce((sum, entry) => sum + entry.pages, 0);
}

export function printsCents(session: Session): number {
    return session.prints.reduce(
        (sum, entry) => sum + entry.pages * entry.price_per_page_cents,
        0,
    );
}

/** Valor do tempo: prévia ao vivo no tempo livre ativo, senão o já cobrado. */
export function timeCents(session: Session, nowMs: number): number {
    if (
        session.billing === 'open' &&
        session.status !== 'ended' &&
        session.hourly_rate_cents !== null
    ) {
        return openTimeCents(
            session.hourly_rate_cents,
            elapsedSeconds(session, nowMs),
        );
    }

    return session.time_charged_cents;
}

export interface BillTotals {
    time: number;
    prints: number;
    products: number;
    total: number;
    paid: number;
    balance: number;
}

export function billTotals(session: Session, nowMs: number): BillTotals {
    const time = timeCents(session, nowMs);
    const prints = printsCents(session);
    const products = itemsCents(session);
    const total = time + prints + products;

    return {
        time,
        prints,
        products,
        total,
        paid: session.paid_cents,
        balance: Math.max(0, total - session.paid_cents),
    };
}
