export type PortalLayoutVariant = 'corporate' | 'modern' | 'minimal';

export type TenantTheme = {
    layout: PortalLayoutVariant;
    primary_color: string;
    accent_color: string;
    font: string;
    logo_path: string | null;
    logo_url: string | null;
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
    privacy_policy_url: string;
    privacy_text: string;
};

export type SharedTenant = {
    id: string;
    name: string;
    theme: TenantTheme;
};

export type TenantStatus = 'active' | 'suspended';

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
