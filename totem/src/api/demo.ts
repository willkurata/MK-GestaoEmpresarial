import type { TotemApi } from '@totem/api/contract';
import { ApiError } from '@totem/api/contract';
import { extensionOf, randomPickupCode } from '@totem/lib/format';
import { parsePageRange, quotePrice } from '@totem/lib/pricing';
import type {
    Order,
    OrderHandle,
    PrinterReason,
    PrinterStatus,
    PrintOptions,
    Pricing,
    QueueEntry,
    TotemDisplay,
    UploadProgress,
} from '@totem/types';

/**
 * Modo demonstração: simula o backend no navegador.
 *
 * O estado fica no `localStorage`, então a tela do totem e a página do
 * celular abertas em abas do MESMO navegador enxergam os mesmos pedidos.
 * Um celular de verdade não alcança esse estado; para isso é preciso o
 * backend real (`VITE_TOTEM_API_URL`).
 *
 * A evolução do pedido é calculada pelo relógio (pago → fila → imprimindo
 * → pronto), então todas as abas mostram o mesmo estado sem coordenação.
 */

const STORAGE_KEY = 'totem.demo.v1';
const PRINTER_KEY = 'totem.demo.printer';
const QR_WINDOW_MS = 60_000;
const PAYMENT_AUTO_CONFIRM_MS = 8_000;
const PAYMENT_TTL_MS = 10 * 60_000;
const WARMUP_MS = 3_000;
const MS_PER_SHEET = 2_500;
const READY_VISIBLE_MS = 15 * 60_000;
const PURGE_AFTER_MS = 10 * 60_000;

export const DEMO_PRICING: Pricing = {
    currency: 'BRL',
    bw_per_side_cents: 50,
    color_per_side_cents: 150,
    max_copies: 50,
    max_file_mb: 25,
    accepted_extensions: ['pdf', 'jpg', 'jpeg', 'png'],
};

export type DemoPrinterScenario =
    | 'ok'
    | 'low'
    | 'no-paper'
    | 'jam'
    | 'offline';

export const DEMO_PRINTER_SCENARIOS: Record<DemoPrinterScenario, string> = {
    ok: 'Normal',
    low: 'Papel e toner baixos',
    'no-paper': 'Sem papel',
    jam: 'Papel atolado',
    offline: 'Desconectada',
};

interface StoredOrder {
    id: string;
    token: string;
    pickup_code: string;
    totem_code: string;
    created_at: string;
    canceled_at: string | null;
    file: Order['file'];
    options: PrintOptions;
    checkout_at: string | null;
}

interface DemoState {
    orders: StoredOrder[];
}

function readState(): DemoState {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);

        if (raw) {
            return JSON.parse(raw) as DemoState;
        }
    } catch {
        // Armazenamento bloqueado ou corrompido: começa vazio.
    }

    return { orders: [] };
}

function writeState(state: DemoState): void {
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    } catch {
        // Sem armazenamento: o demo segue só nesta aba.
    }
}

export function readDemoPrinter(): DemoPrinterScenario {
    try {
        const value = window.localStorage.getItem(PRINTER_KEY);

        if (value && value in DEMO_PRINTER_SCENARIOS) {
            return value as DemoPrinterScenario;
        }
    } catch {
        // ignora
    }

    return 'ok';
}

export function setDemoPrinter(scenario: DemoPrinterScenario): void {
    try {
        window.localStorage.setItem(PRINTER_KEY, scenario);
    } catch {
        // ignora
    }
}

export function resetDemo(): void {
    writeState({ orders: [] });
}

function printerFor(scenario: DemoPrinterScenario): PrinterStatus {
    const reasons: Record<DemoPrinterScenario, PrinterReason[]> = {
        ok: [],
        low: ['media-low', 'toner-low'],
        'no-paper': ['media-empty'],
        jam: ['media-jam'],
        offline: [],
    };

    return {
        state:
            scenario === 'offline'
                ? 'offline'
                : scenario === 'no-paper' || scenario === 'jam'
                  ? 'stopped'
                  : 'idle',
        reasons: reasons[scenario],
        paper_level:
            scenario === 'no-paper' ? 0 : scenario === 'low' ? 12 : 78,
        markers: [{ color: 'black', level: scenario === 'low' ? 8 : 64 }],
        supports_color: true,
        supports_duplex: true,
        last_seen_at: scenario === 'offline' ? null : new Date().toISOString(),
    };
}

function qrWindow(nowMs: number): number {
    return Math.floor(nowMs / QR_WINDOW_MS);
}

function qrTokenFor(slot: number): string {
    return slot.toString(36);
}

function randomId(): string {
    return Math.random().toString(36).slice(2, 10);
}

function sheetsTotal(order: StoredOrder): number {
    const pages = order.file?.page_count;

    if (!pages) {
        return 0;
    }

    return quotePrice(order.options, pages, DEMO_PRICING)?.sheets ?? 0;
}

/**
 * Deriva o estado de todos os pedidos pelo relógio. Pedidos pagos entram
 * na fila em ordem de pagamento e imprimem um de cada vez.
 */
