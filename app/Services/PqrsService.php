<?php

namespace App\Services;

use App\Enums\PqrsStatus;
use App\Enums\PqrsType;
use App\Events\PqrsArrivedAtSede;
use App\Models\Pqrs;
use App\Models\PqrsActivity;
use App\Models\Sede;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PqrsAssignedNotification;
use App\Notifications\PqrsReceivedNotification;
use App\Notifications\PqrsRespondedNotification;
use App\Support\BusinessDays;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class PqrsService
{
    /**
     * ¿El último submit() le envió el acuse por correo al ciudadano? No, si
     * esa dirección ya recibió el máximo de acuses del día (mayAcknowledge).
     */
    public bool $acknowledged = false;

    /**
     * Radica una nueva PQRS desde el portal público.
     *
     * Es "todo o nada": si falla el historial o algún adjunto, no queda una
     * PQRS a medias ni archivos sueltos en el disco.
     *
     * @param  array<string, mixed>  $data  Datos validados
     * @param  list<UploadedFile>  $files
     */
    public function submit(Tenant $tenant, array $data, array $files = [], ?string $ip = null): Pqrs
    {
        $schema = $tenant->resolvedFormSchema();
        $type = collect($schema['types'])->firstWhere('key', $data['type']);

        $extra = collect($schema['fields'])->map(fn (array $field) => [
            'key' => $field['key'],
            'label' => $field['label'],
            'type' => $field['type'],
            'value' => $data['fields'][$field['key']] ?? null,
        ])->values()->all();

        /** @var Pqrs $pqrs */
        $pqrs = $this->transactionWithFiles(function (array &$stored) use ($tenant, $schema, $data, $type, $extra, $ip, $files) {
            $pqrs = Pqrs::create([
                'radicado' => $this->nextRadicado($tenant),
                'type' => $data['type'],
                'sede_id' => $data['sede_id'] ?? null,
                // Solo los reclamos llevan nivel de riesgo.
                'risk_level' => $data['type'] === PqrsType::Reclamo->value && ! empty($schema['risk_levels'])
                    ? ($data['risk_level'] ?? null)
                    : null,
                'status' => PqrsStatus::Radicada,
                'channel' => 'web',
                'requester_name' => $data['requester_name'],
                'requester_document_type' => $data['requester_document_type'] ?? null,
                'requester_document' => $data['requester_document'] ?? null,
                'requester_email' => Str::lower($data['requester_email']),
                'requester_phone' => $data['requester_phone'] ?? null,
                // Datos de salud (solo si la empresa los pide). Si el
                // solicitante es el afectado, los datos del afectado no
                // llegan: la validación los descarta.
                'requester_is_affected' => isset($data['requester_is_affected']) ? (bool) $data['requester_is_affected'] : null,
                'requester_relationship' => $data['requester_relationship'] ?? null,
                'affected_name' => $data['affected_name'] ?? null,
                'affected_document_type' => $data['affected_document_type'] ?? null,
                'affected_document' => $data['affected_document'] ?? null,
                'affected_phone' => $data['affected_phone'] ?? null,
                'affected_email' => isset($data['affected_email']) ? Str::lower($data['affected_email']) : null,
                'eps' => $data['eps'] ?? null,
                'health_regime' => $data['health_regime'] ?? null,
                'service' => $data['service'] ?? null,
                'attended_on' => $data['attended_on'] ?? null,
                'subject' => $data['subject'],
                'description' => $data['description'],
                'extra_fields' => $extra,
                'due_at' => BusinessDays::add(now(), (int) ($type['sla_days'] ?? 15)),
                'ip_address' => $ip,
            ]);

            $activity = $pqrs->activities()->create([
                'type' => PqrsActivity::TYPE_CREATED,
                'body' => 'Solicitud radicada a través del portal web.',
                'is_public' => true,
            ]);

            $this->storeAttachments($pqrs, $files, $activity, $stored);

            return $pqrs;
        });

        $this->acknowledged = $this->mayAcknowledge($pqrs->requester_email);

        if ($this->acknowledged) {
            $this->safely(fn () => Notification::route('mail', $pqrs->requester_email)
                ->notify(new PqrsReceivedNotification($pqrs, $tenant)));
        }

        // Avisa al equipo de la sede (ver NotifySedeAgents).
        PqrsArrivedAtSede::dispatch($pqrs, $tenant);

        return $pqrs;
    }

    /**
     * Cambio de estado hecho a mano por el personal.
     *
     * Solo se permiten los cambios definidos en PqrsStatus::manualTransitions().
     * Reabrir o cerrar sin respuesta exige un motivo. Si la solicitud tiene
     * responsable, solo él o un administrador pueden cambiar su estado.
     */
    public function changeStatus(Pqrs $pqrs, PqrsStatus $status, User $user, ?string $reason = null): void
    {
        $reason = filled($reason) ? trim((string) $reason) : null;

        DB::transaction(function () use ($pqrs, $status, $user, $reason) {
            // Se relee bloqueada: si alguien la cambió mientras tanto, las
            // reglas se revisan contra el estado real y no contra la pantalla.
            $this->lockAndRefresh($pqrs);

            $from = $pqrs->status;

            if ($from === $status) {
                return;
            }

            $this->ensureInCharge($pqrs, $user, 'status', 'cambiar su estado');

            if (! $from->canTransitionTo($status)) {
                throw ValidationException::withMessages([
                    'status' => $status === PqrsStatus::Respondida
                        ? 'Para marcarla como «Respondida» envía una respuesta al ciudadano.'
                        : "No es posible pasar de «{$from->label()}» a «{$status->label()}».",
                ]);
            }

            if ($from->requiresReasonTo($status) && $reason === null) {
                throw ValidationException::withMessages([
                    'reason' => 'Indica el motivo de este cambio de estado.',
                ]);
            }

            $this->transition($pqrs, $status, $user, $reason);

            // Al reabrirla, si su responsable ya no puede atenderla (usuario
            // desactivado o sin la sede), queda libre para que otro la tome.
            if ($status->isOpen() && ! $from->isOpen()) {
                $this->releaseIfUnavailable($pqrs, $user);
            }
        });
    }

    /**
     * Agrega una nota interna o una respuesta al ciudadano.
     *
     * Solo el responsable (o un administrador) responde. Quien no lo es puede
     * asumirla con $take: asumir y responder van juntos, así que si la
     * respuesta no se puede enviar tampoco cambia el responsable.
     *
     * @param  list<UploadedFile>  $files
     */
    public function addActivity(Tenant $tenant, Pqrs $pqrs, User $user, string $type, string $body, array $files = [], bool $take = false): PqrsActivity
    {
        $isResponse = $type === PqrsActivity::TYPE_RESPONSE;

        /** @var PqrsActivity $activity */
        $activity = $this->transactionWithFiles(function (array &$stored) use ($pqrs, $user, $type, $body, $isResponse, $files, $take) {
            $this->lockAndRefresh($pqrs);

            if ($isResponse) {
                $this->ensureCanRespond($pqrs, $user, $take);
            }

            $activity = $pqrs->activities()->create([
                'user_id' => $user->id,
                'type' => $type,
                'body' => $body,
                'is_public' => $isResponse,
            ]);

            $this->storeAttachments($pqrs, $files, $activity, $stored);

            if ($isResponse) {
                // Se conserva la fecha de la primera respuesta (es la que cuenta para el plazo).
                $pqrs->responded_at ??= now();
                $this->transition($pqrs, PqrsStatus::Respondida, $user);
                $pqrs->save();
            } elseif ($pqrs->status === PqrsStatus::Radicada && $this->isInCharge($pqrs, $user)) {
                // Quien la atiende empezó a trabajarla: queda «En trámite» y se
                // registra en el historial. La nota de un compañero que no es el
                // responsable no le cambia el estado (eso solo lo hace él o un
                // administrador).
                $this->transition($pqrs, PqrsStatus::EnTramite, $user);
            }

            return $activity;
        });

        if ($isResponse) {
            $this->safely(fn () => Notification::route('mail', $pqrs->requester_email)
                ->notify(new PqrsRespondedNotification($pqrs, $activity, $tenant)));
        }

        return $activity;
    }

    /**
     * Asigna (o quita) el responsable de una PQRS.
     *
     * Un administrador puede asignar a cualquiera que tenga acceso a la sede
     * de la PQRS. Un agente solo puede tomar una solicitud libre o soltar la suya.
     */
    public function assign(Tenant $tenant, Pqrs $pqrs, ?User $assignee, User $by): void
    {
        $changed = DB::transaction(function () use ($pqrs, $assignee, $by) {
            // Bloqueada: si dos agentes la toman a la vez, el segundo ve que
            // ya tiene responsable en lugar de quitársela al primero.
            $this->lockAndRefresh($pqrs);
            $current = $pqrs->assignee;

            if ($current?->id === $assignee?->id) {
                return false;
            }

            if (! $by->isAdmin()) {
                // Libre también si su responsable ya no puede atenderla
                // (desactivado o sin la sede): si no, quedaría trabada.
                $takingFree = $assignee?->id === $by->id && ! $pqrs->hasAvailableAssignee();
                $releasingOwn = $assignee === null && $current?->id === $by->id;

                if (! $takingFree && ! $releasingOwn) {
                    throw ValidationException::withMessages([
                        'assigned_to' => $current !== null && $assignee?->id === $by->id
                            ? "{$current->name} acaba de tomar esta solicitud."
                            : 'Solo un administrador puede asignar o reasignar solicitudes de otros agentes.',
                    ]);
                }
            }

            if ($assignee !== null && ! $assignee->is_active) {
                throw ValidationException::withMessages([
                    'assigned_to' => 'Ese usuario está desactivado.',
                ]);
            }

            if ($assignee !== null && ! $assignee->canAccessSede($pqrs->sede_id)) {
                throw ValidationException::withMessages([
                    'assigned_to' => "{$assignee->name} no tiene acceso a la sede de esta solicitud.",
                ]);
            }

            $this->recordAssignment($pqrs, $assignee, $by);

            return true;
        });

        // Solo se avisa si queda trabajo por hacer: asignar una solicitud ya
        // respondida o cerrada es para dejar constancia.
        if ($changed && $assignee !== null && $assignee->id !== $by->id && $pqrs->status->isOpen()) {
            $this->safely(fn () => $assignee->notify(new PqrsAssignedNotification($pqrs, $tenant, $by)));
        }
    }

    /**
     * Traslada la PQRS a otra sede (p. ej. el paciente se equivocó al radicar).
     *
     * Lo puede hacer el administrador, su responsable o, si está libre,
     * cualquier agente que la vea; hacia cualquier sede activa de la empresa,
     * aunque él no la tenga asignada (en ese caso deja de verla). Si el
     * responsable actual no tiene acceso a la nueva sede, queda sin
     * responsable: por eso un compañero no puede trasladar una solicitud
     * ajena (sería una forma de quitársela a su responsable).
     */
    public function changeSede(Pqrs $pqrs, Sede $sede, User $by, ?string $reason = null): void
    {
        if (! $sede->is_active) {
            throw ValidationException::withMessages([
                'sede_id' => "La sede «{$sede->name}» está desactivada.",
            ]);
        }

        $reason = filled($reason) ? trim((string) $reason) : null;

        $moved = DB::transaction(function () use ($pqrs, $sede, $by, $reason) {
            $this->lockAndRefresh($pqrs);
            $from = $pqrs->sede;

            if ($from?->id === $sede->id) {
                return false;
            }

            $this->ensureInCharge($pqrs, $by, 'sede_id', 'trasladarla de sede');

            $body = $from === null
                ? "Solicitud asignada a la sede «{$sede->name}»."
                : "Solicitud trasladada de la sede «{$from->name}» a «{$sede->name}».";

            if ($reason !== null) {
                $body .= "\nMotivo: {$reason}";
            }

            $pqrs->forceFill(['sede_id' => $sede->id])->save();
            $pqrs->setRelation('sede', $sede);

            $pqrs->activities()->create([
                'user_id' => $by->id,
                'type' => PqrsActivity::TYPE_SEDE,
                'body' => $body,
                'is_public' => false,
                'meta' => ['from' => $from?->id, 'to' => $sede->id, 'reason' => $reason],
            ]);

            $assignee = $pqrs->assignee;

            if ($assignee !== null && ! $assignee->canAccessSede($sede->id)) {
                $this->unassign($pqrs, $by, "Se quitó la asignación a {$assignee->name}: no tiene acceso a la sede «{$sede->name}».");
            }

            return true;
        });

        // Se avisa a la sede nueva solo si la solicitud sigue abierta.
        if ($moved && $pqrs->status->isOpen()) {
            /** @var Tenant $tenant */
            $tenant = tenant();
            PqrsArrivedAtSede::dispatch($pqrs, $tenant, $by);
        }
    }

    /**
     * Quita a un usuario desactivado de las PQRS abiertas que tenía a cargo,
     * para que otra persona de la sede las tome. Las respondidas o cerradas
     * conservan su responsable; si se reabren, quedan libres (ver changeStatus).
     *
     * @return int Cuántas solicitudes quedaron sin responsable
     */
    public function releaseInactiveAssignments(User $user, User $by): int
    {
        if ($user->is_active) {
            return 0;
        }

        $lost = Pqrs::open()->where('assigned_to', $user->id)->get();

        foreach ($lost as $pqrs) {
            $this->unassign($pqrs, $by, "Se quitó la asignación a {$user->name}: su usuario fue desactivado.");
        }

        return $lost->count();
    }

    /**
     * Quita a un usuario de las PQRS abiertas que ya no puede ver (p. ej. un
     * agente al que le quitaron una sede, o un administrador que pasó a agente).
     *
     * @return int Cuántas solicitudes quedaron sin responsable
     */
    public function releaseInaccessibleAssignments(User $user, User $by): int
    {
        $user->unsetRelation('sedes');
        $ids = $user->accessibleSedeIds();

        if ($ids === null) {
            return 0;
        }

        $lost = Pqrs::open()
            ->with('sede')
            ->where('assigned_to', $user->id)
            ->where(fn ($q) => $q->whereNull('sede_id')->orWhereNotIn('sede_id', $ids))
            ->get();

        foreach ($lost as $pqrs) {
            $sede = $pqrs->sede?->name;

            $this->unassign($pqrs, $by, $sede === null
                ? "Se quitó la asignación a {$user->name}: la solicitud no tiene sede y solo la ven los administradores."
                : "Se quitó la asignación a {$user->name}: ya no tiene acceso a la sede «{$sede}».");
        }

        return $lost->count();
    }

    /**
     * Deja la PQRS sin responsable y lo registra en el historial interno.
     */
    protected function unassign(Pqrs $pqrs, User $by, string $body): void
    {
        $previous = $pqrs->assigned_to;

        $pqrs->forceFill(['assigned_to' => null])->save();
        $pqrs->setRelation('assignee', null);

        $pqrs->activities()->create([
            'user_id' => $by->id,
            'type' => PqrsActivity::TYPE_ASSIGNED,
            'body' => $body,
            'is_public' => false,
            'meta' => ['from' => $previous, 'to' => null],
        ]);
    }

    /**
     * Relee la PQRS bloqueando su fila hasta que termine la transacción: si
     * dos personas actúan a la vez sobre la misma solicitud, van en fila.
     */
    protected function lockAndRefresh(Pqrs $pqrs): void
    {
        Pqrs::query()->whereKey($pqrs->getKey())->lockForUpdate()->first();
        $pqrs->refresh();
    }

    /**
     * Si la PQRS tiene responsable, solo él o un administrador actúan sobre ella.
     */
    protected function ensureInCharge(Pqrs $pqrs, User $user, string $field, string $action): void
    {
        if ($this->isInCharge($pqrs, $user)) {
            return;
        }

        throw ValidationException::withMessages([
            $field => 'Esta solicitud está a cargo de '.($pqrs->assignee->name ?? 'otra persona').". Solo esa persona o un administrador pueden {$action}.",
        ]);
    }

    /**
     * Ver Pqrs::canBeHandledBy(): el responsable, un administrador o, si
     * nadie la puede estar atendiendo, cualquiera que la vea.
     */
    protected function isInCharge(Pqrs $pqrs, User $user): bool
    {
        return $pqrs->canBeHandledBy($user);
    }

    /**
     * Reglas para responder al ciudadano. Con $take, quien responde queda
     * como responsable (en la misma transacción que la respuesta).
     */
    protected function ensureCanRespond(Pqrs $pqrs, User $user, bool $take): void
    {
        if ($pqrs->status === PqrsStatus::Cerrada) {
            throw ValidationException::withMessages([
                'body' => 'La solicitud está cerrada. Reábrela (con un motivo) antes de enviar una nueva respuesta.',
            ]);
        }

        if ($pqrs->assigned_to === $user->id) {
            return;
        }

        $this->ensureInCharge($pqrs, $user, 'body', 'responderla');

        if (! $take) {
            throw ValidationException::withMessages([
                'body' => 'Para responder debes ser el responsable de esta solicitud.',
            ]);
        }

        $this->recordAssignment($pqrs, $user, $user);
    }

    /**
     * Cambia el responsable y lo deja en el historial interno (sin validar).
     */
    protected function recordAssignment(Pqrs $pqrs, ?User $assignee, User $by): void
    {
        $current = $pqrs->assignee;

        $body = match (true) {
            $assignee === null => 'Se quitó la asignación a '.($current->name ?? '—').'.',
            $current === null => "Solicitud asignada a {$assignee->name}.",
            default => "Solicitud reasignada de {$current->name} a {$assignee->name}.",
        };

        $pqrs->forceFill(['assigned_to' => $assignee?->id])->save();
        $pqrs->setRelation('assignee', $assignee);

        $pqrs->activities()->create([
            'user_id' => $by->id,
            'type' => PqrsActivity::TYPE_ASSIGNED,
            'body' => $body,
            'is_public' => false,
            'meta' => ['from' => $current?->id, 'to' => $assignee?->id],
        ]);
    }

    /**
     * Si el responsable ya no puede atender la PQRS (desactivado o sin
     * acceso a su sede), la deja sin responsable.
     */
    protected function releaseIfUnavailable(Pqrs $pqrs, User $by): void
    {
        $assignee = $pqrs->assignee;

        if ($assignee === null) {
            return;
        }

        if (! $assignee->is_active) {
            $this->unassign($pqrs, $by, "Se quitó la asignación a {$assignee->name}: su usuario está desactivado.");
        } elseif (! $assignee->canAccessSede($pqrs->sede_id)) {
            $this->unassign($pqrs, $by, "Se quitó la asignación a {$assignee->name}: ya no tiene acceso a la sede de esta solicitud.");
        }
    }

    /**
     * Aplica un cambio de estado y lo deja en el historial (visible para el ciudadano).
     * No valida reglas: eso lo hace changeStatus() para los cambios manuales.
     */
    protected function transition(Pqrs $pqrs, PqrsStatus $to, ?User $user, ?string $reason = null): void
    {
        $from = $pqrs->status;

        if ($from === $to) {
            return;
        }

        $pqrs->status = $to;

        if ($to === PqrsStatus::Cerrada) {
            $pqrs->closed_at = now();
        } elseif ($from === PqrsStatus::Cerrada) {
            $pqrs->closed_at = null;
        }

        $pqrs->save();

        $body = "Estado cambiado de «{$from->label()}» a «{$to->label()}».";

        if ($reason !== null) {
            $body .= "\nMotivo: {$reason}";
        }

        $pqrs->activities()->create([
            'user_id' => $user?->id,
            'type' => PqrsActivity::TYPE_STATUS,
            'body' => $body,
            'is_public' => true,
            'meta' => ['from' => $from->value, 'to' => $to->value, 'reason' => $reason],
        ]);
    }

    /**
     * Siguiente número de radicado: PREFIJO-AÑO-00001.
     *
     * Usa un contador por prefijo y año (tabla pqrs_sequences). La fila queda
     * bloqueada por el UPDATE hasta que termina la transacción, así dos
     * radicaciones simultáneas nunca reciben el mismo número y un radicado
     * nunca se reutiliza (aunque se borre una PQRS).
     */
    protected function nextRadicado(Tenant $tenant): string
    {
        $scope = $tenant->radicado_prefix.'-'.now()->year;
        $counter = fn () => DB::table('pqrs_sequences')->where('scope', $scope);

        if ($counter()->increment('last_value', 1, ['updated_at' => now()]) === 0) {
            // Primer radicado del año, o empresa que venía de antes del contador:
            // arrancamos desde el mayor consecutivo que ya exista.
            DB::table('pqrs_sequences')->insertOrIgnore([
                'scope' => $scope,
                'last_value' => $this->highestExistingSequence($scope),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $counter()->increment('last_value', 1, ['updated_at' => now()]);
        }

        return sprintf('%s-%05d', $scope, (int) $counter()->value('last_value'));
    }

    /**
     * Mayor consecutivo usado con ese prefijo y año (comparando números, no texto).
     */
    protected function highestExistingSequence(string $scope): int
    {
        return (int) Pqrs::where('radicado', 'like', $scope.'-%')
            ->pluck('radicado')
            ->map(fn (string $radicado) => (int) Str::afterLast($radicado, '-'))
            ->max();
    }

    /**
     * Ejecuta $callback en una transacción. Si algo falla, borra del disco los
     * archivos que alcanzaron a guardarse para no dejar basura.
     *
     * @param  Closure(array<int, string>&): mixed  $callback
     */
    protected function transactionWithFiles(Closure $callback): mixed
    {
        $stored = [];

        try {
            return DB::transaction(function () use ($callback, &$stored) {
                return $callback($stored);
            });
        } catch (Throwable $e) {
            if ($stored !== []) {
                Storage::disk('local')->delete($stored);
            }

            throw $e;
        }
    }

    /**
     * @param  list<UploadedFile>  $files
     * @param  array<int, string>  $stored  Rutas guardadas (para limpiar si algo falla)
     */
    protected function storeAttachments(Pqrs $pqrs, array $files, PqrsActivity $activity, array &$stored): void
    {
        foreach ($files as $file) {
            $path = $file->store("pqrs/{$pqrs->radicado}", 'local');

            if ($path === false) {
                throw new RuntimeException("No fue posible guardar el archivo «{$file->getClientOriginalName()}».");
            }

            $stored[] = $path;

            $pqrs->attachments()->create([
                'activity_id' => $activity->id,
                // El nombre lo manda el navegador: se recorta al tamaño de la columna.
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'path' => $path,
                // El tipo se detecta en el servidor (el del navegador se puede falsear).
                'mime_type' => mb_substr((string) $file->getMimeType(), 0, 120),
                'size' => $file->getSize(),
            ]);
        }
    }

    /**
     * Un fallo del correo (o de la cola) nunca debe impedir la radicación ni la respuesta.
     */
    /**
     * Tope diario de acuses de recibo por dirección (todas las empresas).
     *
     * Un robot que pase el «No soy un robot» podría radicar muchas veces con
     * el correo de otra persona para llenarle el buzón; si esa persona los
     * marca como spam, Outlook o Gmail bloquean el buzón que envía. La PQRS
     * se radica igual y el radicado se ve en pantalla.
     */
    protected function mayAcknowledge(string $email): bool
    {
        $key = 'pqrs-ack|'.hash('sha256', mb_strtolower(trim($email)));

        return RateLimiter::attempt($key, max(1, (int) config('pqrs.mail.acks_per_address')), fn () => true, 86400) === true;
    }

    protected function safely(Closure $send): void
    {
        try {
            $send();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
