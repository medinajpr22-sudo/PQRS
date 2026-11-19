import { useSyncExternalStore } from 'react';

/**
 * Modo de los paneles: claro (por defecto) u oscuro. Cada persona lo cambia
 * con los botones de la barra superior y se recuerda en su navegador.
 */
export type ResolvedAppearance = 'light' | 'dark';
export type Appearance = ResolvedAppearance;

export type UseAppearanceReturn = {
    readonly appearance: Appearance;
    readonly resolvedAppearance: ResolvedAppearance;
    readonly updateAppearance: (mode: Appearance) => void;
};

const listeners = new Set<() => void>();
let currentAppearance: Appearance = 'light';

const setCookie = (name: string, value: string, days = 365): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

// Cualquier otro valor guardado (p. ej. el antiguo «system») cuenta como claro.
const normalize = (value: string | null): Appearance =>
    value === 'dark' ? 'dark' : 'light';

const readStored = (): string | null => {
    try {
        return localStorage.getItem('appearance');
    } catch {
        return null;
    }
};

const store = (mode: Appearance): void => {
    try {
        localStorage.setItem('appearance', mode);
    } catch {
        // Sin almacenamiento local (modo privado): queda la cookie.
    }

    setCookie('appearance', mode);
};

const applyTheme = (appearance: Appearance): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const forceLight =
        document.documentElement.hasAttribute('data-force-light');
    const isDark = !forceLight && appearance === 'dark';

    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const notify = (): void => listeners.forEach((listener) => listener());

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const stored = readStored();
    currentAppearance = normalize(stored);

    if (stored !== currentAppearance) {
        store(currentAppearance);
    }

    applyTheme(currentAppearance);
}

export function useAppearance(): UseAppearanceReturn {
    const appearance: Appearance = useSyncExternalStore(
        subscribe,
        () => currentAppearance,
        () => 'light',
    );

    const updateAppearance = (mode: Appearance): void => {
        currentAppearance = mode;
        store(mode);
        applyTheme(mode);
        notify();
    };

    return {
        appearance,
        resolvedAppearance: appearance,
        updateAppearance,
    } as const;
}
