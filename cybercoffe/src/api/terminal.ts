import { ApiError } from '@cc/api/contract';
import type { DemoApi } from '@cc/api/demo';
import type { BackendSession, BackendStation } from '@cc/api/http';
import { mapSession, request, STATION_STATUS, toCents } from '@cc/api/http';
import type { TerminalSnapshot } from '@cc/lib/terminal';
import { isValidPhone, normalizePhone } from '@cc/lib/terminal';

/**
 * Programa cliente da máquina (WIL-49). Fala com o backend por dois tokens:
 *
 * - token da estação (`POST stations/{id}/agent-token`, emitido pelo gerente)
 *   para `agent/heartbeat`, que diz se a máquina está liberada;
 * - token do cliente (`POST auth/customers/login`) para ler nome e saldo em
 *   `me`. Fica só em memória: a máquina é compartilhada, então recarregar a
 *   tela ou bloquear a máquina desconecta o cliente.
 */

export interface TerminalConfig {
    /** Código da loja, usado no login do cliente. */
    store_code: string;
    /** Token da estação (backend real). */
    agent_token: string;
    /** Estação simulada (modo demonstração). */
    station_id: number | null;
}

export interface CustomerProfile {
    name: string;
    phone: string;
    credit_cents: number;
}

export interface TerminalApi {
    readonly isDemo: boolean;
    heartbeat(): Promise<TerminalSnapshot>;
    customerLogin(phone: string, password: string): Promise<CustomerProfile>;
    customerProfile(): Promise<CustomerProfile>;
    customerLogout(): Promise<void>;
}

const CONFIG_STORAGE_KEY = 'cybercoffe.terminal';

export function readTerminalConfig(): TerminalConfig | null {
    try {
        const raw = window.localStorage.getItem(CONFIG_STORAGE_KEY);

        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw) as Partial<TerminalConfig>;

        return {
            store_code: parsed.store_code ?? '',
            agent_token: parsed.agent_token ?? '',
            station_id: parsed.station_id ?? null,
        };
    } catch {
        return null;
    }
}

export function saveTerminalConfig(config: TerminalConfig): void {
    try {
        window.localStorage.setItem(CONFIG_STORAGE_KEY, JSON.stringify(config));
    } catch {
        // Sem armazenamento a configuração vale só até recarregar a tela.
    }
}

export function isTerminalConfigured(
    config: TerminalConfig | null,
    isDemo: boolean,
): config is TerminalConfig {
    if (!config) {
        return false;
    }

    return isDemo
        ? config.station_id !== null
        : config.store_code.trim() !== '' && config.agent_token.trim() !== '';
}

interface BackendHeartbeat {
    station: { id: number; name: string; status: BackendStation['status'] };
    locked: boolean;
    session: BackendSession | null;
    server_time: string;
}

interface BackendCustomer {
    name: string;
    phone: string;
    credit_balance: string;
}

function mapCustomer(customer: BackendCustomer): CustomerProfile {
    return {
        name: customer.name,
        phone: customer.phone,
        credit_cents: toCents(customer.credit_balance),
    };
}

export class HttpTerminalApi implements TerminalApi {
    readonly isDemo = false;
    private customerToken: string | null = null;

    constructor(
        private readonly baseUrl: string,
        private readonly config: TerminalConfig,
    ) {}

    async heartbeat(): Promise<TerminalSnapshot> {
        const { payload } = await request<BackendHeartbeat>(
            this.baseUrl,
            'POST',
            '/agent/heartbeat',
            {
                token: this.config.agent_token,
                unauthorizedMessage:
                    'Token da estação inválido. Peça ao gerente um novo.',
            },
        );

        return {
            station: {
                id: payload.station.id,
                name: payload.station.name,
                status: STATION_STATUS[payload.station.status] ?? 'offline',
            },
            locked: payload.locked,
            session: payload.session ? mapSession(payload.session) : null,
            server_time: payload.server_time,
        };
    }

    async customerLogin(
        phone: string,
        password: string,
    ): Promise<CustomerProfile> {
        const { payload } = await request<{
            token: string;
            customer: BackendCustomer;
        }>(this.baseUrl, 'POST', '/auth/customers/login', {
            token: null,
            body: {
                store_code: this.config.store_code,
                phone: normalizePhone(phone),
                password,
            },
            unauthorizedMessage: 'Telefone ou senha incorretos.',
        });

        this.customerToken = payload.token;

        return mapCustomer(payload.customer);
    }

    async customerProfile(): Promise<CustomerProfile> {
        if (!this.customerToken) {
            throw new ApiError('Entre com seu telefone e senha.', 401);
        }

        const { payload } = await request<{ data: BackendCustomer }>(
            this.baseUrl,
            'GET',
            '/me',
            {
                token: this.customerToken,
                unauthorizedMessage: 'Sua conta foi desconectada.',
            },
        );

        return mapCustomer(payload.data);
    }

    async customerLogout(): Promise<void> {
        const token = this.customerToken;
        this.customerToken = null;

        if (token) {
            // Se a rede falhar, o token expira sozinho no servidor.
            await request(this.baseUrl, 'POST', '/me/logout', {
                token,
                unauthorizedMessage: '',
            }).catch(() => undefined);
        }
    }
}

/**
 * Demonstração: a máquina reflete a estação escolhida no modo demo do
 * painel (mesmo `localStorage`), e qualquer telefone válido entra.
 */
export class DemoTerminalApi implements TerminalApi {
    readonly isDemo = true;
    private customer: { name: string; phone: string } | null = null;

    constructor(
        private readonly demo: DemoApi,
        private readonly config: TerminalConfig,
    ) {}

    async heartbeat(): Promise<TerminalSnapshot> {
        const { data: station, server_time } = await this.demo.getStation(
            this.config.station_id ?? 0,
        );

        return {
            station: {
                id: station.id,
                name: station.name,
                status: station.status,
            },
            locked: station.session === null,
            session: station.session,
            server_time,
        };
    }

    async customerLogin(
        phone: string,
        password: string,
    ): Promise<CustomerProfile> {
        if (!isValidPhone(phone) || password === '') {
            throw new ApiError('Telefone ou senha incorretos.', 422);
        }

        this.customer = {
            name: 'Cliente demonstração',
            phone: normalizePhone(phone),
        };

        return this.customerProfile();
    }

    async customerProfile(): Promise<CustomerProfile> {
        if (!this.customer) {
            throw new ApiError('Entre com seu telefone e senha.', 401);
        }

        return { ...this.customer, credit_cents: this.demo.walletCents() };
    }

    async customerLogout(): Promise<void> {
        this.customer = null;
    }
}
