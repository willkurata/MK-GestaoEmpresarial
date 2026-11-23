/**
 * Contrato de dados do frontend CyberCoffe.
 *
 * Modelo interno do front: valores monetários sempre em centavos e status
 * em inglês. `api/http.ts` traduz do backend real (WIL-34, `/api/cybercoffe/v1`,
 * valores em string decimal e status em português) para este formato.
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

/**
 * `package`: tempo pré-pago (minutos fixos, preço fixo).
 * `open`: tempo livre, sem limite, cobrado por minuto iniciado ao encerrar (WIL-37).
 */
export type BillingMode = 'package' | 'open';

/** Produto avulso consumido na sessão (WIL-39). */
export interface SessionItem {
    id: number;
    /** `null` quando lançado com preço manual, fora do catálogo. */
    product_id: number | null;
    name: string;
    quantity: number;
    unit_price_cents: number;
    added_at: string;
}

/** Lançamento de páginas impressas na sessão (WIL-38). */
export interface PrintEntry {
    id: number;
    pages: number;
    /** Preço por página no momento do lançamento (snapshot). */
    price_per_page_cents: number;
    added_at: string;
}

export interface Session {
    id: number;
    station_id: number;
    plan_id: number | null;
    customer_name: string | null;
    billing: BillingMode;
    /** Tarifa por hora do tempo livre; `null` em pacote. */
    hourly_rate_cents: number | null;
    status: SessionStatus;
    started_at: string;
    /** Fim previsto do pacote ativo; `null` no tempo livre. */
    ends_at: string | null;
    closed_at: string | null;
    /** Total de segundos comprados no pacote; 0 no tempo livre. */
    total_seconds: number;
    paused_remaining_seconds: number | null;
    /** Saldo de crédito da carteira do cliente desta sessão. */
    credit_cents: number;
    /**
     * Tempo já cobrado: soma dos pacotes comprados ou, no tempo livre, o
     * valor final calculado pelo servidor no encerramento (0 enquanto ativa).
     */
    time_charged_cents: number;
    items: SessionItem[];
    prints: PrintEntry[];
    /** Preço por página vigente na loja, para novos lançamentos. */
    price_per_page_cents: number;
    paid_cents: number;
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
    billing: BillingMode;
    /** Minutos do pacote; 0 no tempo livre. */
    minutes: number;
    /** Preço do pacote; 0 no tempo livre. */
    price_cents: number;
    /** Tarifa por hora do tempo livre; `null` em pacote. */
    hourly_rate_cents: number | null;
    description: string;
    highlight: boolean;
}

/** Item do catálogo de produtos avulsos da loja (WIL-39). */
export interface Product {
    id: number;
    name: string;
    category: string;
    price_cents: number;
}

/** Produto com o preço do catálogo ou com preço manual. */
export type NewSessionItem =
    | { product_id: number; quantity: number }
    | { name: string; unit_price_cents: number; quantity: number };

export interface TopProduct {
    name: string;
    quantity: number;
    total_cents: number;
}

export interface DashboardMetrics {
    revenue_today_cents: number;
    /** Receita por origem; `null` quando o backend ainda não informa. */
    revenue_breakdown: {
        time_cents: number;
        prints_cents: number;
        products_cents: number;
    } | null;
    active_stations: number;
    total_stations: number;
    /** Sessões ativas em tempo livre agora. */
    open_sessions: number;
    /** Valor acumulado e ainda não cobrado das sessões em tempo livre. */
    open_sessions_running_cents: number;
    customers_today: number | null;
    /** `null` quando ainda não há sessões encerradas no dia. */
    average_session_minutes: number | null;
    pages_printed_today: number | null;
    products_sold_today: number | null;
    top_products: TopProduct[] | null;
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
