import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { PanelHeaderUser } from '@/components/panel/panel-user';
import { ThemeToggle } from '@/components/panel/theme-toggle';
import { SidebarTrigger } from '@/components/ui/sidebar';
import admin from '@/routes/tenant/admin';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { context, myOpenPqrs } = usePage().props;

    return (
        <header className="sticky top-0 z-10 px-4 pt-3 md:px-6">
            <div className="flex h-16 items-center justify-between gap-3 rounded-2xl border bg-card/90 px-3 shadow-sm backdrop-blur md:px-4">
                <div className="flex min-w-0 items-center gap-2">
                    <SidebarTrigger className="-ml-1" />
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
                <PanelHeaderUser
                    before={
                        <>
                            <ThemeToggle />
                            {context === 'tenant' && (
                                <Link
                                    href={admin.pqrs.index({
                                        query: { assigned: 'me' },
                                    })}
                                    className="relative flex size-10 items-center justify-center rounded-full border bg-background transition-colors hover:bg-muted"
                                    aria-label={
                                        myOpenPqrs
                                            ? `Tienes ${myOpenPqrs} PQRS abiertas asignadas`
                                            : 'Mis PQRS'
                                    }
                                    title="Mis PQRS abiertas"
                                >
                                    <Bell className="size-4" />
                                    {!!myOpenPqrs && (
                                        <span className="absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-bold text-primary-foreground">
                                            {myOpenPqrs > 99
                                                ? '99+'
                                                : myOpenPqrs}
                                        </span>
                                    )}
                                </Link>
                            )}
                        </>
                    }
                />
            </div>
        </header>
    );
}
