export type StudentStatus = 'ativo' | 'inadimplente' | 'bloqueado' | 'inativo';

export type IdentificationMethod = 'facial' | 'qr' | 'rfid' | 'totem';

export type Plan = {
    id: string;
    name: string;
    monthlyPriceInCents: number;
    /** Faixa de horário liberada pelo plano, em horas inteiras [início, fim). */
    accessHours: [number, number];
};

export type Student = {
    id: string;
    name: string;
    planId: string;
    status: StudentStatus;
    /** Próximo vencimento da mensalidade (YYYY-MM-DD). */
    dueDate: string;
    /** Último check-in registrado (ISO) ou null quando nunca treinou. */
    lastCheckInAt: string | null;
    phone: string;
    hasPixAutomatico: boolean;
};

export type AccessDenialReason =
    | 'mensalidade'
    | 'fora_do_horario'
    | 'lotacao'
    | 'inativo'
    | 'nao_identificado';

export type AccessDecision =
    | { allowed: true }
    | { allowed: false; reason: AccessDenialReason };

export type AccessDirection = 'entrada' | 'saida';

export type AccessEvent = {
    id: string;
    studentId: string | null;
    studentName: string;
    direction: AccessDirection;
    method: IdentificationMethod;
    allowed: boolean;
    reason?: AccessDenialReason;
    at: string;
};

export type GymClass = {
    id: string;
    name: string;
    coach: string;
    room: string;
    /** Data/hora de início (ISO). */
    startsAt: string;
    durationMinutes: number;
    capacity: number;
    bookedStudentIds: string[];
    waitlistStudentIds: string[];
};

export type BookingResult =
    | { status: 'confirmada' }
    | { status: 'fila'; position: number }
    | { status: 'ja_inscrito' };

export type NotificationKind = 'mensalidade' | 'promocao' | 'aula' | 'sistema';

export type AppNotification = {
    id: string;
    kind: NotificationKind;
    title: string;
    body: string;
    createdAt: string;
    read: boolean;
    action?: { label: string; to: string };
};

export type NotificationPreferences = {
    push: boolean;
    whatsapp: boolean;
    email: boolean;
    promotions: boolean;
    quietHours: boolean;
};

export type Campaign = {
    id: string;
    title: string;
    message: string;
    audience: CampaignAudience;
    channel: 'push' | 'whatsapp' | 'email';
    sentAt: string;
    recipients: number;
};

export type CampaignAudience =
    | 'todos'
    | 'inadimplentes'
    | 'em_risco'
    | 'ativos';

export type RevenueMonth = {
    month: string;
    expectedInCents: number;
    receivedInCents: number;
};
