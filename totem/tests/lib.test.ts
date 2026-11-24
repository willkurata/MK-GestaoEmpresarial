/**
 * Testes das regras puras do frontend do totem.
 * Rodar: node --experimental-strip-types --test totem/tests/
 */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { PICKUP_ALPHABET, randomPickupCode } from '../src/lib/format.ts';
import { summarizePrinter } from '../src/lib/printer.ts';
import { clampCopies, parsePageRange, quotePrice } from '../src/lib/pricing.ts';
import { createQr } from '../src/lib/qr.ts';
import type { PrinterStatus, PrintOptions, Pricing } from '../src/types.ts';

const pricing: Pricing = {
    currency: 'BRL',
    bw_per_side_cents: 50,
    color_per_side_cents: 150,
    max_copies: 50,
    max_file_mb: 25,
    accepted_extensions: ['pdf', 'jpg', 'jpeg', 'png'],
};

const baseOptions: PrintOptions = {
    color: 'bw',
    duplex: false,
    pages_per_sheet: 1,
    page_range: null,
    copies: 1,
};

describe('parsePageRange', () => {
    it('uses every page when empty', () => {
        assert.deepEqual(parsePageRange(null, 3), { ok: true, pages: [1, 2, 3] });
        assert.deepEqual(parsePageRange('  ', 2), { ok: true, pages: [1, 2] });
    });

    it('accepts lists, ranges and open ends, without duplicates', () => {
        assert.deepEqual(parsePageRange('5, 1-3, 2', 6), {
            ok: true,
            pages: [1, 2, 3, 5],
        });
        assert.deepEqual(parsePageRange('4-', 6), { ok: true, pages: [4, 5, 6] });
        assert.deepEqual(parsePageRange('-2', 6), { ok: true, pages: [1, 2] });
    });

    it('rejects pages beyond the document, reversed ranges and garbage', () => {
        assert.equal(parsePageRange('1-9', 3).ok, false);
        assert.equal(parsePageRange('3-1', 3).ok, false);
        assert.equal(parsePageRange('0', 3).ok, false);
        assert.equal(parsePageRange('abc', 3).ok, false);
        assert.equal(parsePageRange(',', 3).ok, false);
    });
});

describe('quotePrice', () => {
    it('charges per printed side times copies', () => {
        const quote = quotePrice({ ...baseOptions, copies: 3 }, 5, pricing);

        assert.deepEqual(quote, {
            pages: 5,
            sides: 5,
            sheets: 15,
            copies: 3,
            per_side_cents: 50,
            total_cents: 750,
        });
    });

    it('duplex saves sheets but not sides; pages per sheet saves sides', () => {
        const duplex = quotePrice({ ...baseOptions, duplex: true }, 5, pricing);
        assert.equal(duplex?.sides, 5);
        assert.equal(duplex?.sheets, 3);
        assert.equal(duplex?.total_cents, 250);

        const fourUp = quotePrice(
            { ...baseOptions, pages_per_sheet: 4, color: 'color' },
            9,
            pricing,
        );
        assert.equal(fourUp?.sides, 3);
        assert.equal(fourUp?.total_cents, 450);
    });

    it('respects the page range and refuses invalid input', () => {
        assert.equal(
            quotePrice({ ...baseOptions, page_range: '2-3' }, 10, pricing)
                ?.total_cents,
            100,
        );
        assert.equal(
            quotePrice({ ...baseOptions, page_range: '20' }, 10, pricing),
            null,
        );
        assert.equal(quotePrice({ ...baseOptions, copies: 0 }, 10, pricing), null);
    });

    it('clamps copies to 1..max', () => {
        assert.equal(clampCopies(0, 50), 1);
        assert.equal(clampCopies(99, 50), 50);
        assert.equal(clampCopies(Number.NaN, 50), 1);
        assert.equal(clampCopies(2.6, 50), 3);
    });
});

describe('pickup code', () => {
    it('has 3 characters and never uses O, 0, I or 1', () => {
        assert.doesNotMatch(PICKUP_ALPHABET, /[O0I1]/);

        for (let i = 0; i < 200; i++) {
            assert.match(randomPickupCode(), /^[A-HJ-NP-Z2-9]{3}$/);
        }
    });
});

describe('summarizePrinter', () => {
    const printer = (overrides: Partial<PrinterStatus>): PrinterStatus => ({
        state: 'idle',
        reasons: [],
        paper_level: 80,
        markers: [],
        supports_color: true,
        supports_duplex: true,
        last_seen_at: null,
        ...overrides,
    });

    it('accepts orders when healthy and warns on low supplies', () => {
        assert.equal(summarizePrinter(printer({})).health, 'ok');

        const low = summarizePrinter(printer({ reasons: ['toner-low'] }));
        assert.equal(low.health, 'warning');
        assert.equal(low.acceptingOrders, true);
    });

    it('pauses new orders when out of paper, jammed, stopped or offline', () => {
        for (const status of [
            printer({ reasons: ['media-empty'] }),
            printer({ reasons: ['media-jam'] }),
            printer({ state: 'stopped' }),
            printer({ state: 'offline' }),
        ]) {
            const summary = summarizePrinter(status);
            assert.equal(summary.acceptingOrders, false);
            assert.equal(summary.health, 'blocked');
        }

        assert.equal(
            summarizePrinter(printer({ reasons: ['media-empty'] })).title,
            'Sem papel',
        );
    });
});

describe('createQr', () => {
    function finderAt(modules: boolean[][], x: number, y: number): boolean {
        for (let dy = 0; dy < 7; dy++) {
            for (let dx = 0; dx < 7; dx++) {
                const ring = Math.min(dx, dy, 6 - dx, 6 - dy);
                const expected = ring !== 1;

                if (modules[y + dy][x + dx] !== expected) {
                    return false;
                }
            }
        }

        return true;
    }

    it('picks the smallest version that fits and grows with the text', () => {
        assert.equal(createQr('A').version, 1);
        assert.equal(createQr('A'.repeat(14)).version, 1);
        assert.equal(createQr('A'.repeat(15)).version, 2);
        assert.ok(createQr('x'.repeat(300)).version >= 10);
        assert.throws(() => createQr('x'.repeat(5000)), RangeError);
    });

    it('draws the three finder patterns and the dark module', () => {
        const { modules, size } = createQr(
            'https://totem.exemplo.com.br/#/t/LOJA01?s=abc',
        );

        assert.equal(modules.length, size);
        assert.ok(finderAt(modules, 0, 0));
        assert.ok(finderAt(modules, size - 7, 0));
        assert.ok(finderAt(modules, 0, size - 7));
        assert.equal(modules[size - 8][8], true);
    });

    it('writes a valid BCH-protected format word for level M', () => {
        const { modules } = createQr('totem');
        let bits = 0;

        // Leitura da cópia ao redor do finder superior esquerdo (bit 14 → 0).
        const coordinates: [number, number][] = [
            [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8],
            [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8],
        ];
        coordinates.forEach(([x, y], index) => {
            if (modules[y][x]) {
                bits |= 1 << index;
            }
        });

        const unmasked = bits ^ 0x5412;
        const data = unmasked >>> 10;
        let remainder = data;

        for (let i = 0; i < 10; i++) {
            remainder = (remainder << 1) ^ ((remainder >>> 9) * 0x537);
        }

        assert.equal(unmasked & 0x3ff, remainder & 0x3ff);
        assert.equal(data >>> 3, 0, 'nível de correção M = 00');
    });
});
