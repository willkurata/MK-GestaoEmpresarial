import type { CyberCoffeApi } from '@cc/api/contract';
import { ApiError } from '@cc/api/contract';
import { billTotals } from '@cc/lib/billing';
import type {
    ApiEnvelope,
    CheckinPayload,
    DashboardMetrics,
    NewSessionItem,
    PaymentMethod,
    Plan,
    PrintEntry,
    Product,
    Session,
    SessionItem,
    Station,
    StationStatus,
    StationTier,
    TopProduct,
} from '@cc/types';

const TOKEN_STORAGE_KEY = 'cybercoffe.token';

function readToken(): string | null {
    try {
        return window.localStorage.getItem(TOKEN_STORAGE_KEY);
    } catch {
        return null;
    }
}

/* ------------------------------------------------------------------------
 * Formato do backend real (WIL-34, branch mk-lanhouse-back).
 * Dinheiro vem como string decimal ("12.50"), status em português.
 * Campos marcados "WIL-36" ainda não existem no backend: são o contrato
 * proposto para impressões e produtos avulsos (ver comentário no ticket).
 * --------------------------------------------------------------------- */

type Decimal = string | number | null | undefined;

interface BackendCharge {
    description: string;
    minutes: number | null;
    amount: Decimal;
}

/** WIL-36 (proposto). */
interface BackendItem {
    id: number;
    product_id: number | null;
    description: string;
    quantity: number;
    unit_price: Decimal;
    created_at: string;
}

/** WIL-36 (proposto). */
interface BackendPrint {
    id: number;
    pages: number;
    price_per_page: Decimal;
    created_at: string;
}

export interface BackendSession {
    id: number;
    station_id: number;
    plan_id?: number | null;
    customer?: { id: number; name: string } | null;
    billing: 'pacote' | 'avulso';
    hourly_rate: Decimal;
    status: 'ativa' | 'encerrada';
    started_at: string;
    ends_at: string | null;
    closed_at: string | null;
    elapsed_seconds: number;
    remaining_seconds: number | null;
    amount_due: Decimal;
    amount_paid: Decimal;
    charges?: BackendCharge[];
    server_time?: string;
    items?: BackendItem[];
    prints?: BackendPrint[];
    price_per_page?: Decimal;
    customer_credit_balance?: Decimal;
}

export interface BackendStation {
    id: number;
    name: string;
    type: 'pc' | 'console' | 'vr' | 'notebook' | 'outro';
    status: 'livre' | 'ocupada' | 'manutencao';
    last_seen_at: string | null;
    active_session?: BackendSession | null;
}

interface BackendPlan {
    id: number;
    name: string;
    billing: 'pacote' | 'avulso';
    price: Decimal;
    minutes: number | null;
    hourly_rate: Decimal;
}

interface BackendProduct {
    id: number;
    name: string;
    category: string | null;
    price: Decimal;
}

interface BackendRevenue {
    revenue_total: Decimal;
    by_purpose?: Record<string, Decimal>;
}

/** WIL-36 (proposto): `GET reports/consumption?from&to`. */
interface BackendConsumption {
    prints_pages: number;
    prints_total: Decimal;
    products_quantity: number;
    products_total: Decimal;
    sessions_count: number;
    average_session_minutes: number | null;
    top_products: { name: string; quantity: number; total: Decimal }[];
}

export function toCents(value: Decimal): number {
    return value === null || value === undefined
        ? 0
        : Math.round(Number(value) * 100);
}

function toDecimal(cents: number): string {
    return (cents / 100).toFixed(2);
}

export const STATION_STATUS: Record<BackendStation['status'], StationStatus> = {
    livre: 'free',
    ocupada: 'in_use',
    manutencao: 'maintenance',
};

const STATION_TIER: Record<BackendStation['type'], StationTier> = {
    pc: 'gamer',
    notebook: 'standard',
    outro: 'standard',
    console: 'console',
    vr: 'console',
};

const STATION_SPECS: Record<BackendStation['type'], string> = {
    pc: 'PC',
    notebook: 'Notebook',
    outro: 'Estação',
    console: 'Console',
    vr: 'Realidade virtual',
};

