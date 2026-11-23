import type { CyberCoffeApi } from '@cc/api/contract';
import { ApiError } from '@cc/api/contract';
import {
    billTotals,
    elapsedSeconds,
    itemsCents,
    openTimeCents,
    printedPages,
    printsCents,
} from '@cc/lib/billing';
import type {
    ApiEnvelope,
    CheckinPayload,
    DashboardMetrics,
    NewSessionItem,
    PaymentMethod,
    Plan,
    Product,
    Session,
    Station,
    StationTier,
    TopProduct,
} from '@cc/types';

/**
 * Modo demonstração: simula o backend no navegador. Começa sem vendas nem
 * sessões; todos os números do painel vêm apenas das ações feitas nesta
 * demonstração. Planos, preços, produtos, preço por página e o crédito
 * inicial são exemplos, identificados como tal na interface.
 */

const STORAGE_KEY = 'cybercoffe.demo.v2';
const DEMO_STARTING_CREDIT_CENTS = 5000;
const DEMO_PRICE_PER_PAGE_CENTS = 100;
const MAX_PAGES_PER_ENTRY = 500;
const MAX_ITEM_QUANTITY = 50;

interface StoredStation {
    id: number;
    name: string;
    tier: StationTier;
    specs: string;
    under_maintenance: boolean;
}

interface DemoState {
    stations: StoredStation[];
    sessions: Session[];
    wallet_cents: number;
    next_id: number;
}

const DEMO_PLANS: Plan[] = [
    {
        id: 5,
        name: 'Tempo livre',
        billing: 'open',
        minutes: 0,
        price_cents: 0,
        hourly_rate_cents: 600,
        description: 'Sem limite. Paga só o tempo usado, ao sair.',
        highlight: false,
    },
    {
        id: 1,
        name: 'Rápido',
        billing: 'package',
        minutes: 30,
        price_cents: 500,
        hourly_rate_cents: null,
        description: 'Uma partida ou uma pesquisa rápida.',
        highlight: false,
    },
    {
        id: 2,
        name: '1 hora',
        billing: 'package',
        minutes: 60,
        price_cents: 800,
        hourly_rate_cents: null,
        description: 'O plano mais pedido do salão.',
        highlight: true,
    },
    {
        id: 3,
        name: 'Maratona',
        billing: 'package',
        minutes: 180,
        price_cents: 2000,
        hourly_rate_cents: null,
        description: 'Três horas para ranqueadas e campeonatos.',
        highlight: false,
    },
    {
        id: 4,
        name: 'Noitão',
        billing: 'package',
        minutes: 480,
        price_cents: 3500,
        hourly_rate_cents: null,
        description: 'Das 22h às 6h. Café incluso no balcão.',
        highlight: false,
    },
];

const DEMO_PRODUCTS: Product[] = [
    { id: 1, name: 'Água 500 ml', category: 'Bebidas', price_cents: 300 },
    {
        id: 2,
        name: 'Refrigerante lata',
        category: 'Bebidas',
        price_cents: 550,
    },
    { id: 3, name: 'Energético', category: 'Bebidas', price_cents: 1000 },
    { id: 4, name: 'Café expresso', category: 'Café', price_cents: 450 },
    { id: 5, name: 'Salgado', category: 'Lanches', price_cents: 700 },
    { id: 6, name: 'Chocolate', category: 'Lanches', price_cents: 500 },
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
        wallet_cents: DEMO_STARTING_CREDIT_CENTS,
        next_id: 1,
    };
}

function isToday(iso: string): boolean {
    return new Date(iso).toDateString() === new Date().toDateString();
}

export class DemoApi implements CyberCoffeApi {
    readonly isDemo = true;
    readonly supportsPause = true;
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

        return this.state ?? initialState();
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

    private nextId(): number {
        return this.state.next_id++;
    }

