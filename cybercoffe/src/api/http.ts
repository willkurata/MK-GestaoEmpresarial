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
} from '@cc/types';

const TOKEN_STORAGE_KEY = 'cybercoffe.token';

function readToken(): string | null {
    try {
        return window.localStorage.getItem(TOKEN_STORAGE_KEY);
    } catch {
        return null;
    }
}

/**
 * Cliente REST para `/api/v1` (contrato proposto no WIL-34).
 * Autenticação: cookie de sessão (painel) ou token Sanctum Bearer (máquina/totem).
 */
export class HttpApi implements CyberCoffeApi {
    readonly isDemo = false;

    constructor(private readonly baseUrl: string) {}

    private async request<T>(
        method: string,
        path: string,
        body?: unknown,
    ): Promise<ApiEnvelope<T>> {
        const headers: Record<string, string> = {
            Accept: 'application/json',
        };
        const token = readToken();

        if (token) {
            headers.Authorization = `Bearer ${token}`;
        }

        if (body !== undefined) {
            headers['Content-Type'] = 'application/json';
        }

        let response: Response;

        try {
            response = await fetch(
                `${this.baseUrl.replace(/\/$/, '')}/api/v1${path}`,
                {
                    method,
                    headers,
                    credentials: 'include',
                    body: body === undefined ? undefined : JSON.stringify(body),
                },
            );
        } catch {
            throw new ApiError(
                'Sem conexão com o servidor. Verifique a rede.',
                0,
            );
        }

        const payload = (await response.json().catch(() => null)) as
            | (Partial<ApiEnvelope<T>> & { message?: string })
            | null;

        if (!response.ok || !payload || payload.data === undefined) {
            throw new ApiError(
                payload?.message ?? `Erro ${response.status} no servidor.`,
                response.status,
            );
        }

        return {
            data: payload.data,
            server_time: payload.server_time ?? new Date().toISOString(),
        };
    }

    listStations(): Promise<ApiEnvelope<Station[]>> {
        return this.request('GET', '/stations');
    }

    getStation(stationId: number): Promise<ApiEnvelope<Station>> {
        return this.request('GET', `/stations/${stationId}`);
    }

    listPlans(): Promise<ApiEnvelope<Plan[]>> {
        return this.request('GET', '/plans');
    }

    checkin(payload: CheckinPayload): Promise<ApiEnvelope<Session>> {
        return this.request('POST', '/sessions', payload);
    }

    pauseSession(sessionId: number): Promise<ApiEnvelope<Session>> {
        return this.request('POST', `/sessions/${sessionId}/pause`);
    }

    resumeSession(sessionId: number): Promise<ApiEnvelope<Session>> {
        return this.request('POST', `/sessions/${sessionId}/resume`);
    }

    extendSession(
        sessionId: number,
        planId: number,
        paymentMethod: PaymentMethod,
    ): Promise<ApiEnvelope<Session>> {
        return this.request('POST', `/sessions/${sessionId}/extend`, {
            plan_id: planId,
            payment_method: paymentMethod,
        });
    }

    endSession(sessionId: number): Promise<ApiEnvelope<Session>> {
        return this.request('POST', `/sessions/${sessionId}/end`);
    }

    setMaintenance(
        stationId: number,
        underMaintenance: boolean,
    ): Promise<ApiEnvelope<Station>> {
        return this.request('PATCH', `/stations/${stationId}`, {
            status: underMaintenance ? 'maintenance' : 'free',
        });
    }

    getDashboard(): Promise<ApiEnvelope<DashboardMetrics>> {
        return this.request('GET', '/admin/dashboard');
    }
}