export function mapSession(session: BackendSession): Session {
    const isPackage = session.billing === 'pacote';
    const packageSeconds = (session.charges ?? []).reduce(
        (sum, charge) => sum + (charge.minutes ?? 0) * 60,
        0,
    );

    return {
        id: session.id,
        station_id: session.station_id,
        plan_id: session.plan_id ?? null,
        customer_name: session.customer?.name ?? null,
        billing: isPackage ? 'package' : 'open',
        hourly_rate_cents: isPackage ? null : toCents(session.hourly_rate),
        status: session.status === 'ativa' ? 'active' : 'ended',
        started_at: session.started_at,
        ends_at: session.ends_at,
        closed_at: session.closed_at,
        total_seconds: isPackage
            ? packageSeconds ||
              session.elapsed_seconds + (session.remaining_seconds ?? 0)
            : 0,
        paused_remaining_seconds: null,
        credit_cents: toCents(session.customer_credit_balance),
        // amount_due cobre só o tempo hoje; quando o WIL-36 somar produtos e
        // impressões nele, este cálculo passa a descontar essas parcelas.
        time_charged_cents: isPackage
            ? toCents(session.amount_due)
            : session.status === 'ativa'
              ? 0
              : toCents(session.amount_due),
        items: (session.items ?? []).map((item): SessionItem => ({
            id: item.id,
            product_id: item.product_id,
            name: item.description,
            quantity: item.quantity,
            unit_price_cents: toCents(item.unit_price),
            added_at: item.created_at,
        })),
        prints: (session.prints ?? []).map((entry): PrintEntry => ({
            id: entry.id,
            pages: entry.pages,
            price_per_page_cents: toCents(entry.price_per_page),
            added_at: entry.created_at,
        })),
        price_per_page_cents: toCents(session.price_per_page),
        paid_cents: toCents(session.amount_paid),
    };
}

function mapStation(station: BackendStation): Station {
    return {
        id: station.id,
        name: station.name,
        tier: STATION_TIER[station.type] ?? 'standard',
        specs: STATION_SPECS[station.type] ?? '',
        status: STATION_STATUS[station.status] ?? 'offline',
        session: station.active_session
            ? mapSession(station.active_session)
            : null,
    };
}

function mapPlan(plan: BackendPlan): Plan {
    const isPackage = plan.billing === 'pacote';

    return {
        id: plan.id,
        name: plan.name,
        billing: isPackage ? 'package' : 'open',
        minutes: isPackage ? (plan.minutes ?? 0) : 0,
        price_cents: isPackage ? toCents(plan.price) : 0,
        hourly_rate_cents: isPackage ? null : toCents(plan.hourly_rate),
        description: isPackage
            ? 'Tempo pré-pago.'
            : 'Sem limite. Paga só o tempo usado, ao sair.',
        highlight: false,
    };
}

function todayIso(): string {
    const now = new Date();
    const offset = now.getTimezoneOffset() * 60_000;

    return new Date(now.getTime() - offset).toISOString().slice(0, 10);
}

export interface RequestOptions {
    token: string | null;
    body?: unknown;
    /** Mensagem para 401 quando o servidor não manda uma. */
    unauthorizedMessage: string;
}

/**
 * Chamada JSON a `/api/cybercoffe/v1`. Compartilhada pelo painel do
 * atendente (`HttpApi`) e pelo programa cliente (`api/terminal.ts`), cada um
 * com o seu token Bearer.
 */
export async function request<T>(
    baseUrl: string,
    method: string,
    path: string,
    { token, body, unauthorizedMessage }: RequestOptions,
): Promise<{ payload: T; serverTime: string }> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
    };

    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    let response: Response;

    try {
        response = await fetch(
            `${baseUrl.replace(/\/$/, '')}/api/cybercoffe/v1${path}`,
            {
                method,
                headers,
                body: body === undefined ? undefined : JSON.stringify(body),
            },
        );
    } catch {
        throw new ApiError('Sem conexão com o servidor. Verifique a rede.', 0);
    }

    const payload = (await response.json().catch(() => null)) as
        | (T & { message?: string; errors?: Record<string, string[]> })
        | null;

    if (!response.ok || payload === null) {
        const firstError = payload?.errors
            ? Object.values(payload.errors)[0]?.[0]
            : undefined;

        throw new ApiError(
            firstError ??
                payload?.message ??
                (response.status === 401
                    ? unauthorizedMessage
                    : `Erro ${response.status} no servidor.`),
            response.status,
        );
    }

    const dateHeader = response.headers.get('Date');

    return {
        payload,
        serverTime: dateHeader
            ? new Date(dateHeader).toISOString()
            : new Date().toISOString(),
    };
}

