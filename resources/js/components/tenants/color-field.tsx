import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { readableTextColor } from '@/lib/format';
import { cn } from '@/lib/utils';

/** Colores sugeridos para cada campo (siempre se puede elegir cualquiera). */
export const PORTAL_PRESETS = [
    '#1d4ed8',
    '#0f3d91',
    '#0e7c66',
    '#0369a1',
    '#7c3aed',
    '#be123c',
    '#c2410c',
    '#15803d',
    '#111827',
];

export const ACCENT_PRESETS = [
    '#f59e0b',
    '#f5a524',
    '#e11d48',
    '#22c55e',
    '#06b6d4',
    '#a855f7',
];

export const PANEL_PRESETS = [
    '#5b1a24',
    '#0f172a',
    '#1e3a8a',
    '#064e3b',
    '#3b0764',
    '#e2e8f0',
];

export const PANEL_ACCENT_PRESETS = [
    '#c62833',
    '#1d4ed8',
    '#0e7c66',
    '#c2410c',
    '#7c3aed',
    '#111827',
];

/**
 * Selector de color con su código y algunos colores sugeridos.
 */
export function ColorField({
    label,
    value,
    onChange,
    error,
    presets,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    presets: string[];
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <div className="flex items-center gap-2">
                <input
                    type="color"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    className="size-9 cursor-pointer rounded-md border bg-transparent p-1"
                    aria-label={label}
                />
                <Input
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    className="font-mono uppercase"
                    maxLength={7}
                />
            </div>
            <div className="flex flex-wrap gap-1.5">
                {presets.map((c) => (
                    <button
                        key={c}
                        type="button"
                        onClick={() => onChange(c)}
                        className={cn(
                            'size-6 rounded-full border-2 border-white shadow ring-1 ring-border transition-transform hover:scale-110',
                            value.toLowerCase() === c &&
                                'ring-2 ring-foreground',
                        )}
                        style={{ backgroundColor: c }}
                        aria-label={c}
                    />
                ))}
            </div>
            <InputError message={error} />
        </div>
    );
}

/**
 * Miniatura del panel administrativo con los colores elegidos.
 */
export function PanelThumb({
    color,
    accent,
}: {
    color: string;
    accent: string;
}) {
    const fg = readableTextColor(color);
    const accentFg = readableTextColor(accent);

    return (
        <div className="flex h-28 w-40 gap-1.5 self-end rounded-lg border bg-zinc-50 p-1.5">
            <div
                className="flex w-12 flex-col gap-1 rounded-md p-1.5"
                style={{ backgroundColor: color }}
            >
                <div
                    className="h-1.5 rounded-full opacity-60"
                    style={{ backgroundColor: fg }}
                />
                <div
                    className="h-2.5 rounded"
                    style={{ backgroundColor: fg }}
                />
                <div
                    className="h-1.5 rounded-full opacity-40"
                    style={{ backgroundColor: fg }}
                />
                <div
                    className="h-1.5 rounded-full opacity-40"
                    style={{ backgroundColor: fg }}
                />
            </div>
            <div className="flex flex-1 flex-col gap-1.5">
                <div className="h-3 rounded bg-white shadow-xs" />
                <div className="flex justify-end">
                    <span
                        className="rounded px-1.5 text-[9px] leading-4 font-bold"
                        style={{ backgroundColor: accent, color: accentFg }}
                    >
                        + Nuevo
                    </span>
                </div>
                <div className="flex-1 rounded bg-white shadow-xs" />
            </div>
        </div>
    );
}
