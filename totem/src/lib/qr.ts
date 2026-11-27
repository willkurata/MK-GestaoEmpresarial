/**
 * Gerador de QR Code (modo byte, correção de erro M ou L), sem dependências.
 *
 * Usado quando o servidor não manda o SVG pronto (`session_qr.svg` /
 * `payment.qr_svg`) e no modo demonstração. Segue a ISO/IEC 18004 com o
 * mesmo algoritmo da implementação de referência de Project Nayuki.
 */

export type ErrorCorrection = 'L' | 'M';

const ECC_CODEWORDS_PER_BLOCK: Record<ErrorCorrection, number[]> = {
    L: [
        -1, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28,
        30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30,
        30, 30, 30, 30, 30,
    ],
    M: [
        -1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28,
        26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28,
        28, 28, 28, 28, 28,
    ],
};

const NUM_ERROR_CORRECTION_BLOCKS: Record<ErrorCorrection, number[]> = {
    L: [
        -1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9,
        10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25,
    ],
    M: [
        -1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16,
        17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45,
        47, 49,
    ],
};

const FORMAT_BITS: Record<ErrorCorrection, number> = { L: 1, M: 0 };

export interface QrMatrix {
    version: number;
    size: number;
    /** `modules[y][x]` = módulo escuro. */
    modules: boolean[][];
}

function getBit(value: number, index: number): boolean {
    return ((value >>> index) & 1) !== 0;
}

function numRawDataModules(version: number): number {
    let result = (16 * version + 128) * version + 64;

    if (version >= 2) {
        const numAlign = Math.floor(version / 7) + 2;
        result -= (25 * numAlign - 10) * numAlign - 55;

        if (version >= 7) {
            result -= 36;
        }
    }

    return result;
}

function numDataCodewords(version: number, ecl: ErrorCorrection): number {
    return (
        Math.floor(numRawDataModules(version) / 8) -
        ECC_CODEWORDS_PER_BLOCK[ecl][version] *
            NUM_ERROR_CORRECTION_BLOCKS[ecl][version]
    );
}

function gfMultiply(x: number, y: number): number {
    let z = 0;

    for (let i = 7; i >= 0; i--) {
        z = (z << 1) ^ ((z >>> 7) * 0x11d);
        z ^= ((y >>> i) & 1) * x;
    }

    return z;
}

function reedSolomonDivisor(degree: number): number[] {
    const result = new Array<number>(degree).fill(0);
    result[degree - 1] = 1;
    let root = 1;

    for (let i = 0; i < degree; i++) {
        for (let j = 0; j < result.length; j++) {
            result[j] = gfMultiply(result[j], root);

            if (j + 1 < result.length) {
                result[j] ^= result[j + 1];
            }
        }

        root = gfMultiply(root, 0x02);
    }

    return result;
}

function reedSolomonRemainder(data: number[], divisor: number[]): number[] {
    const result = new Array<number>(divisor.length).fill(0);

    for (const byte of data) {
        const factor = byte ^ (result.shift() as number);
        result.push(0);
        divisor.forEach((coefficient, index) => {
            result[index] ^= gfMultiply(coefficient, factor);
        });
    }

    return result;
}

function utf8Bytes(text: string): number[] {
    return Array.from(new TextEncoder().encode(text));
}

function encodeData(
    bytes: number[],
    ecl: ErrorCorrection,
): { version: number; codewords: number[] } {
    let version = 1;

    for (; ; version++) {
        if (version > 40) {
            throw new RangeError('Texto longo demais para um QR Code.');
        }

        const countBits = version <= 9 ? 8 : 16;
        const usedBits = 4 + countBits + bytes.length * 8;

        if (
            bytes.length < 1 << countBits &&
            usedBits <= numDataCodewords(version, ecl) * 8
        ) {
            break;
        }
    }

    const bits: number[] = [];
    const append = (value: number, length: number): void => {
        for (let i = length - 1; i >= 0; i--) {
            bits.push((value >>> i) & 1);
        }
    };

    append(0b0100, 4);
    append(bytes.length, version <= 9 ? 8 : 16);
    bytes.forEach((byte) => append(byte, 8));

    const capacityBits = numDataCodewords(version, ecl) * 8;
    append(0, Math.min(4, capacityBits - bits.length));
    append(0, (8 - (bits.length % 8)) % 8);

    for (let pad = 0xec; bits.length < capacityBits; pad ^= 0xec ^ 0x11) {
        append(pad, 8);
    }

    const codewords: number[] = [];

    for (let i = 0; i < bits.length; i += 8) {
        codewords.push(
            bits.slice(i, i + 8).reduce((byte, bit) => (byte << 1) | bit, 0),
        );
    }

    return { version, codewords };
}

