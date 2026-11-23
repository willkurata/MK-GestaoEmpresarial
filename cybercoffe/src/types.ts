/**
 * Contrato de dados do frontend CyberCoffe.
 *
 * Segue a proposta do WIL-34 (REST versionada `/api/v1`, servidor como
 * fonte da verdade do tempo). Valores monetários sempre em centavos.
 */

export type StationStatus =
    | 'free'
    | 'in_use'
    | 'paused'
    | 'reserved'
    | 'maintenance'
    | 'offline';

export type StationTier = 'gamer' | 'standard' | 'console';

export type SessionStatus = 'active' | 'paused' | 'ended';

export type PaymentMethod = 'counter' | 'credit';

export interface Session {
    id: number;
    station_id: number;
    plan_id: number;
    customer_name: string | null;
    status: SessionStatus;
    started_at: string;
    /** Fim previsto quando ativa. Durante pausa, use `paused_remaining_seconds`. */
    ends_at: string;
    total_seconds: number;
    paused_remaining_seconds: number | null;
    /** Saldo de crédito da carteira do cliente desta sessão. */
    credit_cents: number;
}

export interface Station {
    id: number;
    name: string;
    tier: StationTier;
    specs: string;
    status: StationStatus;
    session: Session | null;
}

export interface Plan {
    id: number;
    name: string;
    minutes: number;
    price_cents: number;
    description: string;
    highlight: boolean;
}

export interface DashboardMetrics {
    revenue_today_cents: number;
    active_stations: number;
    total_stations: number;
    customers_today: number;
    /** `null` quando ainda não há sessões encerradas no dia. */
    average_session_minutes: number | null;
}

/** Toda resposta carrega o relógio do servidor para calcular o offset local. */
export interface ApiEnvelope<T> {
    data: T;
    server_time: string;
}

export interface CheckinPayload {
    station_id: number;
    plan_id: number;
    customer_name: string | null;
    payment_method: PaymentMethod;
}
