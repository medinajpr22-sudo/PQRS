<?php

namespace App\Support;

use App\Enums\PqrsStatus;
use App\Models\Pqrs;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros de la lista de PQRS: los mismos para ver la lista y para
 * descargarla en Excel, en el panel de la empresa y en el del super
 * administrador.
 *
 * Todo llega por la URL, así que se toma solo lo que tenga la forma
 * esperada: un «?status[]=x» o una fecha inventada se ignoran en vez de
 * tumbar la página.
 */
final readonly class PqrsFilters
{
    public function __construct(
        public string $search = '',
        public string $status = '',
        public string $type = '',
        /** Id de una sede, o "none" = sin sede. */
        public string $sede = '',
        /** "me" = asignadas a quien consulta, "none" = sin responsable, o el id de un usuario. */
        public string $assigned = '',
        public bool $overdue = false,
        /** Radicadas desde / hasta (Y-m-d, ambos días incluidos). */
        public string $from = '',
        public string $to = '',
    ) {}

    public static function fromRequest(Request $request): self
    {
        $text = fn (string $key): string => is_string($value = $request->query($key)) ? trim($value) : '';
        $sede = $text('sede');
        $assigned = $text('assigned');

        return new self(
            search: mb_substr($text('search'), 0, 100),
            status: PqrsStatus::tryFrom($text('status')) !== null ? $text('status') : '',
            type: preg_match('/^[a-z0-9_-]{1,40}$/', $text('type')) === 1 ? $text('type') : '',
            sede: ctype_digit($sede) || $sede === 'none' ? $sede : '',
            assigned: ctype_digit($assigned) || in_array($assigned, ['me', 'none'], true) ? $assigned : '',
            overdue: $request->boolean('overdue'),
            from: self::date($text('from')) !== null ? $text('from') : '',
            to: self::date($text('to')) !== null ? $text('to') : '',
        );
    }

    /**
     * @param  Builder<Pqrs>  $query
     * @return Builder<Pqrs>
     */
    public function apply(Builder $query, ?User $viewer = null): Builder
    {
        $from = self::date($this->from);
        $to = self::date($this->to);

        return $query
            ->when(ctype_digit($this->sede), fn ($q) => $q->where('sede_id', (int) $this->sede))
            ->when($this->sede === 'none', fn ($q) => $q->whereNull('sede_id'))
            ->when($this->assigned === 'me' && $viewer !== null, fn ($q) => $q->where('assigned_to', $viewer?->id))
            ->when($this->assigned === 'none', fn ($q) => $q->whereNull('assigned_to'))
            ->when(ctype_digit($this->assigned), fn ($q) => $q->where('assigned_to', (int) $this->assigned))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->whereLike('radicado', "%{$this->search}%")
                ->orWhereLike('requester_name', "%{$this->search}%")
                ->orWhereLike('requester_email', "%{$this->search}%")
                ->orWhereLike('requester_document', "%{$this->search}%")
                ->orWhereLike('affected_name', "%{$this->search}%")
                ->orWhereLike('affected_document', "%{$this->search}%")
                ->orWhereLike('subject', "%{$this->search}%")))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->type !== '', fn ($q) => $q->where('type', $this->type))
            ->when($this->overdue, fn ($q) => $q->overdue())
            ->when($from !== null, fn ($q) => $q->where('created_at', '>=', $from?->startOfDay()))
            ->when($to !== null, fn ($q) => $q->where('created_at', '<=', $to?->endOfDay()));
    }

    /**
     * Los mismos filtros sin la sede (las sedes son de cada empresa).
     */
    public function withoutSede(): self
    {
        return new self($this->search, $this->status, $this->type, '', $this->assigned, $this->overdue, $this->from, $this->to);
    }

    /**
     * @return array{search: string, status: string, type: string, sede: string, assigned: string, overdue: bool, from: string, to: string}
     */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'status' => $this->status,
            'type' => $this->type,
            'sede' => $this->sede,
            'assigned' => $this->assigned,
            'overdue' => $this->overdue,
            'from' => $this->from,
            'to' => $this->to,
        ];
    }

    private static function date(string $value): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== null && $date->format('Y-m-d') === $value ? $date : null;
    }
}
