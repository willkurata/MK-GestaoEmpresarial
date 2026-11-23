import type { CyberCoffeApi } from '@cc/api/contract';
import { ApiError } from '@cc/api/contract';
import type {
    ApiEnvelope,
    CheckinPayload,
    DashboardMetrics,
    PaymentMethod,
    Plan,
    Session,
    Station,
    StationTier,
} from '@cc/types';

/**
 * Modo demonstração: simula o backend no navegador enquanto as APIs do
 * WIL-34 não existem. Começa sem vendas nem sessões; todos os números do
 * painel vêm apenas das ações feitas nesta demonstração. Planos, preços e
 * o crédito inicial são exemplos, identificados como tal na interface.
 */

const STORAGE_KEY = 'cybercoffe.demo.v1';
const DEMO_STARTING_CREDIT_CENTS = 5000;

interface StoredStation {
    id: number;
    name: string;
    tier: StationTier;
    specs: string;
    under_maintenance: boolean;
}

interface StoredSession extends Session {
    used_seconds: number | null;
}

interface DemoState {
    stations: StoredStation[];
    sessions: StoredSession[];
    sales: { cents: number; at: string }[];
    wallet_cents: number;
    next_session_id: number;
}

const DEMO_PLANS: Plan[] = [
    {
        id: 1,
        name: 'Rápido',
        minutes: 30,
        price_cents: 500,
        description: 'Uma partida ou uma pesquisa rápida.',
        highlight: false,
    },
    {
        id: 2,
        name: '1 hora',
        minutes: 60,
        price_cents: 800,
        description: 'O plano mais pedido do salão.',
        highlight: true,
    },
    {
        id: 3,
        name: 'Maratona',
        minutes: 180,
        price_cents: 2000,
        description: 'Três horas para ranqueadas e campeonatos.',
        highlight: false,
    },
    {
        id: 4,
        name: 'Noitão',
        minutes: 480,
        price_cents: 3500,
        description: 'Das 22h às 6h. Café incluso no balcão.',
        highlight: false,
    },
];

function initialState(): DemoState {
    const stations: StoredStation[] = [];

    for (let index = 1; index <= 12; index++) {
        const tier: StationTier =
            index <= 6 ? 'gamer' : index <= 10 ? 'standard' : 'console';
        stations.push({
            id: index,
            name:
                tier === 'console'
                    ? `CONSOLE ${String(index - 10).padStart(2, '0')}`
                    : `PC ${String(index).padStart(2, '0')}`,
            tier,
            specs:
                tier === 'gamer'
                    ? 'RTX · 240 Hz · 32 GB'
                    : tier === 'standard'
                      ? 'Office · Web · 16 GB'
                      : 'Controle sem fio · TV 55"',
            under_maintenance: false,
        });
    }

    return {
        stations,
        sessions: [],
        sales: [],
        wallet_cents: DEMO_STARTING_CREDIT_CENTS,
        next_session_id: 1,
    };
}

function isToday(iso: string): boolean {
    return new Date(iso).toDateString() === new Date().toDateString();
}

export class DemoApi implements CyberCoffeApi {
    readonly isDemo = true;
    private state: DemoState;

    constructor() {
        this.state = this.load();
    }

    private load(): DemoState {
        try {
            const raw = window.localStorage.getItem(STORAGE_KEY);

            if (raw) {
                return JSON.parse(raw) as DemoState;
            }
        } catch {
            // Armazenamento indisponível: segue só em memória.
        }

        return initialState();
    }

    private save(): void {
        try {
            window.localStorage.setItem(
                STORAGE_KEY,
                JSON.stringify(this.state),
            );
        } catch {
            // Armazenamento indisponível: segue só em memória.
        }
    }

    /** Relê o estado salvo (outra aba pode ter alterado) e expira sessões vencidas. */
    private sync(): void {
        this.state = this.load();
        const now = Date.now();
        let changed = false;

        for (const session of this.state.sessions) {
            if (
                session.status === 'active' &&
                new Date(session.ends_at).getTime() <= now
            ) {
                session.status = 'ended';
                session.used_seconds = session.total_seconds;
                changed = true;
            }
        }

        if (changed) {
            this.save();
        }
    }

