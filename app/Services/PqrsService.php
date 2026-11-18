<?php

namespace App\Services;

use App\Enums\PqrsStatus;
use App\Models\Pqrs;
use App\Models\PqrsActivity;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class PqrsService
{
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
        $pqrs = $this->transactionWithFiles(function (array &$stored) use ($tenant, $data, $type, $extra, $ip, $files) {
            $pqrs = Pqrs::create([
                'radicado' => $this->nextRadicado($tenant),
                'type' => $data['type'],
                'status' => PqrsStatus::Radicada,
                'channel' => 'web',
                'requester_name' => $data['requester_name'],
                'requester_document_type' => $data['requester_document_type'] ?? null,
                'requester_document' => $data['requester_document'] ?? null,
                'requester_email' => Str::lower($data['requester_email']),
                'requester_phone' => $data['requester_phone'] ?? null,
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

        $this->safely(fn () => Notification::route('mail', $pqrs->requester_email)
            ->notify(new PqrsReceivedNotification($pqrs, $tenant)));

        return $pqrs;
    }

    /**
     * Cambio de estado hecho a mano por un funcionario.
     *
     * Solo se permiten los cambios definidos en PqrsStatus::manualTransitions().
     * Reabrir o cerrar sin respuesta exige un motivo.
     */
    public function changeStatus(Pqrs $pqrs, PqrsStatus $status, User $user, ?string $reason = null): void
    {
        $from = $pqrs->status;

        if ($from === $status) {
            return;
        }

        if (! $from->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'status' => $status === PqrsStatus::Respondida
                    ? 'Para marcarla como «Respondida» envía una respuesta al ciudadano.'
                    : "No es posible pasar de «{$from->label()}» a «{$status->label()}».",
            ]);
        }

        $reason = filled($reason) ? trim((string) $reason) : null;

        if ($from->requiresReasonTo($status) && $reason === null) {
            throw ValidationException::withMessages([
                'reason' => 'Indica el motivo de este cambio de estado.',
            ]);
        }

        DB::transaction(fn () => $this->transition($pqrs, $status, $user, $reason));
    }

    /**
     * Agrega una nota interna o una respuesta al ciudadano.
     *
     * @param  list<UploadedFile>  $files
     */
    public function addActivity(Tenant $tenant, Pqrs $pqrs, User $user, string $type, string $body, array $files = []): PqrsActivity
    {
        $isResponse = $type === PqrsActivity::TYPE_RESPONSE;

        if ($isResponse && $pqrs->status === PqrsStatus::Cerrada) {
            throw ValidationException::withMessages([
                'body' => 'La solicitud está cerrada. Reábrela (con un motivo) antes de enviar una nueva respuesta.',
            ]);
        }

        /** @var PqrsActivity $activity */
        $activity = $this->transactionWithFiles(function (array &$stored) use ($pqrs, $user, $type, $body, $isResponse, $files) {
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
            } elseif ($pqrs->status === PqrsStatus::Radicada) {
                // Alguien empezó a trabajarla: queda «En trámite» y se registra en el historial.
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
     * Asigna (o quita) el funcionario responsable de una PQRS.
     *
     * Un administrador puede asignar a cualquiera. Un funcionario solo puede
     * tomar una solicitud libre o soltar la suya.
     */
    public function assign(Tenant $tenant, Pqrs $pqrs, ?User $assignee, User $by): void
    {
        $current = $pqrs->assignee;

        if ($current?->id === $assignee?->id) {
            return;
        }

        if (! $by->isAdmin()) {
            $takingFree = $assignee?->id === $by->id && $current === null;
            $releasingOwn = $assignee === null && $current?->id === $by->id;

            if (! $takingFree && ! $releasingOwn) {
                throw ValidationException::withMessages([
                    'assigned_to' => 'Solo un administrador puede asignar o reasignar solicitudes de otros funcionarios.',
                ]);
            }
        }

        if ($assignee !== null && ! $assignee->is_active) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Ese funcionario está desactivado.',
            ]);
        }

        $body = match (true) {
            $assignee === null => 'Se quitó la asignación a '.($current->name ?? '—').'.',
            $current === null => "Solicitud asignada a {$assignee->name}.",
            default => "Solicitud reasignada de {$current->name} a {$assignee->name}.",
        };

        DB::transaction(function () use ($pqrs, $assignee, $current, $by, $body) {
            $pqrs->forceFill(['assigned_to' => $assignee?->id])->save();
            $pqrs->setRelation('assignee', $assignee);

            $pqrs->activities()->create([
                'user_id' => $by->id,
                'type' => PqrsActivity::TYPE_ASSIGNED,
                'body' => $body,
                'is_public' => false,
                'meta' => ['from' => $current?->id, 'to' => $assignee?->id],
            ]);
        });

        if ($assignee !== null && $assignee->id !== $by->id) {
            $this->safely(fn () => $assignee->notify(new PqrsAssignedNotification($pqrs, $tenant, $by)));
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
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }

    /**
     * Un fallo del correo (o de la cola) nunca debe impedir la radicación ni la respuesta.
     */
    protected function safely(Closure $send): void
    {
        try {
            $send();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
