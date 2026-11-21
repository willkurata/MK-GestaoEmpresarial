export type PropertyStatus = 'rascunho' | 'publicado' | 'vendido' | 'alugado';

export type PropertyOptions = {
    statuses: PropertyStatus[];
    purposes: string[];
    types: string[];
    max_photos: number;
    max_features: number;
};

export type PropertyPhoto = { id: number; url: string; position: number };

export const statusLabels: Record<PropertyStatus, string> = {
    rascunho: 'Rascunho',
    publicado: 'Publicado',
    vendido: 'Vendido',
    alugado: 'Alugado',
};

export const statusClasses: Record<PropertyStatus, string> = {
    rascunho:
        'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    publicado:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    vendido: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
    alugado:
        'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-300',
};

export const purposeLabels: Record<string, string> = {
    venda: 'Venda',
    aluguel: 'Aluguel',
};

export const typeLabels: Record<string, string> = {
    casa: 'Casa',
    apartamento: 'Apartamento',
    terreno: 'Terreno',
    comercial: 'Comercial',
    rural: 'Rural',
    outro: 'Outro',
};

export const requirementLabels: Record<string, string> = {
    purpose: 'finalidade',
    type: 'tipo',
    city: 'cidade',
    neighborhood: 'bairro',
    price: 'preço',
    description: 'descrição',
    photos: 'ao menos uma foto',
};

const currency = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

export function formatPrice(value: string | number | null): string {
    if (value === null || value === '') {
        return '—';
    }

    return currency.format(Number(value));
}
