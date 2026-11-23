<?php

namespace App\Support;

use Closure;

/**
 * Límites reales para subir archivos: los de la app (config/pqrs.php →
 * attachments) recortados a lo que permite PHP en este servidor.
 *
 * Si la app dijera «hasta 10 MB» y PHP solo dejara 2 MB, el archivo se
 * perdería con un error confuso; y si el envío completo pasa de
 * post_max_size, PHP descarta todo el formulario. Por eso la app nunca
 * promete más de lo que el servidor acepta.
 */
final class UploadLimits
{
    /** Espacio que se deja en post_max_size para los textos del formulario. */
    private const FORM_MARGIN_KB = 512;

    /**
     * Tamaño máximo de cada archivo, en KB.
     */
    public static function perFileKb(): int
    {
        $app = max(1, (int) config('pqrs.attachments.max_kb'));
        $php = self::iniKb('upload_max_filesize');

        return $php === null ? $app : max(1, min($app, $php));
    }

    /**
     * Tamaño máximo de todos los archivos juntos, en KB.
     */
    public static function totalKb(): int
    {
        $app = max(self::perFileKb(), (int) config('pqrs.attachments.max_total_kb'));
        $post = self::iniKb('post_max_size');

        return $post === null ? $app : max(1, min($app, $post - self::FORM_MARGIN_KB));
    }

    /**
     * Lo que necesita el navegador para avisar antes de subir.
     *
     * @return array{max_kb: int, max_total_kb: int, mimes: list<string>}
     */
    public static function forFrontend(): array
    {
        return [
            'max_kb' => self::perFileKb(),
            'max_total_kb' => self::totalKb(),
            'mimes' => array_values(config('pqrs.attachments.mimes')),
        ];
    }

    /**
     * Reglas de validación para un campo de archivos (attachments[]).
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(string $field, int $maxFiles): array
    {
        $totalKb = self::totalKb();

        return [
            $field => ['nullable', 'array', 'max:'.$maxFiles, self::totalRule($totalKb)],
            $field.'.*' => ['file', 'max:'.self::perFileKb(), 'mimes:'.implode(',', config('pqrs.attachments.mimes'))],
        ];
    }

    /**
     * Mensajes en español con los límites en MB.
     *
     * @return array<string, string>
     */
    public static function messages(string $field, int $maxFiles): array
    {
        $perFile = self::mb(self::perFileKb());
        $types = mb_strtoupper(implode(', ', config('pqrs.attachments.mimes')));

        return [
            $field.'.max' => "Puedes adjuntar máximo {$maxFiles} ".($maxFiles === 1 ? 'archivo.' : 'archivos.'),
            $field.'.*.max' => "Cada archivo puede pesar máximo {$perFile}. Reduce su tamaño (por ejemplo, guardándolo como PDF comprimido) e inténtalo de nuevo.",
            $field.'.*.uploaded' => "Un archivo no se pudo subir: puede que pese más de {$perFile}, el máximo por archivo.",
            $field.'.*.mimes' => "Solo se aceptan archivos {$types}.",
            $field.'.*.file' => 'Uno de los adjuntos no es un archivo válido.',
        ];
    }

    /**
     * «5 MB», «1,5 MB» o «500 KB».
     */
    public static function mb(int $kb): string
    {
        if ($kb < 1024) {
            return "{$kb} KB";
        }

        $mb = round($kb / 1024, 1);

        return str_replace('.', ',', (string) ($mb == (int) $mb ? (int) $mb : $mb)).' MB';
    }

    /**
     * Regla: todos los archivos juntos no pasan de $totalKb.
     */
    private static function totalRule(int $totalKb): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($totalKb): void {
            $bytes = collect(is_array($value) ? $value : [])
                ->sum(fn ($file) => is_object($file) && method_exists($file, 'getSize') ? (int) $file->getSize() : 0);

            if ($bytes > $totalKb * 1024) {
                $fail('Los archivos suman '.self::mb((int) ceil($bytes / 1024)).' y el máximo por envío es '.self::mb($totalKb).'. Quita alguno o redúcelos de tamaño.');
            }
        };
    }

    /**
     * Valor de php.ini en KB («2M» → 2048). Null = sin límite.
     */
    private static function iniKb(string $key): ?int
    {
        $value = trim((string) ini_get($key));

        if ($value === '' || $value === '0' || $value === '-1') {
            return null;
        }

        $number = (float) $value;

        $bytes = match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };

        return (int) floor($bytes / 1024);
    }
}
