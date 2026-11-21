export type RiskLevel = 'none' | 'ok' | 'attention' | 'high' | 'critical';

export type RiskAssessment = {
    level: RiskLevel;
    label: string;
    reasons: string[];
};

export type TrendSeries = {
    key: string;
    label: string;
    /** Classe Tailwind de cor de texto/traço, ex.: `text-dash-sales`. */
    colorClass: string;
    values: number[];
};
