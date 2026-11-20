export type PortalLayoutVariant = 'corporate' | 'modern' | 'minimal' | 'salud';

export type TenantTheme = {
    layout: PortalLayoutVariant;
    primary_color: string;
    accent_color: string;
    /** Panel administrativo de la empresa: menú lateral y botones. */
    panel_color: string;
    panel_accent: string;
    font: string;
    logo_path: string | null;
    logo_url: string | null;
    /** Ícono de la pestaña del navegador; si no hay, se usa el logo. */
    icon_path?: string | null;
    icon_url?: string | null;
    hero_title: string;
    hero_subtitle: string;
    footer_text: string;
    website_url: string;
    font_family?: string;
    font_url?: string | null;
};

export type FormFieldType =
    | 'text'
    | 'textarea'
    | 'email'
    | 'tel'
    | 'number'
    | 'date'
    | 'select'
    | 'radio'
    | 'checkbox';

export type FormField = {
    key: string;
    label: string;
    type: FormFieldType;
    required: boolean;
    placeholder: string;
    help: string;
    width: 'full' | 'half';
    options: string[];
};

export type PqrsTypeConfig = {
    key: string;
    label: string;
    description: string;
    enabled: boolean;
    sla_days: number;
};

export type FormSchema = {
    types: PqrsTypeConfig[];
    fields: FormField[];
    require_document: boolean;
    require_phone: boolean;
    allow_attachments: boolean;
    max_attachments: number;
    /** Reclamos en salud: el ciudadano elige el nivel de riesgo. */
    risk_levels: boolean;
    /** Salud: paciente afectado, EPS, régimen, fecha de atención y aviso Supersalud. */
    health_data: boolean;
    /** Servicios que presta la empresa; vacío = no se pregunta. */
    services: string[];
    privacy_policy_url: string;
    privacy_text: string;
};

export type SharedTenant = {
    id: string;
    name: string;
    theme: TenantTheme;
};

export type TenantStatus = 'active' | 'suspended';

/** Administrador de una empresa (usuario de la base de la empresa). */
export type CompanyAdmin = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
};

export type TenantSummary = {
    id: string;
    name: string;
    nit: string | null;
    email: string | null;
    phone: string | null;
    status: TenantStatus;
    plan: string;
    radicado_prefix: string;
    database: string;
    domains: string[];
    portal_url: string | null;
    logo_url: string | null;
    primary_color: string;
    layout: PortalLayoutVariant;
    created_at: string;
};

export type PqrsStats = {
    total: number;
    open: number;
    overdue: number;
    this_month: number;
};

export type PqrsStatus = 'radicada' | 'en_tramite' | 'respondida' | 'cerrada';

export type Option = { value: string; label: string };

export type PqrsListItem = {
    id: number;
    radicado: string;
    type: string;
    type_label: string;
    status: PqrsStatus;
    status_label: string;
    subject: string;
    requester_name: string;
    requester_email: string;
    created_at: string;
    due_at: string | null;
    is_overdue: boolean;
    assignee: { id: number; name: string } | null;
    sede: { id: number; name: string } | null;
    risk_level: RiskLevel | null;
    risk_label: string | null;
};

/** Filtros de la lista de PQRS (los mismos para descargar en Excel). */
export type PqrsFilterValues = {
    search: string;
    status: string;
    type: string;
    sede: string;
    assigned: string;
    overdue: boolean;
    from: string;
    to: string;
};

/** PQRS eliminada por un super administrador (registro). */
export type PqrsDeletionItem = {
    id: number;
    radicado: string;
    reason: string;
    detail: string | null;
    deleted_by: string;
    deleted_at: string;
};

export type RiskLevel = 'simple' | 'priorizado' | 'vital';

/** Nivel de riesgo de un reclamo en salud (Supersalud). */
export type RiskLevelOption = {
    value: RiskLevel;
    label: string;
    title: string;
    description: string;
};

/** Cambio de estado permitido desde el estado actual. */
export type StatusTransition = Option & { requires_reason: boolean };

export type StaffOption = { value: number; label: string };

export type StaffRole = 'admin' | 'agente';

export type StaffUser = {
    id: number;
    name: string;
    email: string;
    role: StaffRole;
    role_label: string;
    is_active: boolean;
    sedes: { id: number; name: string }[];
    open_assigned: number;
    last_login_at: string | null;
    created_at: string;
    is_me: boolean;
};

/** Persona con acceso al panel central (todas las empresas). */
export type SuperAdmin = {
    id: number;
    name: string;
    email: string;
    two_factor: boolean;
    created_at: string;
    is_me: boolean;
};

export type AttachmentItem = {
    id: number;
    name: string;
    size: number;
    mime_type?: string | null;
};

export type PqrsActivityItem = {
    id: number;
    type:
        | 'created'
        | 'status_changed'
        | 'note'
        | 'response'
        | 'assigned'
        | 'sede_changed';
    body: string | null;
    is_public?: boolean;
    user?: { id: number; name: string } | null;
    created_at: string;
    attachments: AttachmentItem[];
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
};
