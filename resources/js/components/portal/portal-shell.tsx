import { Link } from '@inertiajs/react';
import { ExternalLink, FilePlus2, Home, Search } from 'lucide-react';
import type { CSSProperties, ReactNode } from 'react';
import { readableTextColor } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { TenantTheme } from '@/types';

type NavKey = 'home' | 'create' | 'track';

const NAV: { key: NavKey; label: string; href: string; icon: typeof Home }[] = [
    { key: 'home', label: 'Inicio', href: '/', icon: Home },
    { key: 'create', label: 'Radicar PQRS', href: '/radicar', icon: FilePlus2 },
    {
        key: 'track',
        label: 'Consultar estado',
        href: '/consultar',
        icon: Search,
    },
];

export type PortalShellProps = {
    tenantName: string;
    theme: TenantTheme;
    title?: string;
    description?: string;
    active?: NavKey;
    /** En la vista previa del panel central los enlaces no navegan. */
    preview?: boolean;
    children: ReactNode;
};

export function brandStyle(theme: PortalShellProps['theme']): CSSProperties {
    const fg = readableTextColor(theme.primary_color);
    const accentFg = readableTextColor(theme.accent_color);

    return {
        '--brand': theme.primary_color,
        '--brand-fg': fg,
        '--brand-accent': theme.accent_color,
        '--brand-accent-fg': accentFg,
        '--brand-soft': `color-mix(in oklab, ${theme.primary_color} 8%, white)`,
        '--brand-dark': `color-mix(in oklab, ${theme.primary_color} 70%, black)`,
        // Las utilidades de Tailwind (bg-primary, ring...) leen --color-*,
        // así que se redefinen aquí para que los componentes usen la marca.
        '--primary': theme.primary_color,
        '--primary-foreground': fg,
        '--ring': theme.primary_color,
        '--color-primary': theme.primary_color,
        '--color-primary-foreground': fg,
        '--color-ring': theme.primary_color,
        fontFamily: theme.font_family
            ? `${theme.font_family}, ui-sans-serif, system-ui, sans-serif`
            : undefined,
    } as CSSProperties;
}

function NavLink({
    item,
    active,
    preview,
    className,
    children,
}: {
    item: (typeof NAV)[number];
    active: boolean;
    preview?: boolean;
    className: string;
    children: ReactNode;
}) {
    if (preview) {
        return (
            <span className={className} data-active={active || undefined}>
                {children}
            </span>
        );
    }

    return (
        <Link
            href={item.href}
            className={className}
            data-active={active || undefined}
            prefetch
        >
            {children}
        </Link>
    );
}

function Logo({
    theme,
    tenantName,
    className,
}: {
    theme: TenantTheme;
    tenantName: string;
    className?: string;
}) {
    if (theme.logo_url) {
        return (
            <img
                src={theme.logo_url}
                alt={tenantName}
                className={cn('h-10 w-auto max-w-44 object-contain', className)}
            />
        );
    }

    const letters = tenantName
        .split(/\s+/)
        .filter((w) => w.length > 2)
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase();

    return (
        <span
            className={cn(
                'flex size-10 items-center justify-center rounded-lg bg-(--brand) text-sm font-bold text-(--brand-fg)',
                className,
            )}
        >
            {letters}
        </span>
    );
}

function FooterContent({
    theme,
    tenantName,
    preview,
    className,
}: {
    theme: TenantTheme;
    tenantName: string;
    preview?: boolean;
    className?: string;
}) {
    return (
        <div className={cn('space-y-3 text-sm', className)}>
            <p className="font-semibold">{tenantName}</p>
            {theme.footer_text && (
                <p className="whitespace-pre-line opacity-80">
                    {theme.footer_text}
                </p>
            )}
            <div className="flex flex-wrap gap-x-5 gap-y-1 opacity-80">
                {theme.website_url && (
                    <a
                        href={preview ? undefined : theme.website_url}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1 hover:underline"
                    >
                        Sitio web <ExternalLink className="size-3" />
                    </a>
                )}
                {preview ? (
                    <span>Acceso funcionarios</span>
                ) : (
                    <a href="/admin/login" className="hover:underline">
                        Acceso funcionarios
                    </a>
                )}
            </div>
        </div>
    );
}

function screenHeight(preview?: boolean): string {
    return preview ? 'min-h-[1280px]' : 'min-h-svh';
}