function addEccAndInterleave(
    data: number[],
    version: number,
    ecl: ErrorCorrection,
): number[] {
    const numBlocks = NUM_ERROR_CORRECTION_BLOCKS[ecl][version];
    const blockEccLength = ECC_CODEWORDS_PER_BLOCK[ecl][version];
    const rawCodewords = Math.floor(numRawDataModules(version) / 8);
    const numShortBlocks = numBlocks - (rawCodewords % numBlocks);
    const shortBlockLength = Math.floor(rawCodewords / numBlocks);
    const divisor = reedSolomonDivisor(blockEccLength);
    const blocks: number[][] = [];

    for (let i = 0, offset = 0; i < numBlocks; i++) {
        const block = data.slice(
            offset,
            offset +
                shortBlockLength -
                blockEccLength +
                (i < numShortBlocks ? 0 : 1),
        );
        offset += block.length;
        const ecc = reedSolomonRemainder(block, divisor);

        if (i < numShortBlocks) {
            block.push(0);
        }

        blocks.push(block.concat(ecc));
    }

    const result: number[] = [];

    for (let i = 0; i < blocks[0].length; i++) {
        blocks.forEach((block, j) => {
            if (
                i !== shortBlockLength - blockEccLength ||
                j >= numShortBlocks
            ) {
                result.push(block[i]);
            }
        });
    }

    return result;
}

function alignmentPositions(version: number, size: number): number[] {
    if (version === 1) {
        return [];
    }

    const numAlign = Math.floor(version / 7) + 2;
    const step =
        version === 32
            ? 26
            : Math.ceil((version * 4 + 4) / (numAlign * 2 - 2)) * 2;
    const result = [6];

    for (let position = size - 7; result.length < numAlign; position -= step) {
        result.splice(1, 0, position);
    }

    return result;
}

function maskApplies(mask: number, x: number, y: number): boolean {
    switch (mask) {
        case 0:
            return (x + y) % 2 === 0;
        case 1:
            return y % 2 === 0;
        case 2:
            return x % 3 === 0;
        case 3:
            return (x + y) % 3 === 0;
        case 4:
            return (Math.floor(x / 3) + Math.floor(y / 2)) % 2 === 0;
        case 5:
            return ((x * y) % 2) + ((x * y) % 3) === 0;
        case 6:
            return (((x * y) % 2) + ((x * y) % 3)) % 2 === 0;
        default:
            return (((x + y) % 2) + ((x * y) % 3)) % 2 === 0;
    }
}

/**
 * Penalidade simplificada (regras 1, 2 e 4 da norma). Serve só para escolher
 * entre as 8 máscaras; qualquer máscara gera um QR válido.
 */
function penalty(modules: boolean[][]): number {
    const size = modules.length;
    let score = 0;
    let dark = 0;

    for (let y = 0; y < size; y++) {
        let rowRun = 1;
        let columnRun = 1;

        for (let x = 0; x < size; x++) {
            if (modules[y][x]) {
                dark++;
            }

            if (x > 0) {
                if (modules[y][x] === modules[y][x - 1]) {
                    rowRun++;
                } else {
                    score += rowRun >= 5 ? rowRun - 2 : 0;
                    rowRun = 1;
                }

                if (modules[x][y] === modules[x - 1][y]) {
                    columnRun++;
                } else {
                    score += columnRun >= 5 ? columnRun - 2 : 0;
                    columnRun = 1;
                }
            }

            if (
                x > 0 &&
                y > 0 &&
                modules[y][x] === modules[y][x - 1] &&
                modules[y][x] === modules[y - 1][x] &&
                modules[y][x] === modules[y - 1][x - 1]
            ) {
                score += 3;
            }
        }

        score += rowRun >= 5 ? rowRun - 2 : 0;
        score += columnRun >= 5 ? columnRun - 2 : 0;
    }

    const total = size * size;
    const k = Math.ceil(Math.abs(dark * 20 - total * 10) / total) - 1;

    return score + Math.max(0, k) * 10;
}

