<?php

namespace App\Services;

use App\Enums\PqrsStatus;
use App\Models\Pqrs;
use App\Models\PqrsActivity;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PqrsReceivedNotification;
use App\Notifications\PqrsRespondedNotification;
use App\Support\BusinessDays;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

class PqrsService
{
    /**
     * Radica una nueva PQRS desde el portal público.
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

        $pqrs = $this->createWithUniqueRadicado($tenant, [
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

        $this->storeAttachments($pqrs, $files, $activity);

        $this->notify($pqrs->requester_email, new PqrsReceivedNotification($pqrs, $tenant));

        return $pqrs;
    }

    public function changeStatus(Pqrs $pqrs, PqrsStatus $status, User $user): void
    {
        if ($pqrs->status === $status) {
            return;
        }

        $from = $pqrs->status;

        $pqrs->status = $status;
        $pqrs->closed_at = $status === PqrsStatus::Cerrada ? now() : null;
        $pqrs->save();

        $pqrs->activities()->create([
            'user_id' => $user->id,
            'type' => PqrsActivity::TYPE_STATUS,
            'body' => "Estado cambiado de «{$from->label()}» a «{$status->label()}».",
            'is_public' => true,
            'meta' => ['from' => $from->value, 'to' => $status->value],
        ]);
    }

    /**
     * Agrega una nota interna o una respuesta al ciudadano.
     *
     * @param  list<UploadedFile>  $files
     */
    public function addActivity(Tenant $tenant, Pqrs $pqrs, User $user, string $type, string $body, array $files = []): PqrsActivity
    {
        $isResponse = $type === PqrsActivity::TYPE_RESPONSE;

        $activity = DB::transaction(function () use ($pqrs, $user, $type, $body, $isResponse, $files) {
            $activity = $pqrs->activities()->create([
                'user_id' => $user->id,
                'type' => $type,
                'body' => $body,
                'is_public' => $isResponse,
            ]);

            $this->storeAttachments($pqrs, $files, $activity);

            if ($isResponse) {
                $pqrs->forceFill([
                    'status' => PqrsStatus::Respondida,
                    'responded_at' => $pqrs->responded_at ?? now(),
                ])->save();
            } elseif ($pqrs->status === PqrsStatus::Radicada) {
                $pqrs->forceFill(['status' => PqrsStatus::EnTramite])->save();
            }

            return $activity;
        });

        if ($isResponse) {
            $this->notify($pqrs->requester_email, new PqrsRespondedNotification($pqrs, $activity, $tenant));
        }

        return $activity;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createWithUniqueRadicado(Tenant $tenant, array $attributes): Pqrs
    {
        $year = now()->year;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return DB::transaction(function () use ($tenant, $attributes, $year) {
                    $last = Pqrs::where('radicado', 'like', "{$tenant->radicado_prefix}-{$year}-%")
                        ->orderByDesc('radicado')
                        ->value('radicado');

                    $sequence = $last ? ((int) substr($last, strrpos($last, '-') + 1)) + 1 : 1;

                    return Pqrs::create([
                        ...$attributes,
                        'radicado' => sprintf('%s-%d-%05d', $tenant->radicado_prefix, $year, $sequence),
                    ]);
                });
            } catch (QueryException $e) {
                // Choque de radicado por concurrencia: reintentar con el siguiente consecutivo.
                if ($attempt === 4) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('No fue posible generar el número de radicado.');
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    protected function storeAttachments(Pqrs $pqrs, array $files, ?PqrsActivity $activity = null): void
    {
        foreach ($files as $file) {
            $path = $file->store("pqrs/{$pqrs->radicado}", 'local');

            $pqrs->attachments()->create([
                'activity_id' => $activity?->id,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }

    protected function notify(string $email, object $notification): void
    {
        try {
            Notification::route('mail', $email)->notify($notification);
        } catch (Throwable $e) {
            // Un fallo del correo no debe impedir la radicación.
            report($e);
        }
    }
}
