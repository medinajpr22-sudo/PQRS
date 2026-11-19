import { useForm } from '@inertiajs/react';
import { ImageUp, Monitor, Trash2 } from 'lucide-react';
import { useEffect, useMemo } from 'react';
import TenantThemeController from '@/actions/App/Http/Controllers/Central/TenantThemeController';
import InputError from '@/components/input-error';
import { PortalShell } from '@/components/portal/portal-shell';
import {
    ACCENT_PRESETS,
    ColorField,
    PANEL_ACCENT_PRESETS,
    PANEL_PRESETS,
    PanelThumb,
    PORTAL_PRESETS,
} from '@/components/tenants/color-field';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { PortalLayoutVariant, TenantTheme } from '@/types';

export type FontOption = {
    value: string;
    label: string;
    family: string;
    bunny: string | null;
};

const LAYOUTS: {
    value: PortalLayoutVariant;
    label: string;
    description: string;
}[] = [
    {
        value: 'corporate',
        label: 'Corporativo',
        description: 'Barra superior, banner de color y pie oscuro.',
    },
    {
        value: 'modern',
        label: 'Moderno',
        description: 'Panel lateral de marca y contenido a la derecha.',
    },
    {
        value: 'minimal',
        label: 'Minimalista',
        description: 'Columna centrada, limpia y con mucho aire.',
    },
    {
        value: 'salud',
        label: 'Salud',
        description:
            'Encabezado negro, barra de consulta y tipos con su definición.',
    },
];

