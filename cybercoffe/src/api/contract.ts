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
} from '@cc/types';

/**
 * Operações que o frontend precisa do backend CyberCoffe (WIL-34/WIL-36).
 * Implementada por `HttpApi` (backend real) e `DemoApi` (modo demonstração).
 */
export interface CyberCoffeApi {
    readonly isDemo: boolean;
    /** O backend real (WIL-34) ainda não tem pausa de sessão. */
    readonly supportsPause: boolean;
    listStations(): Promise<ApiEnvelope<Station[]>>;
    getStation(stationId: number): Promise<ApiEnvelope<Station>>;
    listPlans(): Promise<ApiEnvelope<Plan[]>>;
    checkin(payload: CheckinPayload): Promise<ApiEnvelope<Session>>;
    pauseSession(sessionId: number): Promise<ApiEnvelope<Session>>;
    resumeSession(sessionId: number): Promise<ApiEnvelope<Session>>;
    /**
     * Adiciona o tempo de um plano. `credit` debita da carteira do cliente;
     * `counter` registra pagamento no balcão (Pix manual ou dinheiro).
     */
    extendSession(
        sessionId: number,
        planId: number,
        paymentMethod: PaymentMethod,
    ): Promise<ApiEnvelope<Session>>;
    /**
     * Encerra e libera a máquina. Devolve a sessão fechada com a conta final
     * (no tempo livre, o servidor calcula o valor pelo tempo usado).
     */
    endSession(sessionId: number): Promise<ApiEnvelope<Session>>;
    setMaintenance(
        stationId: number,
        underMaintenance: boolean,
    ): Promise<ApiEnvelope<Station>>;
    getDashboard(): Promise<ApiEnvelope<DashboardMetrics>>;

    /** Catálogo de produtos avulsos ativos da loja (WIL-39). */
    listProducts(): Promise<ApiEnvelope<Product[]>>;
    addSessionItem(
        sessionId: number,
        item: NewSessionItem,
    ): Promise<ApiEnvelope<Session>>;
    removeSessionItem(
        sessionId: number,
        itemId: number,
    ): Promise<ApiEnvelope<Session>>;
    /** Lança páginas impressas na conta da sessão (WIL-38). */
    addPrints(sessionId: number, pages: number): Promise<ApiEnvelope<Session>>;
    removePrints(
        sessionId: number,
        entryId: number,
    ): Promise<ApiEnvelope<Session>>;
}

export class ApiError extends Error {
    constructor(
        message: string,
        public readonly status: number,
    ) {
        super(message);
        this.name = 'ApiError';
    }
}
