import { Head, useForm, usePage } from '@inertiajs/react';
import { ExternalLink, Save } from 'lucide-react';
import BrandingController from '@/actions/App/Http/Controllers/Tenant/Admin/BrandingController';
import { PanelColors } from '@/components/panel/panel-colors';
import { PageHeader } from '@/components/pqrs/page-header';
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
import { Spinner } from '@/components/ui/spinner';
import { readableTextColor } from '@/lib/format';
import admin from '@/routes/tenant/admin';

type Colors = {
    primary_color: string;
    accent_color: string;
    panel_color: string;
    panel_accent: string;
};

const isHex = (value: string) => /^#[0-9a-f]{6}$/i.test(value);

/**
 * Colores de la empresa que ajusta su administrador. Mientras se eligen,
 * el panel se pinta con ellos para verlos en contexto; al guardar los ve
 * todo el equipo y el portal público.
 */
export default function CompanyColors({ colors }: { colors: Colors }) {
    const { tenant } = usePage().props;
    const form = useForm<Colors>(colors);
    const { data } = form;

    const save = () =>
        form.submit(BrandingController.update(), { preserveScroll: true });

    return (
        <>
            <Head title="Colores de la empresa" />

            {/* Vista previa en vivo del panel con los colores sin guardar. */}
            <PanelColors
                color={isHex(data.panel_color) ? data.panel_color : null}
                accent={isHex(data.panel_accent) ? data.panel_accent : null}
            />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Colores de la empresa"
                    description="Elige los colores del portal público y del panel. El panel cambia mientras eliges; guarda para que lo vea todo el equipo."
                    actions={
                        <Button
                            onClick={save}
                            disabled={form.processing || !form.isDirty}
                        >
                            {form.processing ? <Spinner /> : <Save />}
                            Guardar colores
                        </Button>
                    }
                />

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Panel administrativo</CardTitle>
                            <CardDescription>
                                El menú lateral, los botones y el fondo del
                                panel donde el equipo gestiona las PQRS.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-5 sm:grid-cols-[1fr_1fr_auto]">
                            <ColorField
                                label="Menú lateral"
                                value={data.panel_color}
                                onChange={(v) => form.setData('panel_color', v)}
                                error={form.errors.panel_color}
                                presets={PANEL_PRESETS}
                            />
                            <ColorField
                                label="Botones y resaltados"
                                value={data.panel_accent}
                                onChange={(v) =>
                                    form.setData('panel_accent', v)
                                }
                                error={form.errors.panel_accent}
                                presets={PANEL_ACCENT_PRESETS}
                            />
                            <PanelThumb
                                color={
                                    isHex(data.panel_color)
                                        ? data.panel_color
                                        : colors.panel_color
                                }
                                accent={
                                    isHex(data.panel_accent)
                                        ? data.panel_accent
                                        : colors.panel_accent
                                }
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Portal público</CardTitle>
                            <CardDescription>
                                Los colores que ven los ciudadanos al radicar y
                                consultar sus PQRS.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-5 sm:grid-cols-[1fr_1fr_auto]">
                            <ColorField
                                label="Color principal"
                                value={data.primary_color}
                                onChange={(v) =>
                                    form.setData('primary_color', v)
                                }
                                error={form.errors.primary_color}
                                presets={PORTAL_PRESETS}
                            />
                            <ColorField
                                label="Color de acento"
                                value={data.accent_color}
                                onChange={(v) =>
                                    form.setData('accent_color', v)
                                }
                                error={form.errors.accent_color}
                                presets={ACCENT_PRESETS}
                            />
                            <PortalThumb
                                name={tenant?.name ?? ''}
                                color={
                                    isHex(data.primary_color)
                                        ? data.primary_color
                                        : colors.primary_color
                                }
                                accent={
                                    isHex(data.accent_color)
                                        ? data.accent_color
                                        : colors.accent_color
                                }
                            />
                        </CardContent>
                    </Card>
                </div>

                <p className="text-sm text-muted-foreground">
                    La plantilla, el logo y los textos del portal los ajusta el
                    administrador general de la plataforma.{' '}
                    <a
                        href="/"
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1 font-medium text-foreground underline underline-offset-4"
                    >
                        Ver portal público
                        <ExternalLink className="size-3.5" />
                    </a>
                </p>
            </div>
        </>
    );
}

/**
 * Miniatura del portal con el color principal y el de acento.
 */
function PortalThumb({
    name,
    color,
    accent,
}: {
    name: string;
    color: string;
    accent: string;
}) {
    return (
        <div className="flex h-28 w-40 flex-col self-end overflow-hidden rounded-lg border bg-white">
            <div
                className="truncate px-2 py-1.5 text-[10px] font-bold"
                style={{
                    backgroundColor: color,
                    color: readableTextColor(color),
                }}
            >
                {name}
            </div>
            <div className="flex flex-1 flex-col gap-1.5 p-2">
                <div className="h-1.5 w-2/3 rounded bg-zinc-300" />
                <div className="h-1.5 w-1/2 rounded bg-zinc-200" />
                <div className="mt-auto flex items-center justify-between">
                    <span
                        className="rounded px-1.5 text-[9px] leading-4 font-bold"
                        style={{
                            backgroundColor: color,
                            color: readableTextColor(color),
                        }}
                    >
                        Radicar
                    </span>
                    <span
                        className="size-3 rounded-full"
                        style={{ backgroundColor: accent }}
                    />
                </div>
            </div>
        </div>
    );
}

CompanyColors.layout = {
    breadcrumbs: [
        { title: 'Resumen', href: admin.dashboard() },
        { title: 'Colores', href: admin.branding.edit() },
    ],
};