    private envelope<T>(data: T): Promise<ApiEnvelope<T>> {
        const result = {
            data: structuredClone(data),
            server_time: new Date().toISOString(),
        };

        return new Promise((resolve) => {
            window.setTimeout(() => resolve(result), 120);
        });
    }

    private openSessionFor(stationId: number): StoredSession | undefined {
        return this.state.sessions.find(
            (session) =>
                session.station_id === stationId && session.status !== 'ended',
        );
    }

    private toStation(stored: StoredStation): Station {
        const session = this.openSessionFor(stored.id) ?? null;
        const withWallet = session
            ? { ...session, credit_cents: this.state.wallet_cents }
            : null;

        return {
            id: stored.id,
            name: stored.name,
            tier: stored.tier,
            specs: stored.specs,
            status: stored.under_maintenance
                ? 'maintenance'
                : session
                  ? session.status === 'paused'
                      ? 'paused'
                      : 'in_use'
                  : 'free',
            session: withWallet,
        };
    }

    private findSession(sessionId: number): StoredSession {
        const session = this.state.sessions.find(
            (candidate) => candidate.id === sessionId,
        );

        if (!session || session.status === 'ended') {
            throw new ApiError('Sessão não encontrada ou já encerrada.', 404);
        }

        return session;
    }

    private findPlan(planId: number): Plan {
        const plan = DEMO_PLANS.find((candidate) => candidate.id === planId);

        if (!plan) {
            throw new ApiError('Plano inválido.', 422);
        }

        return plan;
    }

    private sessionResponse(
        session: StoredSession,
    ): Promise<ApiEnvelope<Session>> {
        this.save();

        return this.envelope({
            ...session,
            credit_cents: this.state.wallet_cents,
        });
    }

    async listStations(): Promise<ApiEnvelope<Station[]>> {
        this.sync();

        return this.envelope(
            this.state.stations.map((station) => this.toStation(station)),
        );
    }

    async getStation(stationId: number): Promise<ApiEnvelope<Station>> {
        this.sync();
        const station = this.state.stations.find(
            (candidate) => candidate.id === stationId,
        );

        if (!station) {
            throw new ApiError('Máquina não encontrada.', 404);
        }

        return this.envelope(this.toStation(station));
    }

    async listPlans(): Promise<ApiEnvelope<Plan[]>> {
        return this.envelope(DEMO_PLANS);
    }

    async checkin(payload: CheckinPayload): Promise<ApiEnvelope<Session>> {
        this.sync();
        const station = this.state.stations.find(
            (candidate) => candidate.id === payload.station_id,
        );

        if (!station) {
            throw new ApiError('Máquina não encontrada.', 404);
        }

        if (
            station.under_maintenance ||
            this.openSessionFor(station.id) !== undefined
        ) {
            throw new ApiError('Essa máquina não está livre agora.', 409);
        }

        const plan = this.findPlan(payload.plan_id);

        if (payload.payment_method === 'credit') {
            if (this.state.wallet_cents < plan.price_cents) {
                throw new ApiError(
                    'Crédito insuficiente. Escolha pagar no balcão.',
                    422,
                );
            }

            this.state.wallet_cents -= plan.price_cents;
        }

        const now = new Date();
        const session: StoredSession = {
            id: this.state.next_session_id++,
            station_id: station.id,
            plan_id: plan.id,
            customer_name: payload.customer_name?.trim() || null,
            status: 'active',
            started_at: now.toISOString(),
            ends_at: new Date(
                now.getTime() + plan.minutes * 60_000,
            ).toISOString(),
            total_seconds: plan.minutes * 60,
            paused_remaining_seconds: null,
            credit_cents: this.state.wallet_cents,
            used_seconds: null,
        };

        this.state.sessions.push(session);
        this.state.sales.push({
            cents: plan.price_cents,
            at: now.toISOString(),
        });

        return this.sessionResponse(session);
    }

