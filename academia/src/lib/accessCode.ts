/**
 * QR de acesso rotativo. O código muda a cada janela (padrão 30 s), o que
 * impede reaproveitar print ou compartilhar a imagem. Na produção o segredo
 * vem do servidor (TOTP); aqui fica a lógica de interface.
 */
export const ACCESS_CODE_WINDOW_SECONDS = 30;

export function accessCodeWindow(
    nowMs: number,
    windowSeconds = ACCESS_CODE_WINDOW_SECONDS,
): number {
    return Math.floor(nowMs / 1000 / windowSeconds);
}

export function secondsUntilRotation(
    nowMs: number,
    windowSeconds = ACCESS_CODE_WINDOW_SECONDS,
): number {
    const elapsed = Math.floor(nowMs / 1000) % windowSeconds;

    return windowSeconds - elapsed;
}

/** Hash FNV-1a de 32 bits — determinístico e suficiente para a interface. */
export function accessCode(secret: string, window: number): string {
    let hash = 0x811c9dc5;
    const input = `${secret}:${window}`;

    for (let index = 0; index < input.length; index++) {
        hash ^= input.charCodeAt(index);
        hash = Math.imul(hash, 0x01000193);
    }

    return (hash >>> 0).toString(16).padStart(8, '0').toUpperCase();
}

/**
 * Matriz de módulos (true = escuro) gerada a partir do código. É um padrão
 * visual ilustrativo, não um QR Code ISO legível por câmera — a geração real
 * fica a cargo da biblioteca escolhida junto com o backend.
 */
export function codeMatrix(code: string, size = 21): boolean[][] {
    let seed = parseInt(code.slice(0, 8), 16) || 1;
    const next = (): number => {
        seed ^= seed << 13;
        seed ^= seed >>> 17;
        seed ^= seed << 5;

        return (seed >>> 0) / 0xffffffff;
    };

    const matrix = Array.from({ length: size }, () =>
        Array.from({ length: size }, () => next() > 0.52),
    );

    const setCell = (row: number, col: number, value: boolean): void => {
        if (row >= 0 && row < size && col >= 0 && col < size) {
            matrix[row]![col] = value;
        }
    };

    const drawFinder = (top: number, left: number): void => {
        for (let row = -1; row <= 7; row++) {
            for (let col = -1; col <= 7; col++) {
                const inside = row >= 0 && row <= 6 && col >= 0 && col <= 6;
                const isBorder =
                    inside &&
                    (row === 0 || row === 6 || col === 0 || col === 6);
                const isCore = row >= 2 && row <= 4 && col >= 2 && col <= 4;
                setCell(top + row, left + col, isBorder || isCore);
            }
        }
    };

    drawFinder(0, 0);
    drawFinder(0, size - 7);
    drawFinder(size - 7, 0);

    return matrix;
}
