import type { CyberCoffeApi } from '@cc/api/contract';
import { DemoApi } from '@cc/api/demo';
import { HttpApi } from '@cc/api/http';

export { ApiError } from '@cc/api/contract';
export type { CyberCoffeApi } from '@cc/api/contract';

const apiUrl = import.meta.env.VITE_CYBERCOFFE_API_URL?.trim();

/**
 * Com `VITE_CYBERCOFFE_API_URL` definido, fala com o backend real (WIL-34).
 * Sem ele, usa o modo demonstração no navegador.
 */
export const api: CyberCoffeApi = apiUrl ? new HttpApi(apiUrl) : new DemoApi();

export function errorMessage(error: unknown): string {
    return error instanceof Error
        ? error.message
        : 'Algo deu errado. Tente de novo.';
}