function deriveOrders(orders: StoredOrder[], nowMs: number): Order[] {
    const paidAt = (order: StoredOrder): number | null =>
        order.checkout_at === null || order.canceled_at !== null
            ? null
            : new Date(order.checkout_at).getTime() + PAYMENT_AUTO_CONFIRM_MS;

    const schedule = new Map<string, { start: number; end: number }>();
    let printerFreeAt = 0;

    [...orders]
        .filter((order) => paidAt(order) !== null)
        .sort((a, b) => (paidAt(a) as number) - (paidAt(b) as number))
        .forEach((order) => {
            const start = Math.max(paidAt(order) as number, printerFreeAt);
            const end = start + WARMUP_MS + sheetsTotal(order) * MS_PER_SHEET;
            schedule.set(order.id, { start, end });
            printerFreeAt = end;
        });

    return orders.map((stored): Order => {
        const quote = stored.file?.page_count
            ? quotePrice(stored.options, stored.file.page_count, DEMO_PRICING)
            : null;
        const base: Order = {
            id: stored.id,
            pickup_code: stored.pickup_code,
            totem_code: stored.totem_code,
            status: 'draft',
            file: stored.file,
            options: stored.options,
            quote,
            payment: null,
            progress: null,
            failure_reason: null,
            created_at: stored.created_at,
            ready_at: null,
            file_purge_at: null,
        };

        if (stored.canceled_at !== null) {
            return { ...base, status: 'canceled' };
        }

        if (stored.checkout_at === null || quote === null) {
            return base;
        }

        const checkoutMs = new Date(stored.checkout_at).getTime();
        const confirmMs = checkoutMs + PAYMENT_AUTO_CONFIRM_MS;
        const isPaid = nowMs >= confirmMs;

        base.payment = {
            copy_paste: demoPixCode(stored, quote.total_cents),
            qr_svg: null,
            amount_cents: quote.total_cents,
            expires_at: new Date(checkoutMs + PAYMENT_TTL_MS).toISOString(),
            paid_at: isPaid ? new Date(confirmMs).toISOString() : null,
            refunded_at: null,
        };

        if (!isPaid) {
            return { ...base, status: 'awaiting_payment' };
        }

        const slot = schedule.get(stored.id) as { start: number; end: number };
        const total = Math.max(1, quote.sheets);

        if (nowMs < slot.start) {
            return {
                ...base,
                status: 'queued',
                progress: { sheets_done: 0, sheets_total: total },
            };
        }

        if (nowMs < slot.end) {
            const done = Math.floor(
                Math.max(0, nowMs - slot.start - WARMUP_MS) / MS_PER_SHEET,
            );

            return {
                ...base,
                status: 'printing',
                progress: {
                    sheets_done: Math.min(done, total),
                    sheets_total: total,
                },
            };
        }

        return {
            ...base,
            status: 'ready',
            progress: { sheets_done: total, sheets_total: total },
            ready_at: new Date(slot.end).toISOString(),
            file_purge_at: new Date(slot.end + PURGE_AFTER_MS).toISOString(),
        };
    });
}

/** BR Code de mentira, marcado como demonstração. Não paga nada. */
function demoPixCode(order: StoredOrder, cents: number): string {
    const amount = (cents / 100).toFixed(2);

    return `00020126360014br.gov.bcb.pix0114DEMONSTRACAO52040000530398654${String(amount.length).padStart(2, '0')}${amount}5802BR5917TOTEM PRINT DEMO6009SAO PAULO62070503${order.pickup_code}6304DEMO`;
}

async function countPdfPages(file: File): Promise<number> {
    const text = await file.text();
    const matches = text.match(/\/Type\s*\/Page(?![a-zA-Z])/g);

    return Math.max(1, matches?.length ?? 1);
}

function wait(ms: number): Promise<void> {
    return new Promise((resolve) => window.setTimeout(resolve, ms));
}

export class DemoApi implements TotemApi {
    readonly isDemo = true;

    private find(handle: OrderHandle): StoredOrder {
        const stored = readState().orders.find(
            (order) =>
                order.id === handle.order_id &&
                order.token === handle.order_token,
        );

        if (!stored) {
            throw new ApiError('Pedido não encontrado.', 404);
        }

        return stored;
    }

    private save(updated: StoredOrder): Order {
        const state = readState();
        state.orders = state.orders.map((order) =>
            order.id === updated.id ? updated : order,
        );
        writeState(state);

        return this.derive(updated.id);
    }

    private derive(orderId: string): Order {
        return deriveOrders(readState().orders, Date.now()).find(
            (order) => order.id === orderId,
        ) as Order;
    }

