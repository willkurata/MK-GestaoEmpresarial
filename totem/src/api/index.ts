import type { TotemApi } from '@totem/api/contract';
import { DemoApi } from '@totem/api/demo';
import { HttpApi } from '@totem/api/http';
import type { OrderHandle } from '@totem/types';

export { ApiError } from '@totem/api/contract';
export type { TotemApi } from '@totem/api/contract';

const apiUrl = import.meta.env.VITE_TOTEM_API_URL?.trim();

/**
 * Com `VITE_TOTEM_API_URL` definido, fala com o backend real (totem-back).
 * Sem ele, usa o modo demonstração no navegador.
 */
export const api: TotemApi = apiUrl ? new HttpApi(apiUrl) : new DemoApi();

export function errorMessage(error: unknown): string {
    return error instanceof Error
        ? error.message
        : 'Algo deu errado. Tente de novo.';
}

/* Credenciais dos pedidos deste aparelho (conveniência local; o servidor
 * continua exigindo o token). Sem armazenamento, vale só nesta aba. */

const HANDLES_KEY = 'totem.orders';
const memoryHandles = new Map<string, string>();

function readHandles(): Record<string, string> {
    try {
        return JSON.parse(
            window.localStorage.getItem(HANDLES_KEY) ?? '{}',
        ) as Record<string, string>;
    } catch {
        return Object.fromEntries(memoryHandles);
    }
}

export function rememberHandle(handle: OrderHandle): void {
    memoryHandles.set(handle.order_id, handle.order_token);

    try {
        const handles = readHandles();
        handles[handle.order_id] = handle.order_token;
        window.localStorage.setItem(HANDLES_KEY, JSON.stringify(handles));
    } catch {
        // fica só em memória
    }
}

export function recallHandle(orderId: string): OrderHandle | null {
    const token = memoryHandles.get(orderId) ?? readHandles()[orderId];

    return token ? { order_id: orderId, order_token: token } : null;
}