export function createQr(text: string, ecl: ErrorCorrection = 'M'): QrMatrix {
    const { version, codewords } = encodeData(utf8Bytes(text), ecl);
    const size = version * 4 + 17;
    const modules = Array.from({ length: size }, () =>
        new Array<boolean>(size).fill(false),
    );
    const isFunction = Array.from({ length: size }, () =>
        new Array<boolean>(size).fill(false),
    );

    const setFunction = (x: number, y: number, dark: boolean): void => {
        modules[y][x] = dark;
        isFunction[y][x] = true;
    };

    for (let i = 0; i < size; i++) {
        setFunction(6, i, i % 2 === 0);
        setFunction(i, 6, i % 2 === 0);
    }

    for (const [cx, cy] of [
        [3, 3],
        [size - 4, 3],
        [3, size - 4],
    ]) {
        for (let dy = -4; dy <= 4; dy++) {
            for (let dx = -4; dx <= 4; dx++) {
                const distance = Math.max(Math.abs(dx), Math.abs(dy));
                const x = cx + dx;
                const y = cy + dy;

                if (x >= 0 && x < size && y >= 0 && y < size) {
                    setFunction(x, y, distance !== 2 && distance !== 4);
                }
            }
        }
    }

    const positions = alignmentPositions(version, size);
    const last = positions.length - 1;

    positions.forEach((px, i) => {
        positions.forEach((py, j) => {
            if (
                (i === 0 && j === 0) ||
                (i === 0 && j === last) ||
                (i === last && j === 0)
            ) {
                return;
            }

            for (let dy = -2; dy <= 2; dy++) {
                for (let dx = -2; dx <= 2; dx++) {
                    setFunction(
                        px + dx,
                        py + dy,
                        Math.max(Math.abs(dx), Math.abs(dy)) !== 1,
                    );
                }
            }
        });
    });

    const drawFormatBits = (mask: number): void => {
        const data = (FORMAT_BITS[ecl] << 3) | mask;
        let remainder = data;

        for (let i = 0; i < 10; i++) {
            remainder = (remainder << 1) ^ ((remainder >>> 9) * 0x537);
        }

        const bits = ((data << 10) | remainder) ^ 0x5412;

        for (let i = 0; i <= 5; i++) {
            setFunction(8, i, getBit(bits, i));
        }

        setFunction(8, 7, getBit(bits, 6));
        setFunction(8, 8, getBit(bits, 7));
        setFunction(7, 8, getBit(bits, 8));

        for (let i = 9; i < 15; i++) {
            setFunction(14 - i, 8, getBit(bits, i));
        }

        for (let i = 0; i < 8; i++) {
            setFunction(size - 1 - i, 8, getBit(bits, i));
        }

        for (let i = 8; i < 15; i++) {
            setFunction(8, size - 15 + i, getBit(bits, i));
        }

        setFunction(8, size - 8, true);
    };

    drawFormatBits(0);

    if (version >= 7) {
        let remainder = version;

        for (let i = 0; i < 12; i++) {
            remainder = (remainder << 1) ^ ((remainder >>> 11) * 0x1f25);
        }

        const bits = (version << 12) | remainder;

        for (let i = 0; i < 18; i++) {
            const bit = getBit(bits, i);
            const a = size - 11 + (i % 3);
            const b = Math.floor(i / 3);
            setFunction(a, b, bit);
            setFunction(b, a, bit);
        }
    }

    const data = addEccAndInterleave(codewords, version, ecl);
    let bitIndex = 0;

    for (let right = size - 1; right >= 1; right -= 2) {
        if (right === 6) {
            right = 5;
        }

        for (let vertical = 0; vertical < size; vertical++) {
            for (let j = 0; j < 2; j++) {
                const x = right - j;
                const upward = ((right + 1) & 2) === 0;
                const y = upward ? size - 1 - vertical : vertical;

                if (!isFunction[y][x] && bitIndex < data.length * 8) {
                    modules[y][x] = getBit(
                        data[bitIndex >>> 3],
                        7 - (bitIndex & 7),
                    );
                    bitIndex++;
                }
            }
        }
    }

    const applyMask = (mask: number): void => {
        for (let y = 0; y < size; y++) {
            for (let x = 0; x < size; x++) {
                if (!isFunction[y][x] && maskApplies(mask, x, y)) {
                    modules[y][x] = !modules[y][x];
                }
            }
        }
    };

    let bestMask = 0;
    let bestPenalty = Infinity;

    for (let mask = 0; mask < 8; mask++) {
        applyMask(mask);
        drawFormatBits(mask);
        const score = penalty(modules);

        if (score < bestPenalty) {
            bestMask = mask;
            bestPenalty = score;
        }

        applyMask(mask);
    }

    applyMask(bestMask);
    drawFormatBits(bestMask);

    return { version, size, modules };
}

/** Caminho SVG (um retângulo 1×1 por módulo escuro), com margem `quiet`. */
export function qrPath(matrix: QrMatrix, quiet = 4): string {
    const parts: string[] = [];

    matrix.modules.forEach((row, y) => {
        row.forEach((dark, x) => {
            if (dark) {
                parts.push(`M${x + quiet} ${y + quiet}h1v1h-1z`);
            }
        });
    });

    return parts.join('');
}
