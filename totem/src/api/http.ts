// Imports relativos para o mapeamento rodar nos testes do Node.
import type { TotemApi } from './contract.ts';
import { ApiError } from './contract.ts';
import type {
    Order,
    OrderHandle,
    PrintOptions,
    Pricing,
    TotemDisplay,
    UploadProgress,
} from '../types.ts';

/* ------------------------------------------------------------------------
 * Formato do backend (totem-back, WIL-46). Dinheiro vem como decimal em
 * string ("1.50"); respostas embrulhadas em { data } (API Resources).
 * Erros: 422 { message, errors }, 404 token errado, 409 transição
 * inválida, 410 QR expirado, 429 limite de tentativas.
 * --------------------------------------------------------------------- */

type Decimal = string | number | null | undefined;

interface BackendQuote {
    pages: number;
    sides: number;
    sheets: number;
    copies: number;
    per_side: Decimal;
    total: Decimal;
}

interface BackendPayment {
    /** `null` depois que a cobrança deixa de estar pendente. */
    copy_paste: string | null;
    qr_svg: string | null;
    amount: Decimal;
    expires_at: string;
    paid_at: string | null;
    refunded_at?: string | null;
}

type BackendOrder = Omit<Order, 'quote' | 'payment'> & {
    quote: BackendQuote | null;
    payment: BackendPayment | null;
};

type BackendPricing = Omit<
    Pricing,
    'currency' | 'bw_per_side_cents' | 'color_per_side_cents'
> & {
    bw_per_side: Decimal;
    color_per_side: Decimal;
};

type BackendDisplay = Omit<TotemDisplay, 'pricing'> & {
    pricing: BackendPricing;
};

export function toCents(value: Decimal): number {
    return value === null || value === undefined
        ? 0
        : Math.round(Number(value) * 100);
}

export function mapOrder(order: BackendOrder): Order {
    return {
        ...order,
        quote: order.quote
            ? {
                  pages: order.quote.pages,
                  sides: order.quote.sides,
                  sheets: order.quote.sheets,
                  copies: order.quote.copies,
                  per_side_cents: toCents(order.quote.per_side),
                  total_cents: toCents(order.quote.total),
              }
            : null,
        payment: order.payment
            ? {
                  copy_paste: order.payment.copy_paste ?? '',
                  qr_svg: order.payment.qr_svg,
                  amount_cents: toCents(order.payment.amount),
                  expires_at: order.payment.expires_at,
                  paid_at: order.payment.paid_at,
                  refunded_at: order.payment.refunded_at ?? null,
              }
            : null,
    };
}

export function mapDisplay(display: BackendDisplay): TotemDisplay {
    const { bw_per_side, color_per_side, ...pricing } = display.pricing;

    return {
        ...display,
        pricing: {
            ...pricing,
            currency: 'BRL',
            bw_per_side_cents: toCents(bw_per_side),
            // Sem colorido o backend manda `null`; não pode virar "R$ 0,00".
            color_per_side_cents:
                color_per_side === null || color_per_side === undefined
                    ? null
                    : toCents(color_per_side),
        },
    };
}

interface ErrorPayload {
    message?: string;
    errors?: Record<string, string[]>;
}

function errorFrom(status: number, payload: ErrorPayload | null): ApiError {
    const firstError = payload?.errors
        ? Object.values(payload.errors)[0]?.[0]
        : undefined;
    const fallback: Record<number, string> = {
        404: 'Pedido não encontrado. Escaneie o QR do totem de novo.',
        409: 'Este pedido já mudou de etapa. Atualizando…',
        410: 'Este QR Code expirou. Escaneie de novo o QR da tela do totem.',
        413: 'Arquivo grande demais.',
        429: 'Muitas tentativas. Espere um pouco e tente de novo.',
    };

    return new ApiError(
        firstError ??
            fallback[status] ??
            payload?.message ??
            `Erro ${status} no servidor.`,
        status,
    );
}

