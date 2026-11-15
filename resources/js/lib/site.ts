import type {
    PropertyPurpose,
    PropertyStatus,
    PropertySummary,
} from '@/types/site';

/**
 * Link do WhatsApp com mensagem pré-preenchida, para que o atendimento
 * já saiba de qual imóvel ou serviço o visitante está falando.
 */
export function whatsappUrl(phone: string, message: string): string {
    return `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
}

export function propertyWhatsappMessage(property: PropertySummary): string {
    return `Olá! Tenho interesse no imóvel ${property.reference} — ${property.title} (${property.city}). Pode me passar mais informações?`;
}

export function serviceWhatsappMessage(service: string): string {
    return `Olá! Gostaria de falar sobre ${service} com a MK Negócios.`;
}

export function formatPrice(value: string | null): string {
    if (value === null) {
        return 'Sob consulta';
    }

    return Number(value).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
        maximumFractionDigits: 0,
    });
}

export const purposeLabels: Record<PropertyPurpose, string> = {
    venda: 'Venda',
    locacao: 'Locação',
};

export const statusLabels: Record<PropertyStatus, string> = {
    disponivel: 'Disponível',
    vendido: 'Vendido',
    alugado: 'Alugado',
};