    async pauseSession(sessionId: number): Promise<ApiEnvelope<Session>> {
        this.sync();
        const session = this.findSession(sessionId);

        if (session.status === 'active') {
            session.paused_remaining_seconds = Math.max(
                0,
                Math.round(
                    (new Date(session.ends_at).getTime() - Date.now()) / 1000,
                ),
            );
            session.status = 'paused';
        }

        return this.sessionResponse(session);
    }

    async resumeSession(sessionId: number): Promise<ApiEnvelope<Session>> {
        this.sync();
        const session = this.findSession(sessionId);

        if (
            session.status === 'paused' &&
            session.paused_remaining_seconds !== null
        ) {
            session.ends_at = new Date(
                Date.now() + session.paused_remaining_seconds * 1000,
            ).toISOString();
            session.paused_remaining_seconds = null;
            session.status = 'active';
        }

        return this.sessionResponse(session);
    }

    async extendSession(
        sessionId: number,
        planId: number,
        paymentMethod: PaymentMethod,
    ): Promise<ApiEnvelope<Session>> {
        this.sync();
        const session = this.findSession(sessionId);
        const plan = this.findPlan(planId);

        if (paymentMethod === 'credit') {
            if (this.state.wallet_cents < plan.price_cents) {
                throw new ApiError(
                    'Crédito insuficiente. Recarregue no balcão.',
                    422,
                );
            }

            this.state.wallet_cents -= plan.price_cents;
        }

        const extraSeconds = plan.minutes * 60;
        session.total_seconds += extraSeconds;

        if (session.status === 'paused') {
            session.paused_remaining_seconds =
                (session.paused_remaining_seconds ?? 0) + extraSeconds;
        } else {
            session.ends_at = new Date(
                new Date(session.ends_at).getTime() + extraSeconds * 1000,
            ).toISOString();
        }

        this.state.sales.push({
            cents: plan.price_cents,
            at: new Date().toISOString(),
        });

        return this.sessionResponse(session);
    }

    async endSession(sessionId: number): Promise<ApiEnvelope<Session>> {
        this.sync();
        const session = this.findSession(sessionId);
        const remainingSeconds =
            session.status === 'paused'
                ? (session.paused_remaining_seconds ?? 0)
                : Math.max(
                      0,
                      (new Date(session.ends_at).getTime() - Date.now()) / 1000,
                  );

        session.used_seconds = Math.round(
            session.total_seconds - remainingSeconds,
        );
        session.status = 'ended';
        session.paused_remaining_seconds = null;
        session.ends_at = new Date().toISOString();

        return this.sessionResponse(session);
    }

    async setMaintenance(
        stationId: number,
        underMaintenance: boolean,
    ): Promise<ApiEnvelope<Station>> {
        this.sync();
        const station = this.state.stations.find(
            (candidate) => candidate.id === stationId,
        );

        if (!station) {
            throw new ApiError('Máquina não encontrada.', 404);
        }

        if (underMaintenance && this.openSessionFor(stationId)) {
            throw new ApiError(
                'Encerre a sessão antes de colocar em manutenção.',
                409,
            );
        }

        station.under_maintenance = underMaintenance;
        this.save();

        return this.envelope(this.toStation(station));
    }

    async getDashboard(): Promise<ApiEnvelope<DashboardMetrics>> {
        this.sync();
        const todaySessions = this.state.sessions.filter((session) =>
            isToday(session.started_at),
        );
        const endedToday = todaySessions.filter(
            (session) =>
                session.status === 'ended' && session.used_seconds !== null,
        );
        const totalUsedSeconds = endedToday.reduce(
            (sum, session) => sum + (session.used_seconds ?? 0),
            0,
        );

        return this.envelope({
            revenue_today_cents: this.state.sales
                .filter((sale) => isToday(sale.at))
                .reduce((sum, sale) => sum + sale.cents, 0),
            active_stations: this.state.sessions.filter(
                (session) => session.status !== 'ended',
            ).length,
            total_stations: this.state.stations.length,
            customers_today: todaySessions.length,
            average_session_minutes:
                endedToday.length > 0
                    ? Math.round(totalUsedSeconds / endedToday.length / 60)
                    : null,
        });
    }

    /** Apaga apenas os dados desta demonstração no navegador. */
    reset(): void {
        this.state = initialState();
        this.save();
    }
}
