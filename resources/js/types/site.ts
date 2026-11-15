/**
 * Contrato de props do site público MK Negócios.
 *
 * Fonte única do formato que o backend de imóveis (WIL-17/WIL-18) entrega
 * às páginas em resources/js/pages/site. Valores monetários seguem o padrão
 * do MK Gestão: decimal em string ("450000.00") ou null quando sob consulta.
 */

export type SiteSharedProps = {
    url: string;
    gestao_url: string;
    contact: {
        whatsapp: string;
        whatsapp_display: string;
        email: string;
    };
    cities: string[];
    creci: string | null;
    crc: string | null;
};

export type PropertyPurpose = 'venda' | 'locacao';

export type PropertyStatus = 'disponivel' | 'vendido' | 'alugado';

export type PropertyPhoto = {
    url: string;
    alt: string;
};

export type PropertySummary = {
    /** Código de referência exibido ao público, ex.: "MK-0012". */
    reference: string;
    /** Segmento único da URL /imoveis/{slug}. */
    slug: string;
    title: string;
    purpose: PropertyPurpose;
    /** Tipo livre cadastrado no painel: "Casa", "Apartamento", "Terreno"… */
    type: string;
    city: string;
    neighborhood: string | null;
    price: string | null;
    status: PropertyStatus;
    cover: PropertyPhoto | null;
    bedrooms: number | null;
    bathrooms: number | null;
    parking_spaces: number | null;
    area_m2: string | null;
};

export type PropertyDetail = PropertySummary & {
    description: string;
    /** Características em texto curto: "Piscina", "Vista para o mar"… */
    features: string[];
    /** Galeria ordenada; a capa também aparece aqui. */
    photos: PropertyPhoto[];
    /** Localização aproximada; nunca o endereço exato. */
    location_label: string;
    /** Taxas mensais opcionais (condomínio, IPTU), decimal em string. */
    condo_fee: string | null;
    iptu: string | null;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

/** Formato JSON do LengthAwarePaginator do Laravel. */
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
};

export type PropertyFilters = {
    finalidade: PropertyPurpose | null;
    cidade: string | null;
    tipo: string | null;
};