export function PortalShell(props: PortalShellProps) {
    const { theme } = props;

    return (
        <div
            style={brandStyle(theme)}
            className="min-h-full bg-white text-zinc-900 antialiased"
        >
            {theme.layout === 'modern' ? (
                <ModernLayout {...props} />
            ) : theme.layout === 'minimal' ? (
                <MinimalLayout {...props} />
            ) : (
                <CorporateLayout {...props} />
            )}
        </div>
    );
}

/* ------------------------------------------------------------------ */
/* Corporativo: barra superior, banner de color y pie oscuro           */
/* ------------------------------------------------------------------ */
function CorporateLayout({
    tenantName,
    theme,
    title,
    description,
    active = 'home',
    preview,
    children,
}: PortalShellProps) {
    return (
        <div className={cn('flex flex-col bg-zinc-50', screenHeight(preview))}>
            <header
                className={cn(
                    'top-0 z-20 border-b border-zinc-200 bg-white/95 backdrop-blur',
                    !preview && 'sticky',
                )}
            >
                <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
                    <div className="flex min-w-0 items-center gap-3">
                        <Logo theme={theme} tenantName={tenantName} />
                        {!theme.logo_url && (
                            <span className="truncate font-semibold">
                                {tenantName}
                            </span>
                        )}
                    </div>
                    <nav className="hidden items-center gap-1 md:flex">
                        {NAV.map((item) => (
                            <NavLink
                                key={item.key}
                                item={item}
                                active={active === item.key}
                                preview={preview}
                                className="rounded-md px-3 py-2 text-sm font-medium text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900 data-active:text-(--brand)"
                            >
                                {item.label}
                            </NavLink>
                        ))}
                    </nav>
                </div>
            </header>

            <section className="relative overflow-hidden bg-(--brand) text-(--brand-fg)">
                <div
                    aria-hidden
                    className="absolute inset-0 opacity-20"
                    style={{
                        backgroundImage:
                            'radial-gradient(circle at 85% 20%, var(--brand-accent) 0, transparent 35%), radial-gradient(circle at 10% 100%, var(--brand-dark) 0, transparent 45%)',
                    }}
                />
                <div className="relative mx-auto max-w-6xl px-4 pt-12 pb-24 sm:px-6 md:pt-16">
                    <span className="mb-4 inline-block h-1 w-12 rounded-full bg-(--brand-accent)" />
                    <h1 className="max-w-3xl text-3xl font-bold tracking-tight md:text-4xl">
                        {title ?? theme.hero_title}
                    </h1>
                    {(description ?? theme.hero_subtitle) && (
                        <p className="mt-3 max-w-2xl text-base opacity-90 md:text-lg">
                            {description ?? theme.hero_subtitle}
                        </p>
                    )}
                </div>
            </section>

            <main className="relative mx-auto -mt-14 w-full max-w-6xl flex-1 px-4 pb-16 sm:px-6">
                {children}
            </main>

            <nav
                className={cn(
                    'fixed inset-x-0 bottom-0 z-20 grid grid-cols-3 border-t bg-white md:hidden',
                    preview && 'hidden',
                )}
            >
                {NAV.map((item) => (
                    <NavLink
                        key={item.key}
                        item={item}
                        active={active === item.key}
                        preview={preview}
                        className="flex flex-col items-center gap-1 py-2 text-[11px] text-zinc-500 data-active:text-(--brand)"
                    >
                        <item.icon className="size-5" />
                        {item.label}
                    </NavLink>
                ))}
            </nav>

            <footer className="bg-zinc-900 pb-16 text-zinc-200 md:pb-0">
                <div className="mx-auto flex max-w-6xl flex-col justify-between gap-6 px-4 py-10 sm:px-6 md:flex-row">
                    <FooterContent
                        theme={theme}
                        tenantName={tenantName}
                        preview={preview}
                    />
                    <p className="self-end text-xs text-zinc-400">
                        Sistema de PQRS · Ley 1755 de 2015
                    </p>
                </div>
            </footer>
        </div>
    );
}

