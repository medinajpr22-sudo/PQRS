<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Cálculo de días hábiles: lunes a viernes, sin festivos de Colombia
 * (calculados automáticamente) ni los días extra de config('pqrs.holidays').
 */
class BusinessDays
{
    /**
     * Fecha de vencimiento: $days días hábiles contados desde el día siguiente
     * a $from (art. 14 Ley 1755 de 2015). Vence al final de ese día.
     */
    public static function add(DateTimeInterface $from, int $days): CarbonImmutable
    {
        $date = CarbonImmutable::instance($from);

        while ($days > 0) {
            $date = $date->addDay();

            if (self::isBusinessDay($date)) {
                $days--;
            }
        }

        return $date->endOfDay();
    }

    public static function isBusinessDay(DateTimeInterface $date): bool
    {
        $date = CarbonImmutable::instance($date);

        return ! $date->isWeekend()
            && ! in_array($date->toDateString(), self::holidays($date->year), true);
    }

    /**
     * @return list<string> Fechas en formato Y-m-d
     */
    public static function holidays(int $year): array
    {
        $national = config('pqrs.colombian_holidays', true) ? ColombianHolidays::forYear($year) : [];

        return [...$national, ...config('pqrs.holidays', [])];
    }
}
