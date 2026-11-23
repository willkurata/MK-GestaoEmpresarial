import type { StationStatus, StationTier } from '@cc/types';

const currency = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

export function formatMoney(cents: number): string {
    return currency.format(cents / 100);
}

/**
 * Converte o texto digitado ("3,50", "R$ 12", "1.234,5") em centavos.
 * Retorna `null` quando não é um valor positivo válido.
 */
export function parseMoney(input: string): number | null {
    const cleaned = input.replace(/[^\d,.]/g, '');

    if (cleaned === '') {
        return null;
    }

    const lastSeparator = Math.max(
        cleaned.lastIndexOf(','),
        cleaned.lastIndexOf('.'),
    );
    const hasDecimals =
        lastSeparator !== -1 && cleaned.length - lastSeparator - 1 <= 2;
    const normalized = hasDecimals
        ? `${cleaned.slice(0, lastSeparator).replace(/[,.]/g, '')}.${cleaned.slice(lastSeparator + 1)}`
        : cleaned.replace(/[,.]/g, '');
    const value = Math.round(Number(normalized) * 100);

    return Number.isFinite(value) && value > 0 ? value : null;
}

export function formatRate(hourlyRateCents: number): string {
    return `${formatMoney(hourlyRateCents)}/h`;
}

export function formatClock(totalSeconds: number): string {
    const safe = Math.max(0, Math.floor(totalSeconds));
    const hours = Math.floor(safe / 3600);
    const minutes = Math.floor((safe % 3600) / 60);
    const seconds = safe % 60;

    return [hours, minutes, seconds]
        .map((part) => String(part).padStart(2, '0'))
        .join(':');
}

export function formatDuration(minutes: number): string {
    if (minutes < 60) {
        return `${minutes} min`;
    }

    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    return rest === 0 ? `${hours} h` : `${hours} h ${rest} min`;
}

export const STATUS_LABEL: Record<StationStatus | 'ending', string> = {
    free: 'Livre',
    in_use: 'Em uso',
    ending: 'Acabando',
    paused: 'Pausada',
    reserved: 'Reservada',
    maintenance: 'Manutenção',
    offline: 'Offline',
};

export const TIER_LABEL: Record<StationTier, string> = {
    gamer: 'Gamer',
    standard: 'Padrão',
    console: 'Console',
};