    async getDisplay(totemCode: string): Promise<TotemDisplay> {
        await wait(120);
        const now = Date.now();
        const orders = deriveOrders(
            readState().orders.filter(
                (order) => order.totem_code === totemCode,
            ),
            now,
        );
        const queue: QueueEntry[] = orders
            .filter(
                (order) =>
                    order.status === 'queued' ||
                    order.status === 'printing' ||
                    (order.status === 'ready' &&
                        order.ready_at !== null &&
                        now - new Date(order.ready_at).getTime() <
                            READY_VISIBLE_MS),
            )
            .map((order) => ({
                pickup_code: order.pickup_code,
                status: order.status as QueueEntry['status'],
                progress: order.progress,
            }));
        const slot = qrWindow(now);
        const base = `${location.origin}${location.pathname}`;

        return {
            totem: {
                code: totemCode,
                name: `Totem ${totemCode}`,
                location: 'Demonstração',
                help_contact: '(11) 0000-0000',
            },
            printer: printerFor(readDemoPrinter()),
            queue,
            pricing: DEMO_PRICING,
            session_qr: {
                url: `${base}#/t/${encodeURIComponent(totemCode)}?s=${qrTokenFor(slot)}`,
                expires_at: new Date((slot + 1) * QR_WINDOW_MS).toISOString(),
                svg: null,
            },
            server_time: new Date(now).toISOString(),
        };
    }

    async openSession(
        totemCode: string,
        qrToken: string,
    ): Promise<OrderHandle> {
        await wait(250);
        const slot = qrWindow(Date.now());
        const valid = [slot, slot - 1].map(qrTokenFor);

        if (!valid.includes(qrToken)) {
            throw new ApiError(
                'Este QR Code expirou. Escaneie de novo o QR da tela do totem.',
                410,
            );
        }

        const scenario = readDemoPrinter();

        if (scenario !== 'ok' && scenario !== 'low') {
            throw new ApiError(
                'O totem está pausado agora. Tente em alguns minutos.',
                503,
            );
        }

        const order: StoredOrder = {
            id: randomId(),
            token: randomId() + randomId(),
            pickup_code: randomPickupCode(),
            totem_code: totemCode,
            created_at: new Date().toISOString(),
            canceled_at: null,
            file: null,
            options: {
                color: 'bw',
                duplex: false,
                pages_per_sheet: 1,
                page_range: null,
                copies: 1,
            },
            checkout_at: null,
        };
        const state = readState();
        state.orders.push(order);
        writeState(state);

        return { order_id: order.id, order_token: order.token };
    }

    async getOrder(handle: OrderHandle): Promise<Order> {
        await wait(80);
        this.find(handle);

        return this.derive(handle.order_id);
    }

    async uploadFile(
        handle: OrderHandle,
        file: File,
        onProgress?: (progress: UploadProgress) => void,
    ): Promise<Order> {
        const stored = this.find(handle);

        if (stored.checkout_at !== null) {
            throw new ApiError('Este pedido já foi enviado para pagamento.', 409);
        }

        const extension = extensionOf(file.name);

        if (!DEMO_PRICING.accepted_extensions.includes(extension)) {
            throw new ApiError('Envie um PDF, JPG ou PNG.', 422);
        }

        if (file.size > DEMO_PRICING.max_file_mb * 1024 * 1024) {
            throw new ApiError(
                `O arquivo passa de ${DEMO_PRICING.max_file_mb} MB.`,
                422,
            );
        }

        const steps = 12;

        for (let step = 1; step <= steps; step++) {
            await wait(90);
            onProgress?.({
                loaded: Math.round((file.size * step) / steps),
                total: file.size,
            });
        }

        const pages = extension === 'pdf' ? await countPdfPages(file) : 1;
        await wait(500);

        return this.save({
            ...stored,
            file: {
                name: file.name,
                size_bytes: file.size,
                mime: file.type || 'application/octet-stream',
                page_count: pages,
                converting: false,
                thumbnail_url: null,
            },
            options: { ...stored.options, page_range: null },
        });
    }

    async updateOptions(
        handle: OrderHandle,
        options: PrintOptions,
    ): Promise<Order> {
        await wait(60);
        const stored = this.find(handle);

        if (stored.checkout_at !== null || stored.canceled_at !== null) {
            throw new ApiError('Não dá mais para mudar este pedido.', 409);
        }

        if (stored.file?.page_count) {
            const range = parsePageRange(
                options.page_range,
                stored.file.page_count,
            );

            if (!range.ok) {
                throw new ApiError(range.message, 422);
            }
        }

        return this.save({ ...stored, options });
    }

    async checkout(handle: OrderHandle): Promise<Order> {
        await wait(400);
        const stored = this.find(handle);
        const order = this.derive(stored.id);

        if (order.status !== 'draft') {
            throw new ApiError('Este pedido já foi para pagamento.', 409);
        }

        if (!order.quote) {
            throw new ApiError('Envie um arquivo antes de pagar.', 422);
        }

        return this.save({ ...stored, checkout_at: new Date().toISOString() });
    }

    async cancel(handle: OrderHandle): Promise<Order> {
        await wait(150);
        const stored = this.find(handle);
        const order = this.derive(stored.id);

        if (order.status !== 'draft' && order.status !== 'awaiting_payment') {
            throw new ApiError(
                'O pagamento já foi confirmado; o pedido segue para impressão.',
                409,
            );
        }

        return this.save({ ...stored, canceled_at: new Date().toISOString() });
    }
}
