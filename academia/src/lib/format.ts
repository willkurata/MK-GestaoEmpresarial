const currencyFormatter = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

const compactCurrencyFormatter = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    notation: 'compact',
    maximumFractionDigits: 1,
});

export function formatCurrency(amountInCents: number): string {
    return currencyFormatter.format(amountInCents / 100);
}

export function formatCompactCurrency(amountInCents: number): string {
    return compactCurrencyFormatter.format(amountInCents / 100);
}

export function formatTime(iso: string): string {
    return new Date(iso).toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function toDate(isoOrDate: string): Date {
    return isoOrDate.length === 10
        ? parseLocalDate(isoOrDate)
        : new Date(isoOrDate);
}

export function formatShortDate(isoOrDate: string): string {
    return toDate(isoOrDate).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: 'short',
    });
}

export function formatWeekday(isoOrDate: string): string {
    return toDate(isoOrDate)
        .toLocaleDateString('pt-BR', { weekday: 'short' })
        .replace('.', '');
}

export function firstName(fullName: string): string {
    return fullName.split(' ')[0] ?? fullName;
}

export function initials(fullName: string): string {
    const parts = fullName.trim().split(/\s+/);
    const first = parts[0]?.[0] ?? '';
    const last = parts.length > 1 ? (parts[parts.length - 1]?.[0] ?? '') : '';

    return (first + last).toUpperCase();
}

/** Converte "YYYY-MM-DD" em Date no fuso local (sem deslocamento de UTC). */
export function parseLocalDate(value: string): Date {
    const [year, month, day] = value.split('-').map(Number);

    return new Date(year ?? 1970, (month ?? 1) - 1, day ?? 1);
}

export function toLocalDateString(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

/** Diferença em dias inteiros (b − a), ignorando horário. */
export function daysBetween(a: Date, b: Date): number {
    const start = new Date(a.getFullYear(), a.getMonth(), a.getDate());
    const end = new Date(b.getFullYear(), b.getMonth(), b.getDate());

    return Math.round((end.getTime() - start.getTime()) / 86_400_000);
}