/**
 * Cliente REST para o backend real em `/api/cybercoffe/v1` (WIL-34).
 * Autenticação: token Bearer do atendente salvo em `localStorage`
 * (`cybercoffe.token`), emitido por `POST auth/attendants/login`.
 */
export class HttpApi implements CyberCoffeApi {
    readonly isDemo = false;
    readonly supportsPause = false;

    constructor(private readonly baseUrl: string) {}

    private raw<T>(
        method: string,
        path: string,
        body?: unknown,
    ): Promise<{ payload: T; serverTime: string }> {
        return request<T>(this.baseUrl, method, path, {
            token: readToken(),
            body,
            unauthorizedMessage:
                'Sessão do atendente expirada. Entre novamente.',
        });
    }

    /** Respostas no formato `{ data: ... }` (API Resources do Laravel). */
    private async data<T>(
        method: string,
        path: string,
        body?: unknown,
    ): Promise<{ data: T; serverTime: string }> {
        const { payload, serverTime } = await this.raw<{ data: T }>(
            method,
            path,
            body,
        );

        return { data: payload.data, serverTime };
    }

    private async session(
        method: string,
        path: string,
        body?: unknown,
    ): Promise<ApiEnvelope<Session>> {
        const { data, serverTime } = await this.data<BackendSession>(
            method,
            path,
            body,
        );

        return {
            data: mapSession(data),
            server_time: data.server_time ?? serverTime,
        };
    }

    async listStations(): Promise<ApiEnvelope<Station[]>> {
        const { data, serverTime } = await this.data<BackendStation[]>(
            'GET',
            '/stations',
        );

        return { data: data.map(mapStation), server_time: serverTime };
    }

    async getStation(stationId: number): Promise<ApiEnvelope<Station>> {
        const { data, serverTime } = await this.data<BackendStation>(
            'GET',
            `/stations/${stationId}`,
        );

        return {
            data: mapStation(data),
            server_time: data.active_session?.server_time ?? serverTime,
        };
    }

    async listPlans(): Promise<ApiEnvelope<Plan[]>> {
        const { data, serverTime } = await this.data<BackendPlan[]>(
            'GET',
            '/plans',
        );

        return { data: data.map(mapPlan), server_time: serverTime };
    }

    checkin(payload: CheckinPayload): Promise<ApiEnvelope<Session>> {
        // O backend vincula cliente por `customer_id` (cadastro); o nome
        // livre do totem não é enviado.
        return this.session('POST', '/sessions', {
            station_id: payload.station_id,
            plan_id: payload.plan_id,
        });
    }

    pauseSession(): Promise<ApiEnvelope<Session>> {
        return Promise.reject(
            new ApiError('Pausa ainda não disponível no servidor.', 501),
        );
    }

    resumeSession(): Promise<ApiEnvelope<Session>> {
        return this.pauseSession();
    }

    extendSession(
        sessionId: number,
        planId: number,
        paymentMethod: PaymentMethod,
    ): Promise<ApiEnvelope<Session>> {
        return this.session('POST', `/sessions/${sessionId}/extend`, {
            plan_id: planId,
            payment_method: paymentMethod === 'credit' ? 'creditos' : 'balcao',
        });
    }

    endSession(sessionId: number): Promise<ApiEnvelope<Session>> {
        return this.session('POST', `/sessions/${sessionId}/checkout`);
    }

    async setMaintenance(
        stationId: number,
        underMaintenance: boolean,
    ): Promise<ApiEnvelope<Station>> {
        const { data, serverTime } = await this.data<BackendStation>(
            'PATCH',
            `/stations/${stationId}/status`,
            { status: underMaintenance ? 'manutencao' : 'livre' },
        );

        return { data: mapStation(data), server_time: serverTime };
    }

