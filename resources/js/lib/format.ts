const TIME_ZONE = 'America/Bogota';

export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('es-CO', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: TIME_ZONE,
    }).format(new Date(value));
}

export function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('es-CO', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: TIME_ZONE,
    }).format(new Date(value));
}

export function formatLongDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('es-CO', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: TIME_ZONE,
    }).format(new Date(value));
}

export function formatMonth(yearMonth: string): string {
    const [year, month] = yearMonth.split('-').map(Number);

    return new Intl.DateTimeFormat('es-CO', { month: 'short' })
        .format(new Date(year, month - 1, 1))
        .replace('.', '');
}

export function formatNumber(value: number): string {
    return new Intl.NumberFormat('es-CO').format(value);
}

export function formatFileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(0)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

/**
 * Días restantes hasta una fecha (negativo si ya venció).
 */
export function daysUntil(value: string | null): number | null {
    if (!value) {
        return null;
    }

    const diff = new Date(value).getTime() - Date.now();

    return Math.ceil(diff / (1000 * 60 * 60 * 24));
}

/**
 * Color de texto legible (blanco o casi negro) sobre un color de fondo hex.
 */
export function readableTextColor(hex: string): string {
    const clean = hex.replace('#', '');
    const r = parseInt(clean.substring(0, 2), 16) / 255;
    const g = parseInt(clean.substring(2, 4), 16) / 255;
    const b = parseInt(clean.substring(4, 6), 16) / 255;
    const lin = (c: number) =>
        c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    const luminance = 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);

    return luminance > 0.45 ? '#111827' : '#ffffff';
}

export function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter((w) => w.length > 2 || /^[A-Z]/.test(w))
        .slice(0, 2)
        .map((w) => w[0]?.toUpperCase())
        .join('');
}
