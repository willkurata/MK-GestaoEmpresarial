import type { PrintOptions, PriceQuote, Pricing } from '../types.ts';

export type PageRangeResult =
    | { ok: true; pages: number[] }
    | { ok: false; message: string };

/**
 * Interpreta um intervalo de páginas como "1-3, 5, 8-".
 * Vazio ou `null` = todas. Páginas repetidas contam uma vez, em ordem.
 */
export function parsePageRange(
    range: string | null,
    pageCount: number,
): PageRangeResult {
    const all = Array.from({ length: pageCount }, (_, index) => index + 1);
    const text = (range ?? '').trim();

    if (text === '') {
        return { ok: true, pages: all };
    }

    const selected = new Set<number>();

    for (const rawPart of text.split(/[,;]/)) {
        const part = rawPart.trim();

        if (part === '') {
            continue;
        }

        const match = /^(\d+)?\s*(?:(-)\s*(\d+)?)?$/.exec(part);

        if (!match || (match[1] === undefined && match[3] === undefined)) {
            return { ok: false, message: `Não entendi "${part}".` };
        }

        const start = match[1] === undefined ? 1 : Number(match[1]);
        const end = match[2]
            ? match[3] === undefined
                ? pageCount
                : Number(match[3])
            : start;

        if (start < 1 || end < 1) {
            return { ok: false, message: 'As páginas começam em 1.' };
        }

        if (start > end) {
            return { ok: false, message: `"${part}" está ao contrário.` };
        }

        if (end > pageCount) {
            return {
                ok: false,
                message: `O arquivo tem ${pageCount} ${pageCount === 1 ? 'página' : 'páginas'}.`,
            };
        }

        for (let page = start; page <= end; page++) {
            selected.add(page);
        }
    }

    if (selected.size === 0) {
        return { ok: false, message: 'Escolha ao menos uma página.' };
    }

    return { ok: true, pages: all.filter((page) => selected.has(page)) };
}

/**
 * Prévia do preço, com a mesma regra do servidor (que é quem cobra):
 * faces = ⌈páginas ÷ páginas por folha⌉; total = faces × cópias × preço/face;
 * folhas = frente e verso ? ⌈faces ÷ 2⌉ : faces (por cópia, somadas).
 */
export function quotePrice(
    options: PrintOptions,
    pageCount: number,
    pricing: Pricing,
): PriceQuote | null {
    const range = parsePageRange(options.page_range, pageCount);

    if (!range.ok || options.copies < 1) {
        return null;
    }

    const pages = range.pages.length;
    const sides = Math.ceil(pages / options.pages_per_sheet);
    const sheetsPerCopy = options.duplex ? Math.ceil(sides / 2) : sides;
    const perSide: number | null =
        options.color === 'color'
            ? pricing.color_per_side_cents
            : pricing.bw_per_side_cents;

    if (perSide === null) {
        return null;
    }

    return {
        pages,
        sides,
        sheets: sheetsPerCopy * options.copies,
        copies: options.copies,
        per_side_cents: perSide,
        total_cents: sides * options.copies * perSide,
    };
}

export function clampCopies(value: number, max: number): number {
    if (!Number.isFinite(value)) {
        return 1;
    }

    return Math.min(Math.max(Math.round(value), 1), max);
}
