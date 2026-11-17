import { initials, readableTextColor } from '@/lib/format';
import { cn } from '@/lib/utils';

export function TenantAvatar({
    name,
    color,
    logoUrl,
    className,
}: {
    name: string;
    color: string;
    logoUrl?: string | null;
    className?: string;
}) {
    if (logoUrl) {
        return (
            <span
                className={cn(
                    'flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-white p-1',
                    className,
                )}
            >
                <img
                    src={logoUrl}
                    alt=""
                    className="max-h-full max-w-full object-contain"
                />
            </span>
        );
    }

    return (
        <span
            className={cn(
                'flex size-9 shrink-0 items-center justify-center rounded-lg text-xs font-semibold',
                className,
            )}
            style={{ backgroundColor: color, color: readableTextColor(color) }}
        >
            {initials(name)}
        </span>
    );
}
