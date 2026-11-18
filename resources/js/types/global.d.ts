import type { Auth } from '@/types/auth';
import type { SharedTenant } from '@/types/pqrs';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            tenant: SharedTenant | null;
            /** Panel central o el de una empresa. */
            context: 'central' | 'tenant';
            /** PQRS abiertas asignadas al usuario (solo en el panel de una empresa). */
            myOpenPqrs: number | null;
            [key: string]: unknown;
        };
    }
}
