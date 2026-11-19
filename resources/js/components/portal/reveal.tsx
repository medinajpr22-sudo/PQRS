import { createElement, useEffect, useState } from 'react';
import type { CSSProperties, ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Hace aparecer un elemento (sube y se aclara) cuando entra en pantalla.
 * Las reglas de la animación están en app.css (.portal-reveal) y se
 * apagan solas si la persona prefiere reducir el movimiento.
 */
export function useReveal<T extends HTMLElement>() {
    const [node, setNode] = useState<T | null>(null);
    // Navegadores muy viejos: se muestra todo sin animar.
    const [shown, setShown] = useState(
        () => typeof IntersectionObserver === 'undefined',
    );

    useEffect(() => {
        if (!node || shown) {
            return;
        }

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setShown(true);
                    observer.disconnect();
                }
            },
            { threshold: 0.12, rootMargin: '0px 0px -40px 0px' },
        );

        observer.observe(node);

        return () => observer.disconnect();
    }, [node, shown]);

    return { ref: setNode, shown };
}

/**
 * Props para la animación de aparición. `delay` en pasos de 90 ms, para
 * que los elementos de una misma fila aparezcan uno detrás de otro.
 */
export function revealProps(shown: boolean, delay = 0, className?: string) {
    return {
        'data-shown': shown,
        className: cn('portal-reveal', className),
        style: delay
            ? ({ '--reveal-delay': `${delay * 90}ms` } as CSSProperties)
            : undefined,
    };
}

export function Reveal({
    as = 'div',
    delay = 0,
    className,
    children,
}: {
    as?: 'div' | 'li' | 'section' | 'article';
    delay?: number;
    className?: string;
    children: ReactNode;
}) {
    const { ref, shown } = useReveal<HTMLElement>();

    return createElement(
        as,
        { ref, ...revealProps(shown, delay, className) },
        children,
    );
}

/**
 * Retraso para las animaciones de entrada (.portal-enter).
 */
export function enterDelay(ms: number): CSSProperties {
    return { '--enter-delay': `${ms}ms` } as CSSProperties;
}