    /* ---- WIL-36: produtos avulsos e impressões (endpoints propostos) ---- */

    async listProducts(): Promise<ApiEnvelope<Product[]>> {
        const { data, serverTime } = await this.data<BackendProduct[]>(
            'GET',
            '/products',
        );

        return {
            data: data.map((product) => ({
                id: product.id,
                name: product.name,
                category: product.category ?? 'Outros',
                price_cents: toCents(product.price),
            })),
            server_time: serverTime,
        };
    }

    addSessionItem(
        sessionId: number,
        item: NewSessionItem,
    ): Promise<ApiEnvelope<Session>> {
        return this.session(
            'POST',
            `/sessions/${sessionId}/items`,
            'product_id' in item
                ? { product_id: item.product_id, quantity: item.quantity }
                : {
                      description: item.name,
                      unit_price: toDecimal(item.unit_price_cents),
                      quantity: item.quantity,
                  },
        );
    }

    removeSessionItem(
        sessionId: number,
        itemId: number,
    ): Promise<ApiEnvelope<Session>> {
        return this.session('DELETE', `/sessions/${sessionId}/items/${itemId}`);
    }

    addPrints(sessionId: number, pages: number): Promise<ApiEnvelope<Session>> {
        return this.session('POST', `/sessions/${sessionId}/prints`, {
            pages,
        });
    }

    removePrints(
        sessionId: number,
        entryId: number,
    ): Promise<ApiEnvelope<Session>> {
        return this.session(
            'DELETE',
            `/sessions/${sessionId}/prints/${entryId}`,
        );
    }

    /**
     * Combina `reports/revenue` (WIL-34, só gerente) com `stations` e, quando
     * existir, `reports/consumption` (WIL-36). Métricas que o servidor ainda
     * não fornece ficam `null` e aparecem como "—" no painel.
     */
    async getDashboard(): Promise<ApiEnvelope<DashboardMetrics>> {
        const day = todayIso();
        const period = `?from=${day}&to=${day}`;
        const [stations, revenue, consumption] = await Promise.all([
            this.listStations(),
            this.raw<BackendRevenue>('GET', `/reports/revenue${period}`)
                .then(({ payload }) => payload)
                .catch(() => null),
            this.raw<BackendConsumption>('GET', `/reports/consumption${period}`)
                .then(({ payload }) => payload)
                .catch(() => null),
        ]);

        const nowMs = new Date(stations.server_time).getTime();
        const sessions = stations.data.flatMap((station) =>
            station.session ? [station.session] : [],
        );
        const openSessions = sessions.filter(
            (session) => session.billing === 'open',
        );
        const printsCents = consumption ? toCents(consumption.prints_total) : 0;
        const productsCents = consumption
            ? toCents(consumption.products_total)
            : 0;
        const revenueCents = revenue ? toCents(revenue.revenue_total) : 0;

        return {
            data: {
                revenue_today_cents: revenueCents,
                revenue_breakdown: consumption
                    ? {
                          time_cents: Math.max(
                              0,
                              revenueCents - printsCents - productsCents,
                          ),
                          prints_cents: printsCents,
                          products_cents: productsCents,
                      }
                    : null,
                active_stations: sessions.length,
                total_stations: stations.data.length,
                open_sessions: openSessions.length,
                open_sessions_running_cents: openSessions.reduce(
                    (sum, session) => sum + billTotals(session, nowMs).time,
                    0,
                ),
                customers_today: consumption?.sessions_count ?? null,
                average_session_minutes:
                    consumption?.average_session_minutes ?? null,
                pages_printed_today: consumption?.prints_pages ?? null,
                products_sold_today: consumption?.products_quantity ?? null,
                top_products: consumption
                    ? consumption.top_products.map((product): TopProduct => ({
                          name: product.name,
                          quantity: product.quantity,
                          total_cents: toCents(product.total),
                      }))
                    : null,
            },
            server_time: stations.server_time,
        };
    }
}
