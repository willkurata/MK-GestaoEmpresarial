import type { PrinterReason, PrinterStatus } from '../types.ts';

export type PrinterHealth = 'ok' | 'warning' | 'blocked';

export interface PrinterSummary {
    health: PrinterHealth;
    /** Aceita pedidos novos (o QR aparece no totem). */
    acceptingOrders: boolean;
    title: string;
    detail: string | null;
}

const BLOCKING: PrinterReason[] = [
    'media-empty',
    'media-jam',
    'toner-empty',
    'door-open',
    'cover-open',
];

const MESSAGES: Record<PrinterReason, string> = {
    'media-empty': 'Sem papel',
    'media-low': 'Papel acabando',
    'media-jam': 'Papel atolado',
    'toner-empty': 'Sem toner',
    'toner-low': 'Toner baixo',
    'door-open': 'Porta aberta',
    'cover-open': 'Tampa aberta',
    other: 'Verificando impressora',
};

export function reasonLabel(reason: PrinterReason): string {
    return MESSAGES[reason];
}

/**
 * Resume `printer-state` + `printer-state-reasons` (IPP) no que a tela
 * do totem e a página mobile precisam mostrar.
 */
export function summarizePrinter(printer: PrinterStatus): PrinterSummary {
    if (printer.state === 'offline') {
        return {
            health: 'blocked',
            acceptingOrders: false,
            title: 'Impressora desconectada',
            detail: 'Já avisamos o suporte. Volte em alguns minutos.',
        };
    }

    const blocking = printer.reasons.find((reason) =>
        BLOCKING.includes(reason),
    );

    if (blocking || printer.state === 'stopped') {
        return {
            health: 'blocked',
            acceptingOrders: false,
            title: blocking ? MESSAGES[blocking] : 'Impressora pausada',
            detail: 'Pedidos novos estão pausados. A fila continua guardada.',
        };
    }

    const warning = printer.reasons.find(
        (reason) => reason === 'media-low' || reason === 'toner-low',
    );

    return {
        health: warning ? 'warning' : 'ok',
        acceptingOrders: true,
        title:
            printer.state === 'processing'
                ? 'Imprimindo'
                : 'Pronta para imprimir',
        detail: warning ? MESSAGES[warning] : null,
    };
}
