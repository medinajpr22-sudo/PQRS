<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Cálculo de días hábiles (lunes a viernes).
 *
 * Nota: no descuenta festivos. Para Colombia se puede agregar la lista
 * de festivos (Ley Emiliani) en self::holidays().
 */
class BusinessDays
{
    public static function add(DateTimeInterface $from, int $days): CarbonImmutable
    {
        $date = CarbonImmutable::instance($from);
        $holidays = self::holidays();

        while ($days > 0) {
            $date = $date->addDay();

            if ($date->isWeekend() || in_array($date->toDateString(), $holidays, true)) {
                continue;
            }

            $days--;
        }

        return $date->endOfDay();
    }

    /**
     * @return list<string> Fechas en formato Y-m-d
     */
    protected static function holidays(): array
    {
        return config('pqrs.holidays', []);
    }
}
