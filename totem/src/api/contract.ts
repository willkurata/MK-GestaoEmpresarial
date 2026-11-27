import type {
    Order,
    OrderHandle,
    PrintOptions,
    TotemDisplay,
    UploadProgress,
} from '@totem/types';

/**
 * Operações que o frontend do totem precisa do backend (totem-back).
 * Implementada por `HttpApi` (servidor real) e `DemoApi` (modo demonstração).
 *
 * Endpoints propostos, prefixo `/api/totem/v1`:
 *   GET    /totems/{code}/display          tela do totem (sem auth; só leitura)
 *   POST   /totems/{code}/sessions         { token } → { order_id, order_token }
 *   GET    /orders/{id}                    pedido (header X-Order-Token)
 *   POST   /orders/{id}/file               multipart "file"
 *   PATCH  /orders/{id}                    { options }
 *   POST   /orders/{id}/checkout           gera cobrança Pix
 *   POST   /orders/{id}/cancel
 */
export interface TotemApi {
    readonly isDemo: boolean;
    getDisplay(totemCode: string): Promise<TotemDisplay>;
    /** Valida o token do QR (prova de presença) e abre um pedido em rascunho. */
    openSession(totemCode: string, qrToken: string): Promise<OrderHandle>;
    getOrder(handle: OrderHandle): Promise<Order>;
    uploadFile(
        handle: OrderHandle,
        file: File,
        onProgress?: (progress: UploadProgress) => void,
    ): Promise<Order>;
    updateOptions(handle: OrderHandle, options: PrintOptions): Promise<Order>;
    checkout(handle: OrderHandle): Promise<Order>;
    cancel(handle: OrderHandle): Promise<Order>;
}

export class ApiError extends Error {
    readonly status: number;

    constructor(message: string, status: number) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
    }
}
