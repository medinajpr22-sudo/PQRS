<?php

namespace App\Services;

use App\Enums\PqrsStatus;
use App\Models\Pqrs;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PqrsFilters;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\DateTimeCell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Properties;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga de PQRS en Excel (.xlsx) con los filtros de la lista.
 *
 * - Una fila por PQRS, con encabezado fijo y filtros de Excel listos.
 * - Con varias empresas, la primera columna dice de cuál es cada una.
 * - Se escribe por partes, así que sirve para miles de PQRS sin llenar la
 *   memoria del servidor.
 * - Todo lo que escribió el ciudadano va como texto: un asunto como
 *   «=HYPERLINK(...)» no se vuelve una fórmula al abrir el archivo.
 */
final class PqrsExport
{
    private const MAX_CELL_LENGTH = 32000;

    /**
     * @param  list<Tenant>  $tenants
     * @param  (Closure(Builder<Pqrs>): mixed)|null  $scope  Restricción adicional (p. ej. solo lo que ve un usuario).
     */
    public function download(array $tenants, PqrsFilters $filters, string $fileName, ?User $viewer = null, ?Closure $scope = null): StreamedResponse
    {
        return response()->streamDownload(
            fn () => $this->write($tenants, $filters, 'php://output', $viewer, $scope),
            $fileName,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, private',
            ],
        );
    }

    /**
     * Escribe el archivo y devuelve cuántas PQRS quedaron en él.
     *
     * @param  list<Tenant>  $tenants
     * @param  (Closure(Builder<Pqrs>): mixed)|null  $scope
     */
    public function write(array $tenants, PqrsFilters $filters, string $path, ?User $viewer = null, ?Closure $scope = null): int
    {
        $withCompany = count($tenants) > 1;
        $withHealth = collect($tenants)->contains(fn (Tenant $t) => ! empty($t->resolvedFormSchema()['health_data']));
        $columns = $this->columns($withCompany, $withHealth);

        $app = (string) config('app.name');
        $options = new Options(properties: new Properties(
            title: 'PQRS',
            application: $app,
            creator: $app,
            lastModifiedBy: $app,
            language: 'es-CO',
        ));

        foreach ($columns as $index => $column) {
            $options->setColumnWidth($column['width'], $index + 1);
        }

        $writer = new Writer($options);
        $writer->openToFile($path);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('PQRS');
        $sheet->setSheetView(new SheetView(freezeRow: 2));

        $header = new Style(
            fontBold: true,
            fontColor: 'FFFFFF',
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: true,
            backgroundColor: '5B1A24',
        );
        $writer->addRow(new Row(array_map(fn (array $c) => new StringCell($c['label'], $header), $columns), 30));

        $styles = [
            'datetime' => new Style(format: 'dd/mm/yyyy hh:mm'),
            'date' => new Style(format: 'dd/mm/yyyy'),
        ];

        $rows = 0;

        foreach ($tenants as $tenant) {
            $tenant->run(function () use ($tenant, $filters, $viewer, $scope, $columns, $styles, $writer, &$rows) {
                $schema = $tenant->resolvedFormSchema();
                /** @var array<string, string> $types */
                $types = collect(is_array($schema['types'] ?? null) ? $schema['types'] : [])->pluck('label', 'key')->all();

                $query = $filters->apply(Pqrs::query()->with(['sede:id,name', 'assignee:id,name']), $viewer);

                if ($scope !== null) {
                    $scope($query);
                }

                foreach ($query->lazyById(500) as $pqrs) {
                    $cells = array_map(
                        fn (array $c) => $this->cell($c['value']($pqrs, $tenant, $types), $styles[$c['format'] ?? ''] ?? null),
                        $columns,
                    );
                    $writer->addRow(new Row($cells));
                    $rows++;
                }
            });
        }

        $sheet->setAutoFilter(new AutoFilter(0, 1, max(0, count($columns) - 1), $rows + 1));
        $writer->close();

        return $rows;
    }

    /**
     * @return list<array{label: string, width: int|float, format?: string, value: Closure}>
     */
    private function columns(bool $withCompany, bool $withHealth): array
    {
        $columns = [];

        if ($withCompany) {
            $columns[] = ['label' => 'Empresa', 'width' => 24, 'value' => fn (Pqrs $p, Tenant $t) => $t->name];
        }

        array_push($columns,
            ['label' => 'Radicado', 'width' => 18, 'value' => fn (Pqrs $p) => $p->radicado],
            ['label' => 'Radicada el', 'width' => 17, 'format' => 'datetime', 'value' => fn (Pqrs $p) => $p->created_at],
            ['label' => 'Tipo', 'width' => 14, 'value' => fn (Pqrs $p, Tenant $t, array $types) => $types[$p->type] ?? $p->type],
            ['label' => 'Nivel de riesgo', 'width' => 14, 'value' => fn (Pqrs $p) => $p->risk_level?->label()],
            ['label' => 'Estado', 'width' => 13, 'value' => fn (Pqrs $p) => $p->status->label()],
            ['label' => 'Cumplimiento', 'width' => 16, 'value' => fn (Pqrs $p) => self::compliance($p)],
            ['label' => 'Sede', 'width' => 20, 'value' => fn (Pqrs $p) => $p->sede?->name],
            ['label' => 'Asunto', 'width' => 34, 'value' => fn (Pqrs $p) => $p->subject],
            ['label' => 'Descripción', 'width' => 60, 'value' => fn (Pqrs $p) => $p->description],
            ['label' => 'Solicitante', 'width' => 26, 'value' => fn (Pqrs $p) => $p->requester_name],
            ['label' => 'Tipo de documento', 'width' => 16, 'value' => fn (Pqrs $p) => PqrsFormRules::DOCUMENT_TYPES[$p->requester_document_type ?? ''] ?? $p->requester_document_type],
            ['label' => 'Documento', 'width' => 15, 'value' => fn (Pqrs $p) => $p->requester_document],
            ['label' => 'Correo', 'width' => 28, 'value' => fn (Pqrs $p) => $p->requester_email],
            ['label' => 'Teléfono', 'width' => 14, 'value' => fn (Pqrs $p) => $p->requester_phone],
        );

        if ($withHealth) {
            array_push($columns,
                ['label' => 'Quién recibió el servicio', 'width' => 18, 'value' => fn (Pqrs $p) => match ($p->requester_is_affected) {
                    true => 'El solicitante',
                    false => 'Otra persona',
                    null => null,
                }],
                ['label' => 'Parentesco', 'width' => 14, 'value' => fn (Pqrs $p) => $p->requester_relationship],
                ['label' => 'Paciente', 'width' => 26, 'value' => fn (Pqrs $p) => $p->affected_name],
                ['label' => 'Documento del paciente', 'width' => 20, 'value' => fn (Pqrs $p) => $p->affected_document
                    ? trim(($p->affected_document_type ?? '').' '.$p->affected_document)
                    : null],
                ['label' => 'Teléfono del paciente', 'width' => 16, 'value' => fn (Pqrs $p) => $p->affected_phone],
                ['label' => 'Correo del paciente', 'width' => 26, 'value' => fn (Pqrs $p) => $p->affected_email],
                ['label' => 'EPS', 'width' => 20, 'value' => fn (Pqrs $p) => $p->eps],
                ['label' => 'Régimen', 'width' => 16, 'value' => fn (Pqrs $p) => PqrsFormRules::HEALTH_REGIMES[$p->health_regime ?? ''] ?? $p->health_regime],
                ['label' => 'Servicio', 'width' => 22, 'value' => fn (Pqrs $p) => $p->service],
                ['label' => 'Fecha de atención', 'width' => 14, 'format' => 'date', 'value' => fn (Pqrs $p) => $p->attended_on],
            );
        }

        array_push($columns,
            ['label' => 'Otros datos', 'width' => 30, 'value' => fn (Pqrs $p) => self::extraFields($p)],
            ['label' => 'Responsable', 'width' => 22, 'value' => fn (Pqrs $p) => $p->assignee?->name],
            ['label' => 'Vence', 'width' => 12, 'format' => 'date', 'value' => fn (Pqrs $p) => $p->due_at],
            ['label' => 'Respondida el', 'width' => 17, 'format' => 'datetime', 'value' => fn (Pqrs $p) => $p->responded_at],
            ['label' => 'Cerrada el', 'width' => 17, 'format' => 'datetime', 'value' => fn (Pqrs $p) => $p->closed_at],
            ['label' => 'Canal', 'width' => 10, 'value' => fn (Pqrs $p) => $p->channel === 'web' ? 'Portal web' : $p->channel],
        );

        return $columns;
    }

    private function cell(string|DateTimeInterface|null $value, ?Style $style): Cell
    {
        if ($value instanceof DateTimeInterface) {
            return new DateTimeCell($value, $style);
        }

        if ($value === null || $value === '') {
            return new EmptyCell(null);
        }

        // Siempre texto, aunque empiece por «=»: nunca una fórmula.
        return new StringCell(mb_substr($value, 0, self::MAX_CELL_LENGTH));
    }

    /**
     * ¿Se respondió a tiempo?
     */
    private static function compliance(Pqrs $pqrs): string
    {
        if ($pqrs->due_at === null) {
            return 'Sin plazo';
        }

        if ($pqrs->responded_at !== null) {
            return $pqrs->responded_at->lte($pqrs->due_at) ? 'A tiempo' : 'Fuera de plazo';
        }

        if ($pqrs->status === PqrsStatus::Cerrada) {
            return 'Cerrada sin respuesta';
        }

        return $pqrs->due_at->isPast() ? 'Vencida' : 'En plazo';
    }

    /**
     * Campos adicionales del formulario de la empresa, en una sola celda.
     */
    private static function extraFields(Pqrs $pqrs): ?string
    {
        $parts = [];

        foreach ($pqrs->extra_fields ?? [] as $field) {
            $value = $field['value'] ?? null;
            $value = match (true) {
                is_bool($value) => $value ? 'Sí' : 'No',
                is_array($value) => implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $value)),
                is_scalar($value) => (string) $value,
                default => '',
            };

            if ($value !== '') {
                $parts[] = $field['label'].': '.$value;
            }
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
