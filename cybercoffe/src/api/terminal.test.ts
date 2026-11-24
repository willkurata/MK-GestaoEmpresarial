import { HttpTerminalApi } from '@cc/api/terminal';
import { afterEach, describe, expect, it, vi } from 'vitest';

const config = {
    store_code: 'lan-centro',
    agent_token: 'station-token',
    station_id: null,
};

function respond(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

afterEach(() => vi.unstubAllGlobals());

describe('HttpTerminalApi', () => {
    it('faz heartbeat com o token da estação e traduz a sessão', async () => {
        const fetchMock = vi.fn().mockResolvedValue(
            respond({
                station: { id: 3, name: 'PC 03', status: 'ocupada' },
                locked: false,
                session: {
                    id: 9,
                    station_id: 3,
                    billing: 'avulso',
                    hourly_rate: '6.00',
                    status: 'ativa',
                    started_at: '2026-11-23T19:00:00Z',
                    ends_at: null,
                    closed_at: null,
                    elapsed_seconds: 3600,
                    remaining_seconds: null,
                    amount_due: '6.00',
                    amount_paid: '0.00',
                    customer_credit_balance: '12.50',
                },
                server_time: '2026-11-23T20:00:00Z',
            }),
        );
        vi.stubGlobal('fetch', fetchMock);

        const snapshot = await new HttpTerminalApi(
            'http://lan.test/',
            config,
        ).heartbeat();

        const [url, init] = fetchMock.mock.calls[0];
        expect(url).toBe('http://lan.test/api/cybercoffe/v1/agent/heartbeat');
        expect(init.method).toBe('POST');
        expect(init.headers.Authorization).toBe('Bearer station-token');
        expect(snapshot.station.status).toBe('in_use');
        expect(snapshot.session).toMatchObject({
            billing: 'open',
            hourly_rate_cents: 600,
            // Tempo livre ativo: o valor final só existe no encerramento.
            time_charged_cents: 0,
            credit_cents: 1250,
        });
    });

    it('login do cliente usa a loja e o telefone normalizado; /me usa o token do cliente', async () => {
        const customer = {
            name: 'Ana',
            phone: '11912345678',
            credit_balance: '30.00',
        };
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(
                respond({ token: 'customer-token', customer }),
            )
            .mockResolvedValueOnce(
                respond({ data: { ...customer, credit_balance: '25.00' } }),
            );
        vi.stubGlobal('fetch', fetchMock);
        const terminal = new HttpTerminalApi('http://lan.test', config);

        const profile = await terminal.customerLogin(
            '(11) 91234-5678',
            'segredo',
        );
        const refreshed = await terminal.customerProfile();

        expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({
            store_code: 'lan-centro',
            phone: '11912345678',
            password: 'segredo',
        });
        expect(
            fetchMock.mock.calls[0][1].headers.Authorization,
        ).toBeUndefined();
        expect(profile.credit_cents).toBe(3000);
        expect(fetchMock.mock.calls[1][0]).toBe(
            'http://lan.test/api/cybercoffe/v1/me',
        );
        expect(fetchMock.mock.calls[1][1].headers.Authorization).toBe(
            'Bearer customer-token',
        );
        expect(refreshed.credit_cents).toBe(2500);
    });

    it('mostra o erro de validação do servidor no login', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(
                respond(
                    {
                        message: 'invalid',
                        errors: { phone: ['Credenciais inválidas.'] },
                    },
                    422,
                ),
            ),
        );

        await expect(
            new HttpTerminalApi('http://lan.test', config).customerLogin(
                '11912345678',
                'errada',
            ),
        ).rejects.toThrow('Credenciais inválidas.');
    });

    it('depois de sair, não lê mais o perfil', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(
                respond({
                    token: 'customer-token',
                    customer: {
                        name: 'Ana',
                        phone: '11912345678',
                        credit_balance: '0',
                    },
                }),
            )
            .mockResolvedValueOnce(respond({ message: 'Sessão encerrada.' }));
        vi.stubGlobal('fetch', fetchMock);
        const terminal = new HttpTerminalApi('http://lan.test', config);

        await terminal.customerLogin('11912345678', 'segredo');
        await terminal.customerLogout();

        expect(fetchMock.mock.calls[1][0]).toBe(
            'http://lan.test/api/cybercoffe/v1/me/logout',
        );
        await expect(terminal.customerProfile()).rejects.toThrow();
    });
});
