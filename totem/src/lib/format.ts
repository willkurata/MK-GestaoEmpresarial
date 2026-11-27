const currency = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

export function formatMoney(cents: number): string {
    return currency.format(cents / 100);
}

export function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1).replace('.', ',')} MB`;
}

export function plural(count: number, one: string, many: string): string {
    return `${count} ${count === 1 ? one : many}`;
}

/** Segundos até `iso`, nunca negativo. */
export function secondsUntil(iso: string, nowMs: number): number {
    return Math.max(0, Math.ceil((new Date(iso).getTime() - nowMs) / 1000));
}

export function formatCountdown(totalSeconds: number): string {
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    return `${minutes}:${String(seconds).padStart(2, '0')}`;
}

/** Alfabeto do código de retirada: sem O/0, I/1, para não confundir. */
export const PICKUP_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

export function randomPickupCode(random: () => number = Math.random): string {
    let code = '';

    for (let index = 0; index < 3; index++) {
        code += PICKUP_ALPHABET[Math.floor(random() * PICKUP_ALPHABET.length)];
    }

    return code;
}

export function extensionOf(fileName: string): string {
    const dot = fileName.lastIndexOf('.');

    return dot === -1 ? '' : fileName.slice(dot + 1).toLowerCase();
}
