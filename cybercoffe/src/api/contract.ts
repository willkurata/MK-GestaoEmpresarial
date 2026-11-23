import type {
    ApiEnvelope,
    CheckinPayload,
    DashboardMetrics,
    PaymentMethod,
    Plan,
    Session,
    Station,
} from '@cc/types';

/**
 * Operações que o frontend precisa do backend CyberCoffe (WIL-34).
 * Implementada por `HttpApi` (backend real) e `DemoApi` (modo demonstração).
 */
export interface CyberCoffeApi {
    readonly isDemo: boolean;
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
    endSession(sessionId: number): Promise<ApiEnvelope<Session>>;
    setMaintenance(
        stationId: number,
        underMaintenance: boolean,
    ): Promise<ApiEnvelope<Station>>;
    getDashboard(): Promise<ApiEnvelope<DashboardMetrics>>;
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
