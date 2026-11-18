import { readableTextColor } from '@/lib/format';

/**
 * Colores del panel administrativo de una empresa (menú lateral y botones).
 *
 * Se definen en :root para que también los usen los diálogos y menús, que
 * se dibujan fuera del layout. Los valores vienen validados como #rrggbb.
 */
export function PanelColors({
    color,
    accent,
}: {
    color?: string | null;
    accent?: string | null;
}) {
    const vars = [
        color && `--panel: ${color}; --panel-fg: ${readableTextColor(color)};`,
        accent &&
            `--panel-accent: ${accent}; --panel-accent-fg: ${readableTextColor(accent)};`,
    ]
        .filter(Boolean)
        .join(' ');

    if (!vars) {
        return null;
    }

    return <style>{`:root { ${vars} }`}</style>;
}
