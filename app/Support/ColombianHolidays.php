<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Festivos de Colombia calculados para cualquier año (18 por año).
 *
 * - Fijos: 1 ene, 1 may, 20 jul, 7 ago, 8 dic, 25 dic.
 * - Ley Emiliani (Ley 51 de 1983), se pasan al lunes siguiente:
 *   6 ene, 19 mar, 29 jun, 15 ago, 12 oct, 1 nov, 11 nov.
 * - Según la Pascua: Jueves y Viernes Santo, y (ya trasladados a lunes)
 *   Ascensión, Corpus Christi y Sagrado Corazón.
 */
class ColombianHolidays
{
    /** @var array<int, list<string>> */
    protected static array $cache = [];

    /**
     * @return list<string> Fechas Y-m-d ordenadas
     */
    public static function forYear(int $year): array
    {
        return self::$cache[$year] ??= self::compute($year);
    }

    /**
     * @return list<string>
     */
    protected static function compute(int $year): array
    {
        $date = fn (int $month, int $day) => CarbonImmutable::create($year, $month, $day);
        $easter = self::easterSunday($year);

        $holidays = [
            // Fijos
            $date(1, 1),
            $date(5, 1),
            $date(7, 20),
            $date(8, 7),
            $date(12, 8),
            $date(12, 25),

            // Ley Emiliani: se trasladan al lunes siguiente
            self::nextMonday($date(1, 6)),
            self::nextMonday($date(3, 19)),
            self::nextMonday($date(6, 29)),
            self::nextMonday($date(8, 15)),
            self::nextMonday($date(10, 12)),
            self::nextMonday($date(11, 1)),
            self::nextMonday($date(11, 11)),

            // Semana Santa
            $easter->subDays(3),
            $easter->subDays(2),

            // Móviles según la Pascua, ya en lunes
            $easter->addDays(43), // Ascensión del Señor
            $easter->addDays(64), // Corpus Christi
            $easter->addDays(71), // Sagrado Corazón
        ];

        $dates = array_values(array_unique(array_map(
            fn (CarbonImmutable $d) => $d->toDateString(),
            $holidays,
        )));
        sort($dates);

        return $dates;
    }

    protected static function nextMonday(CarbonImmutable $date): CarbonImmutable
    {
        return $date->isMonday() ? $date : $date->next(CarbonInterface::MONDAY);
    }

    /**
     * Domingo de Pascua (algoritmo de Meeus/Jones/Butcher, calendario gregoriano).
     * No depende de la extensión "calendar" de PHP.
     */
    protected static function easterSunday(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day);
    }
}
