import type {} from 'altcha/types/react';
import type { WidgetMethods } from 'altcha/types';
import { useEffect, useRef } from 'react';
import PqrsSubmissionController from '@/actions/App/Http/Controllers/Tenant/PqrsSubmissionController';
import InputError from '@/components/input-error';

/**
 * Casilla «No soy un robot» (ALTCHA). Al marcarla, el navegador resuelve
 * un reto que pide el servidor y deja la solución en el campo oculto
 * «altcha» del formulario. No usa Google ni servicios externos.
 */
export function NotARobot({ error }: { error?: string }) {
    const widget = useRef<HTMLElement & Partial<WidgetMethods>>(null);

    useEffect(() => {
        // Solo en el navegador: el widget usa Web Workers y Web Crypto.
        void import('altcha/i18n/es-419').then(() => import('altcha'));
    }, []);

    // Si el servidor la rechaza (venció o ya se usó), hay que marcarla otra vez.
    useEffect(() => {
        if (error) {
            widget.current?.reset?.();
        }
    }, [error]);

    return (
        <div>
            <altcha-widget
                ref={widget}
                challenge={PqrsSubmissionController.captcha.url()}
                name="altcha"
                language="es-419"
                configuration={JSON.stringify({ hideFooter: true })}
                style={{
                    '--altcha-color-primary': 'var(--brand)',
                    '--altcha-color-primary-content': 'var(--brand-fg)',
                    '--altcha-border-radius': '0.75rem',
                    '--altcha-max-width': '320px',
                }}
            />
            <InputError message={error} className="mt-2" />
        </div>
    );
}
