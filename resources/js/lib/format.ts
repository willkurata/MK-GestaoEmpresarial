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

export function formatCurrency(value: string | number | null): string {
    return value === null ? '—' : currencyFormatter.format(Number(value));
}

export function formatCompactCurrency(value: string | number): string {
    return compactCurrencyFormatter.format(Number(value));
}

export function formatPercent(value: number | null): string {
    return value === null
        ? '—'
        : `${value.toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`;
}

/** Converte uma data `AAAA-MM-DD` para `DD/MM/AAAA` sem passar pelo fuso do navegador. */
export function formatDate(value: string): string {
    const [year, month, day] = value.split('-');

    return `${day}/${month}/${year}`;
}

export function formatVariation(value: number | null): string {
    if (value === null) {
        return 'Sem base anterior';
    }

    const sign = value > 0 ? '+' : '';

    return `${sign}${value.toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`;
}
