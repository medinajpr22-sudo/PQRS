import { HeartHandshake, ScrollText, Sparkles, ThumbsUp } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { ResponseTemplate, TemplateId } from '@/lib/response-templates';
import { cn } from '@/lib/utils';

const ICONS: Record<TemplateId, typeof Sparkles> = {
    formal: ScrollText,
    apology: HeartHandshake,
    thanks: ThumbsUp,
};

/**
 * Botón «Ayuda a redactar»: ofrece tres plantillas de respuesta para
 * empezar a escribir. La sugerida según el tipo de solicitud va primero.
 */
export function WritingHelp({
    templates,
    suggested,
    onPick,
    onClosed,
    className,
}: {
    templates: ResponseTemplate[];
    suggested: TemplateId;
    onPick: (template: ResponseTemplate) => void;
    /**
     * Al cerrarse el menú. Devuelve true si ya movió el foco (p. ej. al
     * mensaje); si no, el foco vuelve al botón, como en cualquier menú.
     */
    onClosed?: () => boolean;
    className?: string;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className={cn(
                        'border-violet-200 bg-linear-to-r from-violet-50 to-fuchsia-50 text-violet-700 hover:from-violet-100 hover:to-fuchsia-100 hover:text-violet-800',
                        'dark:border-violet-900 dark:from-violet-950/60 dark:to-fuchsia-950/60 dark:text-violet-200 dark:hover:text-violet-100',
                        className,
                    )}
                >
                    <Sparkles />
                    Ayuda a redactar
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="w-80"
                // El foco no vuelve al botón: quien usa el menú decide (el
                // mensaje, con el primer texto por completar seleccionado).
                onCloseAutoFocus={(e) => {
                    if (onClosed?.()) {
                        e.preventDefault();
                    }
                }}
            >
                <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">
                    Elige un punto de partida: el nombre, el radicado y la fecha
                    se llenan solos.
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {templates.map((t) => {
                    const Icon = ICONS[t.id];

                    return (
                        <DropdownMenuItem
                            key={t.id}
                            onSelect={() => onPick(t)}
                            className="items-start gap-3 py-2"
                        >
                            <Icon className="mt-0.5 text-violet-600 dark:text-violet-300" />
                            <div className="min-w-0 flex-1">
                                <p className="flex items-center gap-2 font-medium">
                                    {t.title}
                                    {t.id === suggested && (
                                        <span className="rounded-full bg-violet-100 px-1.5 py-px text-[10px] font-semibold tracking-wide text-violet-700 uppercase dark:bg-violet-900 dark:text-violet-200">
                                            Sugerida
                                        </span>
                                    )}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {t.description}
                                </p>
                            </div>
                        </DropdownMenuItem>
                    );
                })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
