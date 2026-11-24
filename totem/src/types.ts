/**
 * Tipos do Totem de Impressão Autônoma 24h (WIL-47).
 *
 * Dinheiro sempre em centavos inteiros no frontend; o adaptador HTTP converte
 * o formato do backend. Datas em ISO 8601 (UTC).
 */

export type ColorMode = 'bw' | 'color';
export type PagesPerSheet = 1 | 2 | 4;

export interface PrintOptions {
    color: ColorMode;
    duplex: boolean;
    pages_per_sheet: PagesPerSheet;
    /** Ex.: "1-3, 5". `null` = todas as páginas. */
    page_range: string | null;
    copies: number;
}

export interface Pricing {
    currency: 'BRL';
    /** Preço por face impressa (lado de folha). */
    bw_per_side_cents: number;
    color_per_side_cents: number;
    max_copies: number;
    max_file_mb: number;
    accepted_extensions: string[];
}

/**
 * Ciclo do pedido. Só o servidor muda o estado; o frontend apenas exibe.
 *  draft → awaiting_payment → queued → printing → ready
 *  e as saídas failed | expired | canceled | refunded
 *  (refunded = falhou depois de pago e o Pix foi estornado).
 */
export type OrderStatus =
    | 'draft'
    | 'awaiting_payment'
    | 'queued'
    | 'printing'
    | 'ready'
    | 'failed'
    | 'expired'
    | 'canceled'
    | 'refunded';

export interface OrderFile {
    name: string;
    size_bytes: number;
    mime: string;
    /** `null` enquanto o servidor converte para PDF. */
    page_count: number | null;
    converting: boolean;
    /** Miniatura da 1ª página (PNG/JPEG) quando o servidor gerar. */
    thumbnail_url: string | null;
}

export interface PixPayment {
    /** Código "copia e cola" (BR Code). */
    copy_paste: string;
    /** QR do Pix já renderizado pelo servidor; se vier `null`, o front gera. */
    qr_svg: string | null;
    amount_cents: number;
    expires_at: string;
    paid_at: string | null;
    refunded_at: string | null;
}

export interface PriceQuote {
    pages: number;
    sides: number;
    sheets: number;
    copies: number;
    per_side_cents: number;
    total_cents: number;
}

export interface PrintProgress {
    sheets_done: number;
    sheets_total: number;
}

export interface Order {
    id: string;
    /** Código de retirada, 3 caracteres sem O/0/I/1 (ex.: A7K). */
    pickup_code: string;
    totem_code: string;
    status: OrderStatus;
    file: OrderFile | null;
    options: PrintOptions;
    quote: PriceQuote | null;
    payment: PixPayment | null;
    progress: PrintProgress | null;
    /** Mensagem amigável quando `failed` (ex.: atolamento → crédito). */
    failure_reason: string | null;
    created_at: string;
    ready_at: string | null;
    /** Quando o arquivo será apagado (LGPD). */
    file_purge_at: string | null;
}

/** Credencial do pedido devolvida ao abrir a sessão pelo QR. */
export interface OrderHandle {
    order_id: string;
    order_token: string;
}

/* ---------------------------- Impressora ---------------------------- */

/** Espelha `printer-state` do IPP. */
export type PrinterState = 'idle' | 'processing' | 'stopped' | 'offline';

/** Subconjunto de `printer-state-reasons` do IPP que a tela trata. */
export type PrinterReason =
    | 'media-empty'
    | 'media-low'
    | 'media-jam'
    | 'toner-empty'
    | 'toner-low'
    | 'door-open'
    | 'cover-open'
    | 'other';

export interface MarkerLevel {
    /** Ex.: "black", "cyan". */
    color: string;
    /** 0–100, ou `null` se a impressora não informar. */
    level: number | null;
}

export interface PrinterStatus {
    state: PrinterState;
    reasons: PrinterReason[];
    /** 0–100, ou `null` se não houver sensor. */
    paper_level: number | null;
    markers: MarkerLevel[];
    supports_color: boolean;
    supports_duplex: boolean;
    last_seen_at: string | null;
}

/* ------------------------------ Totem ------------------------------- */

export interface QueueEntry {
    pickup_code: string;
    status: Extract<OrderStatus, 'queued' | 'printing' | 'ready'>;
    progress: PrintProgress | null;
}

export interface SessionQr {
    /** URL completa que o QR codifica (página mobile com token). */
    url: string;
    expires_at: string;
    /** SVG pronto vindo do servidor (BaconQrCode); `null` = front gera. */
    svg: string | null;
}

export interface TotemDisplay {
    totem: {
        code: string;
        name: string;
        location: string;
        help_contact: string;
    };
    printer: PrinterStatus;
    queue: QueueEntry[];
    pricing: Pricing;
    session_qr: SessionQr;
    server_time: string;
}

export interface UploadProgress {
    loaded: number;
    total: number;
}
