/** Turno de caixa ainda não fechado (ListOpenCashSessions no backend). */
export type OpenCashSession = {
    id: number;
    company: { id: number; name: string; cnpj: string };
    operator: string;
    register: string;
    openedAt: string;
    openedAtIso: string;
    hoursOpen: number;
    isStale: boolean;
};
