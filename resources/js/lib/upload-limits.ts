/**
 * Límites para adjuntar archivos (los manda el servidor: App\Support\UploadLimits).
 * Se revisan en el navegador antes de subir, para avisar con un mensaje
 * claro en vez de esperar a que el servidor rechace el envío.
 */
export type UploadRules = {
    /** Máximo por archivo, en KB. */
    max_kb: number;
    /** Máximo de todos los archivos juntos, en KB. */
    max_total_kb: number;
    mimes: string[];
};

/** «5 MB», «1,5 MB» o «500 KB». */
export function formatKb(kb: number): string {
    if (kb < 1024) {
        return `${kb} KB`;
    }

    const mb = Math.round((kb / 1024) * 10) / 10;

    return `${String(mb).replace('.', ',')} MB`;
}

/** «PDF, JPG, PNG · hasta 5 MB cada uno · 10 MB en total». */
export function describeLimits(rules: UploadRules): string {
    return [
        rules.mimes.join(', ').toUpperCase(),
        `hasta ${formatKb(rules.max_kb)} cada uno`,
        `${formatKb(rules.max_total_kb)} en total`,
    ].join(' · ');
}

/**
 * Agrega a la lista los archivos elegidos que cumplen los límites y explica
 * por qué se dejaron por fuera los demás.
 */
export function pickFiles(
    current: File[],
    picked: File[],
    rules: UploadRules,
    maxFiles: number,
): { files: File[]; problems: string[] } {
    const files = [...current];
    const problems: string[] = [];
    const allowed = new Set(rules.mimes.map((m) => m.toLowerCase()));

    for (const file of picked) {
        const extension = file.name.split('.').pop()?.toLowerCase() ?? '';

        if (files.length >= maxFiles) {
            problems.push(
                `Puedes adjuntar máximo ${maxFiles} ${maxFiles === 1 ? 'archivo' : 'archivos'}: «${file.name}» quedó por fuera.`,
            );
            continue;
        }

        if (!allowed.has(extension)) {
            problems.push(
                `«${file.name}» no se puede adjuntar: solo se aceptan ${rules.mimes.join(', ').toUpperCase()}.`,
            );
            continue;
        }

        if (file.size > rules.max_kb * 1024) {
            problems.push(
                `«${file.name}» pesa ${formatKb(Math.ceil(file.size / 1024))} y el máximo por archivo es ${formatKb(rules.max_kb)}. Redúcelo (por ejemplo, guardándolo como PDF comprimido).`,
            );
            continue;
        }

        const total = files.reduce((sum, f) => sum + f.size, 0) + file.size;

        if (total > rules.max_total_kb * 1024) {
            problems.push(
                `«${file.name}» no cabe: todos los archivos juntos pueden pesar máximo ${formatKb(rules.max_total_kb)}.`,
            );
            continue;
        }

        files.push(file);
    }

    return { files, problems };
}