/**
 * Cliente REST para `/api/totem/v1`. O pedido é autenticado pelo
 * `order_token` recebido ao abrir a sessão, no header `X-Order-Token`.
 */
export class HttpApi implements TotemApi {
    readonly isDemo = false;

    private readonly baseUrl: string;

    constructor(baseUrl: string) {
        this.baseUrl = baseUrl;
    }

    private url(path: string): string {
        return `${this.baseUrl.replace(/\/$/, '')}/api/totem/v1${path}`;
    }

    private async request<T>(
        method: string,
        path: string,
        options: { body?: unknown; handle?: OrderHandle } = {},
    ): Promise<T> {
        const headers: Record<string, string> = { Accept: 'application/json' };

        if (options.handle) {
            headers['X-Order-Token'] = options.handle.order_token;
        }

        if (options.body !== undefined) {
            headers['Content-Type'] = 'application/json';
        }

        let response: Response;

        try {
            response = await fetch(this.url(path), {
                method,
                headers,
                body:
                    options.body === undefined
                        ? undefined
                        : JSON.stringify(options.body),
            });
        } catch {
            throw new ApiError('Sem conexão. Verifique a internet.', 0);
        }

        const payload = (await response.json().catch(() => null)) as
            | ({ data: T } & ErrorPayload)
            | null;

        if (!response.ok || payload === null) {
            throw errorFrom(response.status, payload);
        }

        return payload.data;
    }

    private async order(
        method: string,
        path: string,
        handle: OrderHandle,
        body?: unknown,
    ): Promise<Order> {
        return mapOrder(
            await this.request<BackendOrder>(method, path, { handle, body }),
        );
    }

    async getDisplay(totemCode: string): Promise<TotemDisplay> {
        return mapDisplay(
            await this.request<BackendDisplay>(
                'GET',
                `/totems/${encodeURIComponent(totemCode)}/display`,
            ),
        );
    }

    openSession(totemCode: string, qrToken: string): Promise<OrderHandle> {
        return this.request<OrderHandle>(
            'POST',
            `/totems/${encodeURIComponent(totemCode)}/sessions`,
            { body: { token: qrToken } },
        );
    }

    getOrder(handle: OrderHandle): Promise<Order> {
        return this.order('GET', `/orders/${handle.order_id}`, handle);
    }

    /** XHR em vez de fetch para ter progresso real de envio. */
    uploadFile(
        handle: OrderHandle,
        file: File,
        onProgress?: (progress: UploadProgress) => void,
    ): Promise<Order> {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            const form = new FormData();
            form.append('file', file);

            xhr.open('POST', this.url(`/orders/${handle.order_id}/file`));
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Order-Token', handle.order_token);
            xhr.upload.onprogress = (event) => {
                if (event.lengthComputable) {
                    onProgress?.({ loaded: event.loaded, total: event.total });
                }
            };
            xhr.onerror = () =>
                reject(new ApiError('O envio caiu. Tente de novo.', 0));
            xhr.onload = () => {
                let payload: ({ data: BackendOrder } & ErrorPayload) | null =
                    null;

                try {
                    payload = JSON.parse(xhr.responseText);
                } catch {
                    payload = null;
                }

                if (xhr.status >= 200 && xhr.status < 300 && payload) {
                    resolve(mapOrder(payload.data));
                } else {
                    reject(errorFrom(xhr.status, payload));
                }
            };
            xhr.send(form);
        });
    }

    updateOptions(handle: OrderHandle, options: PrintOptions): Promise<Order> {
        return this.order('PATCH', `/orders/${handle.order_id}`, handle, {
            options,
        });
    }

    checkout(handle: OrderHandle): Promise<Order> {
        return this.order(
            'POST',
            `/orders/${handle.order_id}/checkout`,
            handle,
        );
    }

    cancel(handle: OrderHandle): Promise<Order> {
        return this.order('POST', `/orders/${handle.order_id}/cancel`, handle);
    }
}