/* ------------------------------------------------------------------ */
/* Moderno: panel lateral de marca + contenido a la derecha            */
/* ------------------------------------------------------------------ */
function ModernLayout({
    tenantName,
    theme,
    title,
    description,
    active = 'home',
    preview,
    children,
}: PortalShellProps) {
    return (
        <div
            className={cn(
                'lg:grid lg:grid-cols-[minmax(320px,420px)_1fr]',
                screenHeight(preview),
            )}
        >
            <aside
                className={cn(
                    'relative overflow-hidden bg-(--brand) text-(--brand-fg)',
                    preview ? 'lg:h-[1280px]' : 'lg:sticky lg:top-0 lg:h-svh',
                )}
            >
                <div
                    aria-hidden
                    className="absolute -right-24 -bottom-24 size-80 rounded-full bg-(--brand-accent) opacity-25 blur-2xl"
                />
                <div
                    aria-hidden
                    className="absolute -top-20 -left-20 size-64 rounded-full bg-white opacity-10"
                />
                <div className="relative flex h-full flex-col gap-8 p-6 sm:p-10">
                    <div className="flex items-center gap-3">
                        <span className="rounded-xl bg-white p-2 shadow-sm">
                            <Logo
                                theme={theme}
                                tenantName={tenantName}
                                className="h-9"
                            />
                        </span>
                        {!theme.logo_url && (
                            <span className="font-semibold">{tenantName}</span>
                        )}
                    </div>

                    <div>
                        <h1 className="text-3xl leading-tight font-bold tracking-tight lg:text-4xl">
                            {title ?? theme.hero_title}
                        </h1>
                        {(description ?? theme.hero_subtitle) && (
                            <p className="mt-4 text-base opacity-90">
                                {description ?? theme.hero_subtitle}
                            </p>
                        )}
                    </div>

                    <nav className="flex flex-wrap gap-2 lg:flex-col">
                        {NAV.map((item) => (
                            <NavLink
                                key={item.key}
                                item={item}
                                active={active === item.key}
                                preview={preview}
                                className="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium transition-colors hover:bg-white/15 data-active:bg-white data-active:text-zinc-900 data-active:shadow-sm"
                            >
                                <item.icon className="size-4" />
                                {item.label}
                            </NavLink>
                        ))}
                    </nav>

                    <FooterContent
                        theme={theme}
                        tenantName={tenantName}
                        preview={preview}
                        className="mt-auto hidden text-xs lg:block"
                    />
                </div>
            </aside>

            <div
                className={cn(
                    'flex flex-col bg-zinc-50',
                    screenHeight(preview),
                )}
            >
                <main className="mx-auto w-full max-w-3xl flex-1 px-4 py-8 sm:px-8 lg:py-14">
                    {children}
                </main>
                <footer className="border-t bg-white px-6 py-6 lg:hidden">
                    <FooterContent
                        theme={theme}
                        tenantName={tenantName}
                        preview={preview}
                    />
                </footer>
            </div>
        </div>
    );
}

/* ------------------------------------------------------------------ */
/* Minimalista: columna centrada, mucho aire                           */
/* ------------------------------------------------------------------ */
function MinimalLayout({
    tenantName,
    theme,
    title,
    description,
    active = 'home',
    preview,
    children,
}: PortalShellProps) {
    return (
        <div
            className={cn(
                'flex flex-col border-t-4 border-(--brand) bg-white',
                screenHeight(preview),
            )}
        >
            <header className="mx-auto flex w-full max-w-3xl flex-col items-center gap-5 px-4 pt-10 text-center">
                <Logo theme={theme} tenantName={tenantName} className="h-12" />
                <p className="text-sm font-medium tracking-wide text-zinc-500 uppercase">
                    {tenantName}
                </p>
                <nav className="inline-flex flex-wrap justify-center gap-1 rounded-full bg-zinc-100 p-1">
                    {NAV.map((item) => (
                        <NavLink
                            key={item.key}
                            item={item}
                            active={active === item.key}
                            preview={preview}
                            className="rounded-full px-4 py-1.5 text-sm font-medium text-zinc-600 transition-colors hover:text-zinc-900 data-active:bg-white data-active:text-(--brand) data-active:shadow-sm"
                        >
                            {item.label}
                        </NavLink>
                    ))}
                </nav>
                <div className="pt-4">
                    <h1 className="text-2xl font-semibold tracking-tight md:text-3xl">
                        {title ?? theme.hero_title}
                    </h1>
                    {(description ?? theme.hero_subtitle) && (
                        <p className="mx-auto mt-2 max-w-xl text-zinc-500">
                            {description ?? theme.hero_subtitle}
                        </p>
                    )}
                </div>
            </header>

            <main className="mx-auto w-full max-w-3xl flex-1 px-4 py-10">
                {children}
            </main>

            <footer className="border-t">
                <FooterContent
                    theme={theme}
                    tenantName={tenantName}
                    preview={preview}
                    className="mx-auto max-w-3xl px-4 py-8 text-center text-zinc-600 [&>div]:justify-center"
                />
            </footer>
        </div>
    );
}
