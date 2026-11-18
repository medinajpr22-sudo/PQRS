import { Link, router, usePage } from '@inertiajs/react';
import { ChevronDown, LogOut } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { useIsMobile } from '@/hooks/use-mobile';
import { cn } from '@/lib/utils';
import admin from '@/routes/tenant/admin';
import type { User } from '@/types';

/**
 * Rol que se muestra bajo el nombre: en el panel central es el super
 * administrador; en una empresa, administrador de empresa o agente.
 */
function roleLabel(user: User, inTenant: boolean): string {
    if (!inTenant) {
        return 'Super administrador';
    }

    return user.role === 'admin' ? 'Administrador de empresa' : 'Agente';
}

function usePanelUser() {
    const { auth, context } = usePage().props;
    const inTenant = context === 'tenant';

    return {
        user: auth.user,
        inTenant,
        role: auth.user ? roleLabel(auth.user, inTenant) : '',
    };
}

function Initials({ name, className }: { name: string; className?: string }) {
    const getInitials = useInitials();

    return (
        <span
            className={cn(
                'flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                className,
            )}
        >
            {getInitials(name)}
        </span>
    );
}

/** Opciones del menú del usuario según el panel. */
function PanelUserMenu({ user, role }: { user: User; role: string }) {
    const { context } = usePage().props;

    if (context !== 'tenant') {
        return <UserMenuContent user={user} />;
    }

    return (
        <>
            <DropdownMenuLabel className="font-normal">
                <p className="truncate text-sm font-medium">{user.name}</p>
                <p className="truncate text-xs text-muted-foreground">
                    {user.email} · {role}
                </p>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={admin.logout()}
                    as="button"
                    onClick={() => router.flushAll()}
                >
                    <LogOut className="mr-2" />
                    Cerrar sesión
                </Link>
            </DropdownMenuItem>
        </>
    );
}

function NameAndRole({
    name,
    role,
    className,
}: {
    name: string;
    role: string;
    className?: string;
}) {
    return (
        <span className={cn('grid min-w-0 text-left leading-tight', className)}>
            <span className="truncate text-sm font-bold">{name}</span>
            <span className="truncate text-xs opacity-75">{role}</span>
        </span>
    );
}

/**
 * Tarjeta del usuario al pie del menú lateral.
 */
export function PanelSidebarUser() {
    const { user, role } = usePanelUser();
    const { state } = useSidebar();
    const isMobile = useIsMobile();

    if (!user) {
        return null;
    }

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="h-14 rounded-2xl border border-sidebar-border bg-white/5 data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        >
                            <Initials
                                name={user.name}
                                className="bg-sidebar-accent text-sidebar-accent-foreground"
                            />
                            <NameAndRole name={user.name} role={role} />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-xl"
                        align="end"
                        side={
                            isMobile
                                ? 'bottom'
                                : state === 'collapsed'
                                  ? 'left'
                                  : 'top'
                        }
                    >
                        <PanelUserMenu user={user} role={role} />
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}

/**
 * Usuario en la barra superior (avatar, nombre y rol, con su menú).
 */
export function PanelHeaderUser({ before }: { before?: ReactNode }) {
    const { user, role } = usePanelUser();

    if (!user) {
        return null;
    }

    return (
        <div className="flex items-center gap-2">
            {before}
            <DropdownMenu>
                <DropdownMenuTrigger className="flex items-center gap-2.5 rounded-full py-1 pr-2 pl-1 transition-colors outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring">
                    <Initials
                        name={user.name}
                        className="bg-(--panel) text-(--panel-fg)"
                    />
                    <NameAndRole
                        name={user.name}
                        role={role}
                        className="hidden sm:grid"
                    />
                    <ChevronDown className="size-4 text-muted-foreground" />
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    className="min-w-60 rounded-xl"
                    align="end"
                >
                    <PanelUserMenu user={user} role={role} />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}