export function ThemeEditor({
    tenantId,
    tenantName,
    theme,
    fonts,
}: {
    tenantId: string;
    tenantName: string;
    theme: TenantTheme;
    fonts: FontOption[];
}) {
    const form = useForm({
        layout: theme.layout,
        primary_color: theme.primary_color,
        accent_color: theme.accent_color,
        panel_color: theme.panel_color,
        panel_accent: theme.panel_accent,
        font: theme.font,
        hero_title: theme.hero_title,
        hero_subtitle: theme.hero_subtitle ?? '',
        footer_text: theme.footer_text ?? '',
        website_url: theme.website_url ?? '',
        logo: null as File | null,
        remove_logo: false,
    });

    const font = fonts.find((f) => f.value === form.data.font);

    // Carga la tipografía elegida para la vista previa.
    useEffect(() => {
        if (!font?.bunny) {
            return;
        }

        const id = `font-${font.value}`;

        if (document.getElementById(id)) {
            return;
        }

        const link = document.createElement('link');
        link.id = id;
        link.rel = 'stylesheet';
        link.href = `https://fonts.bunny.net/css?family=${font.bunny}&display=swap`;
        document.head.appendChild(link);
    }, [font]);

    const logoPreview = useMemo(
        () => (form.data.logo ? URL.createObjectURL(form.data.logo) : null),
        [form.data.logo],
    );

    const currentLogo = form.data.remove_logo
        ? null
        : (logoPreview ?? theme.logo_url);

    const previewTheme: TenantTheme = {
        ...theme,
        ...form.data,
        logo_url: currentLogo,
        font_family: font?.family,
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(TenantThemeController.update.url(tenantId), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () =>
                form.setData((d) => ({ ...d, logo: null, remove_logo: false })),
        });
    };

    return (
        <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]">
            <form onSubmit={submit} className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Plantilla</CardTitle>
                        <CardDescription>
                            Estructura visual del portal público.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        {LAYOUTS.map((l) => (
                            <button
                                key={l.value}
                                type="button"
                                onClick={() => form.setData('layout', l.value)}
                                className={cn(
                                    'rounded-lg border p-3 text-left transition-colors hover:bg-muted/50',
                                    form.data.layout === l.value &&
                                        'border-primary ring-1 ring-primary',
                                )}
                            >
                                <LayoutThumb
                                    variant={l.value}
                                    color={form.data.primary_color}
                                />
                                <p className="mt-2 text-sm font-medium">
                                    {l.label}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {l.description}
                                </p>
                            </button>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Marca</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-5">
                        <div className="grid gap-2">
                            <Label>Logo</Label>
                            <div className="flex items-center gap-4">
                                <div className="flex size-16 items-center justify-center overflow-hidden rounded-lg border bg-white p-1.5">
                                    {currentLogo ? (
                                        <img
                                            src={currentLogo}
                                            alt=""
                                            className="max-h-full max-w-full object-contain"
                                        />
                                    ) : (
                                        <ImageUp className="size-6 text-muted-foreground" />
                                    )}
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        asChild
                                    >
                                        <label className="cursor-pointer">
                                            <ImageUp />
                                            Subir logo
                                            <input
                                                type="file"
                                                accept="image/png,image/jpeg,image/webp"
                                                className="sr-only"
                                                onChange={(e) => {
                                                    const file =
                                                        e.target.files?.[0] ??
                                                        null;
                                                    form.setData((d) => ({
                                                        ...d,
                                                        logo: file,
                                                        remove_logo: false,
                                                    }));
                                                }}
                                            />
                                        </label>
                                    </Button>
                                    {currentLogo && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                form.setData((d) => ({
                                                    ...d,
                                                    logo: null,
                                                    remove_logo: true,
                                                }))
                                            }
                                        >
                                            <Trash2 />
                                            Quitar
                                        </Button>
                                    )}
                                </div>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                PNG, JPG o WEBP, máximo 2 MB. Ideal: fondo
                                transparente.
                            </p>
                            <InputError message={form.errors.logo} />
                        </div>

                        <div className="grid gap-5 sm:grid-cols-2">
                            <ColorField
                                label="Color principal"
                                value={form.data.primary_color}
                                onChange={(v) =>
                                    form.setData('primary_color', v)
                                }
                                error={form.errors.primary_color}
                                presets={PORTAL_PRESETS}
                            />
                            <ColorField
                                label="Color de acento"
                                value={form.data.accent_color}
                                onChange={(v) =>
                                    form.setData('accent_color', v)
                                }
                                error={form.errors.accent_color}
                                presets={ACCENT_PRESETS}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label>Tipografía</Label>
                            <div className="grid gap-2 sm:grid-cols-3">
                                {fonts.map((f) => (
                                    <button
                                        key={f.value}
                                        type="button"
                                        onClick={() =>
                                            form.setData('font', f.value)
                                        }
                                        className={cn(
                                            'rounded-md border px-3 py-2 text-left text-sm transition-colors hover:bg-muted/50',
                                            form.data.font === f.value &&
                                                'border-primary ring-1 ring-primary',
                                        )}
                                        style={{ fontFamily: f.family }}
                                    >
                                        {f.label}
                                    </button>
                                ))}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Panel administrativo</CardTitle>
                        <CardDescription>
                            Colores del panel donde el personal de la empresa
                            gestiona sus PQRS.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5 sm:grid-cols-[1fr_1fr_auto]">
                        <ColorField
                            label="Menú lateral"
                            value={form.data.panel_color}
                            onChange={(v) => form.setData('panel_color', v)}
                            error={form.errors.panel_color}
                            presets={PANEL_PRESETS}
                        />
                        <ColorField
                            label="Botones y resaltados"
                            value={form.data.panel_accent}
                            onChange={(v) => form.setData('panel_accent', v)}
                            error={form.errors.panel_accent}
                            presets={PANEL_ACCENT_PRESETS}
                        />
                        <PanelThumb
                            color={form.data.panel_color}
                            accent={form.data.panel_accent}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Textos del portal</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-5">
                        <div className="grid gap-2">
                            <Label htmlFor="hero_title">Título principal</Label>
                            <Input
                                id="hero_title"
                                value={form.data.hero_title}
                                onChange={(e) =>
                                    form.setData('hero_title', e.target.value)
                                }
                                maxLength={120}
                            />
                            <InputError message={form.errors.hero_title} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="hero_subtitle">Subtítulo</Label>
                            <Textarea
                                id="hero_subtitle"
                                rows={2}
                                value={form.data.hero_subtitle}
                                onChange={(e) =>
                                    form.setData(
                                        'hero_subtitle',
                                        e.target.value,
                                    )
                                }
                                maxLength={300}
                            />
                            <InputError message={form.errors.hero_subtitle} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="footer_text">
                                Pie de página (dirección, teléfonos, horario)
                            </Label>
                            <Textarea
                                id="footer_text"
                                rows={3}
                                value={form.data.footer_text}
                                onChange={(e) =>
                                    form.setData('footer_text', e.target.value)
                                }
                                maxLength={500}
                            />
                            <InputError message={form.errors.footer_text} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="website_url">Sitio web</Label>
                            <Input
                                id="website_url"
                                type="url"
                                placeholder="https://"
                                value={form.data.website_url}
                                onChange={(e) =>
                                    form.setData('website_url', e.target.value)
                                }
                            />
                            <InputError message={form.errors.website_url} />
                        </div>
                    </CardContent>
                </Card>

                <div className="flex items-center justify-end gap-3">
                    {form.isDirty && (
                        <span className="text-sm text-muted-foreground">
                            Cambios sin guardar
                        </span>
                    )}
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        Guardar diseño
                    </Button>
                </div>
            </form>

            <div className="xl:sticky xl:top-4 xl:self-start">
                <div className="overflow-hidden rounded-xl border bg-muted shadow-sm">
                    <div className="flex items-center gap-2 border-b bg-background px-3 py-2">
                        <span className="flex gap-1.5">
                            <span className="size-2.5 rounded-full bg-red-400" />
                            <span className="size-2.5 rounded-full bg-amber-400" />
                            <span className="size-2.5 rounded-full bg-emerald-400" />
                        </span>
                        <span className="flex flex-1 items-center justify-center gap-1.5 text-xs text-muted-foreground">
                            <Monitor className="size-3.5" />
                            Vista previa del portal
                        </span>
                    </div>
                    <div className="relative h-[640px] overflow-hidden bg-white">
                        <div className="pointer-events-none absolute top-0 left-0 h-[200%] w-[200%] origin-top-left scale-50">
                            <PortalShell
                                tenantName={tenantName}
                                theme={previewTheme}
                                preview
                            >
                                <PreviewContent />
                            </PortalShell>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function PreviewContent() {
    return (
        <div className="space-y-6">
            <div className="grid gap-4 md:grid-cols-2">
                {['Radicar una solicitud', 'Consultar el estado'].map(
                    (t, i) => (
                        <div
                            key={t}
                            className="rounded-2xl border border-zinc-200 bg-white p-7 shadow-sm"
                        >
                            <span
                                className={
                                    i === 0
                                        ? 'flex size-12 items-center justify-center rounded-xl bg-(--brand)'
                                        : 'flex size-12 items-center justify-center rounded-xl bg-(--brand-soft)'
                                }
                            />
                            <p className="mt-4 text-xl font-semibold">{t}</p>
                            <p className="mt-1.5 text-sm text-zinc-500">
                                Presenta o consulta tus peticiones, quejas,
                                reclamos y sugerencias en línea.
                            </p>
                            <span
                                className={
                                    i === 0
                                        ? 'mt-4 inline-block rounded-lg bg-(--brand) px-4 py-2 text-sm font-semibold text-(--brand-fg)'
                                        : 'mt-4 inline-block rounded-lg border border-zinc-300 px-4 py-2 text-sm font-semibold'
                                }
                            >
                                {i === 0 ? 'Radicar PQRS' : 'Consultar'}
                            </span>
                        </div>
                    ),
                )}
            </div>
            <div className="grid gap-3 md:grid-cols-3">
                {['Petición', 'Queja', 'Reclamo'].map((t) => (
                    <div
                        key={t}
                        className="rounded-xl border border-zinc-200 bg-white p-5"
                    >
                        <p className="font-semibold">{t}</p>
                        <p className="mt-1 text-sm text-zinc-500">
                            Descripción del tipo de solicitud.
                        </p>
                        <p className="mt-3 text-xs font-medium text-(--brand)">
                            15 días hábiles
                        </p>
                    </div>
                ))}
            </div>
        </div>
    );
}

function LayoutThumb({
    variant,
    color,
}: {
    variant: PortalLayoutVariant;
    color: string;
}) {
    return (
        <div className="aspect-[4/3] overflow-hidden rounded-md border bg-zinc-50">
            {variant === 'corporate' && (
                <div className="flex h-full flex-col">
                    <div className="h-2 bg-white" />
                    <div className="h-6" style={{ backgroundColor: color }} />
                    <div className="mx-2 -mt-2 flex-1 rounded-sm border bg-white" />
                    <div className="mt-1 h-2 bg-zinc-800" />
                </div>
            )}
            {variant === 'modern' && (
                <div className="flex h-full">
                    <div className="w-2/5" style={{ backgroundColor: color }} />
                    <div className="flex-1 p-1.5">
                        <div className="h-full rounded-sm border bg-white" />
                    </div>
                </div>
            )}
            {variant === 'salud' && (
                <div className="flex h-full flex-col bg-white">
                    <div
                        className="h-7 border-b-2 bg-black"
                        style={{ borderColor: color }}
                    />
                    <div
                        className="mx-2 -mt-1.5 h-2.5 rounded-sm"
                        style={{ backgroundColor: color }}
                    />
                    <div className="mx-2 mt-1.5 flex-1 space-y-1">
                        <div className="h-1 w-1/2 rounded bg-zinc-300" />
                        <div className="h-1 w-2/3 rounded bg-zinc-200" />
                        <div className="h-1 w-1/2 rounded bg-zinc-200" />
                    </div>
                    <div
                        className="h-2 border-t bg-black"
                        style={{ borderColor: color }}
                    />
                </div>
            )}
            {variant === 'minimal' && (
                <div
                    className="flex h-full flex-col items-center border-t-2 bg-white p-1.5"
                    style={{ borderColor: color }}
                >
                    <div
                        className="size-2 rounded-full"
                        style={{ backgroundColor: color }}
                    />
                    <div className="mt-1 h-1 w-1/3 rounded bg-zinc-200" />
                    <div className="mt-1.5 w-2/3 flex-1 rounded-sm border" />
                </div>
            )}
        </div>
    );
}