    /** Relê o estado salvo (outra aba pode ter alterado) e expira pacotes vencidos. */
    private sync(): void {
        this.state = this.load();
        const now = Date.now();
        let changed = false;

        for (const session of this.state.sessions) {
            if (
                session.status === 'active' &&
                session.billing === 'package' &&
                session.ends_at !== null &&
                new Date(session.ends_at).getTime() <= now
            ) {
                session.status = 'ended';
                session.closed_at = session.ends_at;
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

    private openSessionFor(stationId: number): Session | undefined {
        return this.state.sessions.find(
            (session) =>
                session.station_id === stationId && session.status !== 'ended',
        );
    }

    private toStation(stored: StoredStation): Station {
        const session = this.openSessionFor(stored.id) ?? null;

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
            session: session
                ? { ...session, credit_cents: this.state.wallet_cents }
                : null,
        };
    }

    private findStation(stationId: number): StoredStation {
        const station = this.state.stations.find(
            (candidate) => candidate.id === stationId,
        );

        if (!station) {
            throw new ApiError('Máquina não encontrada.', 404);
        }

        return station;
    }

    private findSession(sessionId: number): Session {
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

    private debitWallet(cents: number, message: string): void {
        if (this.state.wallet_cents < cents) {
            throw new ApiError(message, 422);
        }

        this.state.wallet_cents -= cents;
    }

    private sessionResponse(session: Session): Promise<ApiEnvelope<Session>> {
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

        return this.envelope(this.toStation(this.findStation(stationId)));
    }

    async listPlans(): Promise<ApiEnvelope<Plan[]>> {
        return this.envelope(DEMO_PLANS);
    }

    async listProducts(): Promise<ApiEnvelope<Product[]>> {
        return this.envelope(DEMO_PRODUCTS);
    }

    async checkin(payload: CheckinPayload): Promise<ApiEnvelope<Session>> {
        this.sync();
        const station = this.findStation(payload.station_id);

        if (
            station.under_maintenance ||
            this.openSessionFor(station.id) !== undefined
        ) {
            throw new ApiError('Essa máquina não está livre agora.', 409);
        }

        const plan = this.findPlan(payload.plan_id);
        const isPackage = plan.billing === 'package';

        if (isPackage && payload.payment_method === 'credit') {
            this.debitWallet(
                plan.price_cents,
                'Crédito insuficiente. Escolha pagar no balcão.',
            );
        }

        const now = new Date();
        const session: Session = {
            id: this.nextId(),
            station_id: station.id,
            plan_id: plan.id,
            customer_name: payload.customer_name?.trim() || null,
            billing: plan.billing,
            hourly_rate_cents: isPackage ? null : plan.hourly_rate_cents,
            status: 'active',
            started_at: now.toISOString(),
            ends_at: isPackage
                ? new Date(now.getTime() + plan.minutes * 60_000).toISOString()
                : null,
            closed_at: null,
            total_seconds: isPackage ? plan.minutes * 60 : 0,
            paused_remaining_seconds: null,
            credit_cents: this.state.wallet_cents,
            time_charged_cents: isPackage ? plan.price_cents : 0,
            items: [],
            prints: [],
            price_per_page_cents: DEMO_PRICE_PER_PAGE_CENTS,
            paid_cents:
                isPackage && payload.payment_method === 'credit'
                    ? plan.price_cents
                    : 0,
        };

        this.state.sessions.push(session);

        return this.sessionResponse(session);
    }

    async pauseSession(sessionId: number): Promise<ApiEnvelope<Session>> {
        this.sync();
        const session = this.findSession(sessionId);

        if (session.billing === 'open') {
            throw new ApiError('Tempo livre não pode ser pausado.', 422);
        }

        if (session.status === 'active' && session.ends_at !== null) {
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

        if (session.billing === 'open' || plan.billing === 'open') {
            throw new ApiError(
                'Só é possível adicionar tempo a um pacote.',
                422,
            );
        }

        if (paymentMethod === 'credit') {
            this.debitWallet(
                plan.price_cents,
                'Crédito insuficiente. Recarregue no balcão.',
            );
            session.paid_cents += plan.price_cents;
        }

        const extraSeconds = plan.minutes * 60;
        session.total_seconds += extraSeconds;
        session.time_charged_cents += plan.price_cents;

        if (session.status === 'paused') {
            session.paused_remaining_seconds =
                (session.paused_remaining_seconds ?? 0) + extraSeconds;
        } else {
            session.ends_at = new Date(
                new Date(session.ends_at ?? Date.now()).getTime() +
                    extraSeconds * 1000,
            ).toISOString();
        }

        return this.sessionResponse(session);
    }

    async endSession(sessionId: number): Promise<ApiEnvelope<Session>> {
        this.sync();
        const session = this.findSession(sessionId);
        const closedAt = new Date().toISOString();

        if (session.billing === 'open' && session.hourly_rate_cents !== null) {
            session.closed_at = closedAt;
            session.time_charged_cents = openTimeCents(
                session.hourly_rate_cents,
                elapsedSeconds(session, Date.now()),
            );
        } else {
            session.closed_at = closedAt;
            session.ends_at = closedAt;
        }

        session.status = 'ended';
        session.paused_remaining_seconds = null;

        return this.sessionResponse(session);
    }

    async addSessionItem(
        sessionId: number,
        item: NewSessionItem,
    ): Promise<ApiEnvelope<Session>> {
        this.sync();
        const session = this.findSession(sessionId);

        if (
            !Number.isInteger(item.quantity) ||
            item.quantity < 1 ||
            item.quantity > MAX_ITEM_QUANTITY
        ) {
            throw new ApiError(
                `Quantidade deve ser de 1 a ${MAX_ITEM_QUANTITY}.`,
                422,
            );
        }

        if ('product_id' in item) {
            const product = DEMO_PRODUCTS.find(
                (candidate) => candidate.id === item.product_id,
            );

            if (!product) {
                throw new ApiError('Produto não encontrado.', 404);
            }

            session.items.push({
                id: this.nextId(),
                product_id: product.id,
                name: product.name,
                quantity: item.quantity,
                unit_price_cents: product.price_cents,
                added_at: new Date().toISOString(),
            });
        } else {
            const name = item.name.trim();

            if (name === '' || item.unit_price_cents <= 0) {
                throw new ApiError(
                    'Informe o nome e um preço maior que zero.',
                    422,
                );
            }

            session.items.push({
                id: this.nextId(),
                product_id: null,
                name: name.slice(0, 60),
                quantity: item.quantity,
                unit_price_cents: item.unit_price_cents,
                added_at: new Date().toISOString(),
            });
        }

        return this.sessionResponse(session);
    }

    async removeSessionItem(
        sessionId: number,
        itemId: number,
    ): Promise<ApiEnvelope<Session>> {
        this.sync();
        const session = this.findSession(sessionId);
        session.items = session.items.filter((item) => item.id !== itemId);

        return this.sessionResponse(session);
    }

    async addPrints(
        sessionId: number,
        pages: number,
    ): Promise<ApiEnvelope<Session>> {
        this.sync();
        const session = this.findSession(sessionId);

        if (
            !Number.isInteger(pages) ||
            pages < 1 ||
            pages > MAX_PAGES_PER_ENTRY
        ) {
            throw new ApiError(
                `Informe de 1 a ${MAX_PAGES_PER_ENTRY} páginas.`,
                422,
            );
        }

        session.prints.push({
            id: this.nextId(),
            pages,
            price_per_page_cents: session.price_per_page_cents,
            added_at: new Date().toISOString(),
        });

        return this.sessionResponse(session);
    }

    async removePrints(
        sessionId: number,
        entryId: number,
    ): Promise<ApiEnvelope<Session>> {
        this.sync();
        const session = this.findSession(sessionId);
        session.prints = session.prints.filter((entry) => entry.id !== entryId);

        return this.sessionResponse(session);
    }

    async setMaintenance(
        stationId: number,
        underMaintenance: boolean,
    ): Promise<ApiEnvelope<Station>> {
        this.sync();
        const station = this.findStation(stationId);

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
        const nowMs = Date.now();
        const today = this.state.sessions.filter((session) =>
            isToday(session.started_at),
        );
        const ended = today.filter((session) => session.status === 'ended');
        const active = this.state.sessions.filter(
            (session) => session.status !== 'ended',
        );
        const openActive = active.filter(
            (session) => session.billing === 'open',
        );

        // Receita = consumo registrado. Tempo livre entra só ao encerrar.
        const time = today.reduce(
            (sum, session) =>
                sum +
                (session.status === 'ended' || session.billing === 'package'
                    ? session.time_charged_cents
                    : 0),
            0,
        );
        const prints = today.reduce(
            (sum, session) => sum + printsCents(session),
            0,
        );
        const products = today.reduce(
            (sum, session) => sum + itemsCents(session),
            0,
        );

        const productTotals = new Map<string, TopProduct>();

        for (const item of today.flatMap((session) => session.items)) {
            const current = productTotals.get(item.name) ?? {
                name: item.name,
                quantity: 0,
                total_cents: 0,
            };
            current.quantity += item.quantity;
            current.total_cents += item.quantity * item.unit_price_cents;
            productTotals.set(item.name, current);
        }

        const usedSeconds = ended.reduce(
            (sum, session) => sum + elapsedSeconds(session, nowMs),
            0,
        );

        return this.envelope({
            revenue_today_cents: time + prints + products,
            revenue_breakdown: {
                time_cents: time,
                prints_cents: prints,
                products_cents: products,
            },
            active_stations: active.length,
            total_stations: this.state.stations.length,
            open_sessions: openActive.length,
            open_sessions_running_cents: openActive.reduce(
                (sum, session) => sum + billTotals(session, nowMs).time,
                0,
            ),
            customers_today: today.length,
            average_session_minutes:
                ended.length > 0
                    ? Math.round(usedSeconds / ended.length / 60)
                    : null,
            pages_printed_today: today.reduce(
                (sum, session) => sum + printedPages(session),
                0,
            ),
            products_sold_today: today.reduce(
                (sum, session) =>
                    sum +
                    session.items.reduce(
                        (count, item) => count + item.quantity,
                        0,
                    ),
                0,
            ),
            top_products: [...productTotals.values()]
                .sort((first, second) => second.quantity - first.quantity)
                .slice(0, 5),
        });
    }

    /** Apaga apenas os dados desta demonstração no navegador. */
    reset(): void {
        this.state = initialState();
        this.save();
    }
}
