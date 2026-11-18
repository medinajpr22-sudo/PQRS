<?php

use App\Support\BusinessDays;
use App\Support\ColombianHolidays;
use Carbon\CarbonImmutable;

test('colombian holidays are computed for 2026', function () {
    expect(ColombianHolidays::forYear(2026))->toBe([
        '2026-01-01', // Año Nuevo
        '2026-01-12', // Reyes Magos (trasladado)
        '2026-03-23', // San José (trasladado)
        '2026-04-02', // Jueves Santo
        '2026-04-03', // Viernes Santo
        '2026-05-01', // Día del Trabajo
        '2026-05-18', // Ascensión
        '2026-06-08', // Corpus Christi
        '2026-06-15', // Sagrado Corazón
        '2026-06-29', // San Pedro y San Pablo
        '2026-07-20', // Independencia
        '2026-08-07', // Batalla de Boyacá
        '2026-08-17', // Asunción (trasladado)
        '2026-10-12', // Día de la Raza
        '2026-11-02', // Todos los Santos (trasladado)
        '2026-11-16', // Independencia de Cartagena (trasladado)
        '2026-12-08', // Inmaculada Concepción
        '2026-12-25', // Navidad
    ]);
});

test('due dates skip weekends and holidays', function () {
    // Radicada el viernes 13 de noviembre de 2026; el lunes 16 es festivo.
    $due = BusinessDays::add(CarbonImmutable::parse('2026-11-13 10:00'), 1);

    expect($due->toDateString())->toBe('2026-11-17');

    // 15 días hábiles desde el jueves 26 de marzo de 2026 (pasa por Semana Santa).
    expect(BusinessDays::add(CarbonImmutable::parse('2026-03-26 09:00'), 15)->toDateString())
        ->toBe('2026-04-20');
});
