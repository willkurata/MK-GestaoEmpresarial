import type { CyberCoffeApi } from '@cc/api/contract';
import { DemoApi } from '@cc/api/demo';
import { HttpApi } from '@cc/api/http';
import type { TerminalApi, TerminalConfig } from '@cc/api/terminal';
import { DemoTerminalApi, HttpTerminalApi } from '@cc/api/terminal';

export { ApiError } from '@cc/api/contract';
export type { CyberCoffeApi } from '@cc/api/contract';

const apiUrl = import.meta.env.VITE_CYBERCOFFE_API_URL?.trim();

/**
 * Com `VITE_CYBERCOFFE_API_URL` definido, fala com o backend real (WIL-34).
 * Sem ele, usa o modo demonstração no navegador.
 */
const demoApi = apiUrl ? null : new DemoApi();

export const api: CyberCoffeApi = demoApi ?? new HttpApi(apiUrl ?? '');

/** API do programa cliente da máquina (WIL-49) para a configuração dada. */
export function createTerminalApi(config: TerminalConfig): TerminalApi {
    return demoApi
        ? new DemoTerminalApi(demoApi, config)
        : new HttpTerminalApi(apiUrl ?? '', config);
}

export function errorMessage(error: unknown): string {
    return error instanceof Error
        ? error.message
        : 'Algo deu errado. Tente de novo.';
}
