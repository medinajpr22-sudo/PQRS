import { formatNumber } from '@/lib/format';

/**
 * Columnas verticales (una sola serie). Tooltip al pasar el mouse,
 * valor visible solo en la columna más alta y tabla accesible.
 */
export function ColumnChart({
    data,
    caption,
}: {
    data: { label: string; value: number }[];
    caption: string;
}) {
    const max = Math.max(1, ...data.map((d) => d.value));
    const maxIndex = data.findIndex((d) => d.value === max);
    const ticks = [max, Math.round(max / 2), 0];

    return (
        <figure>
            <div className="flex h-48 gap-3">
                <div className="flex flex-col justify-between pb-6 text-right text-[11px] text-muted-foreground tabular-nums">
                    {ticks.map((t, i) => (
                        <span key={i}>{formatNumber(t)}</span>
                    ))}
                </div>
                <div className="relative flex flex-1 flex-col">
                    <div className="pointer-events-none absolute inset-x-0 top-0 bottom-6 flex flex-col justify-between">
                        {ticks.map((_, i) => (
                            <div key={i} className="h-px bg-border/70" />
                        ))}
                    </div>
                    <div className="relative flex flex-1 items-end justify-around gap-1">
                        {data.map((d, i) => (
                            <div
                                key={d.label}
                                className="group relative flex h-full flex-1 flex-col items-center justify-end"
                            >
                                <span className="pointer-events-none absolute -top-1 z-10 -translate-y-full rounded-md border bg-popover px-2 py-1 text-xs whitespace-nowrap text-popover-foreground opacity-0 shadow-sm transition-opacity group-hover:opacity-100">
                                    {d.label}:{' '}
                                    <strong>{formatNumber(d.value)}</strong>
                                </span>
                                {i === maxIndex && d.value > 0 && (
                                    <span className="mb-1 text-xs font-medium tabular-nums">
                                        {formatNumber(d.value)}
                                    </span>
                                )}
                                <div
                                    className="w-full max-w-6 rounded-t-[4px] bg-(--chart-bar) transition-opacity group-hover:opacity-80"
                                    style={{
                                        height: `${(d.value / max) * 100}%`,
                                        minHeight: d.value > 0 ? 2 : 0,
                                    }}
                                />
                            </div>
                        ))}
                    </div>
                    <div className="flex h-6 items-end justify-around gap-1 text-[11px] text-muted-foreground capitalize">
                        {data.map((d) => (
                            <span key={d.label} className="flex-1 text-center">
                                {d.label}
                            </span>
                        ))}
                    </div>
                </div>
            </div>
            <table className="sr-only">
                <caption>{caption}</caption>
                <tbody>
                    {data.map((d) => (
                        <tr key={d.label}>
                            <th>{d.label}</th>
                            <td>{d.value}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </figure>
    );
}

/**
 * Barras horizontales con etiqueta y valor en la punta (una sola serie).
 */
export function BarList({
    data,
    emptyText = 'Sin datos todavía',
}: {
    data: { label: string; value: number }[];
    emptyText?: string;
}) {
    const max = Math.max(1, ...data.map((d) => d.value));
    const total = data.reduce((sum, d) => sum + d.value, 0);

    if (total === 0) {
        return (
            <p className="py-8 text-center text-sm text-muted-foreground">
                {emptyText}
            </p>
        );
    }

    return (
        <ul className="space-y-3">
            {data.map((d) => (
                <li
                    key={d.label}
                    className="group"
                    title={`${d.label}: ${d.value}`}
                >
                    <div className="mb-1 flex items-baseline justify-between gap-2 text-sm">
                        <span className="truncate">{d.label}</span>
                        <span className="text-muted-foreground tabular-nums">
                            {formatNumber(d.value)}
                            <span className="ml-1 text-xs">
                                ({Math.round((d.value / total) * 100)}%)
                            </span>
                        </span>
                    </div>
                    <div className="h-2 rounded-full bg-muted">
                        <div
                            className="h-2 rounded-full bg-(--chart-bar) transition-opacity group-hover:opacity-80"
                            style={{ width: `${(d.value / max) * 100}%` }}
                        />
                    </div>
                </li>
            ))}
        </ul>
    );
}
