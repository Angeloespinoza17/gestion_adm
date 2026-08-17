<?php

namespace App\Services\Operational;

use App\Models\Operational\OperationalTransferQuote;
use App\Models\Operational\OperationalTransferRequest;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\OperationalTransferNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Throwable;

class OperationalTransferWorkflowService
{
    public function __construct(private readonly OperationalTransferPdfService $pdfService) {}

    public function saveDraft(OperationalTransferRequest $transfer, array $payload, User $actor, bool $isNew): OperationalTransferRequest
    {
        if (! $isNew && ! $transfer->isEditable() && ! $actor->hasPermission('gestionar_traslados_operativos') && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages(['approval_status' => 'La solicitud ya no puede editarse en su estado actual.']);
        }

        return DB::transaction(function () use ($transfer, $payload, $actor, $isNew): OperationalTransferRequest {
            $staff = Staff::query()
                ->with(['cargo:id,name', 'departments:id,name', 'organigramRelations.relatedStaff.user:id,name,email,staff_id'])
                ->findOrFail($payload['requester_staff_id']);
            $visor = $this->resolveVisor($staff);
            $studentCount = (int) ($payload['student_count'] ?? 0);
            $adultCount = (int) ($payload['adult_count'] ?? 0);

            $transfer->fill([
                'folio' => $transfer->folio ?: $this->nextFolio(),
                'requester_staff_id' => $staff->id,
                'requested_by_user_id' => $transfer->requested_by_user_id ?: $actor->id,
                'visor_user_id' => $visor?->id,
                'created_by' => $isNew ? $actor->id : $transfer->created_by,
                'updated_by' => $actor->id,
                'requester_name_snapshot' => $staff->full_name,
                'requester_role_snapshot' => $staff->cargo?->name,
                'requester_unit_snapshot' => $staff->departments->pluck('name')->implode(', '),
                'visor_name_snapshot' => $visor?->staff?->full_name ?: $visor?->name,
                'activity_type' => $payload['activity_type'],
                'activity_name' => $payload['activity_name'],
                'course_subject' => $payload['course_subject'] ?? null,
                'purpose' => $payload['purpose'] ?? null,
                'transport_date' => $payload['transport_date'],
                'departure_time' => $payload['departure_time'],
                'return_time' => $payload['return_time'] ?? null,
                'origin' => $payload['origin'],
                'destination' => $payload['destination'],
                'transport_mode' => $payload['transport_mode'],
                'student_count' => $studentCount,
                'adult_count' => $adultCount,
                'passenger_count' => $studentCount + $adultCount,
                'reduced_mobility' => (bool) ($payload['reduced_mobility'] ?? false),
                'mobility_requirements' => $payload['mobility_requirements'] ?? null,
                'visible_observations' => $payload['visible_observations'] ?? null,
                'urgent' => (bool) ($payload['urgent'] ?? false),
                'approval_status' => $transfer->exists ? $transfer->approval_status : 'borrador',
            ]);
            $oldStatus = $transfer->getOriginal('approval_status');
            $transfer->save();
            $this->log($transfer, $actor, $isNew ? 'creada' : 'actualizada', $oldStatus, $transfer->approval_status);

            return $this->load($transfer);
        });
    }

    public function submit(OperationalTransferRequest $transfer, User $actor, ?string $comment = null): OperationalTransferRequest
    {
        if (! $transfer->isEditable() && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages(['approval_status' => 'Solo se pueden enviar solicitudes en borrador u observadas.']);
        }
        if (! $transfer->visor_user_id) {
            throw ValidationException::withMessages(['visor_user_id' => 'El solicitante no tiene un subdirector configurado en el organigrama.']);
        }
        if (! $transfer->documents()->where('document_type', 'solicitud_pedagogica')->exists()) {
            throw ValidationException::withMessages(['documents' => 'Adjunta la solicitud pedagógica o respaldo institucional antes de enviar.']);
        }

        $transfer = DB::transaction(function () use ($transfer, $actor, $comment): OperationalTransferRequest {
            $oldStatus = $transfer->approval_status;
            $transfer->forceFill([
                'approval_status' => 'pendiente_visacion',
                'service_status' => 'sin_gestion',
                'submitted_at' => now(),
                'visible_observations' => null,
                'updated_by' => $actor->id,
            ])->save();
            $this->log($transfer, $actor, 'enviada_visacion', $oldStatus, $transfer->approval_status, ['comment' => $comment]);

            return $this->load($transfer);
        });

        $this->notify(collect([$transfer->visorUser])->filter(), $transfer, 'Solicitud de traslado pendiente de visación', 'Tienes una solicitud de traslado pendiente de visación.', $comment);
        $this->notifyRequester($transfer, 'Solicitud de traslado enviada', 'Tu solicitud fue enviada al subdirector para su visación.', $comment);

        return $transfer;
    }

    public function visorApprove(OperationalTransferRequest $transfer, User $actor, ?string $comment = null, ?string $internalComment = null): OperationalTransferRequest
    {
        $this->assertNotSelfApproval($transfer, $actor);
        if ($transfer->approval_status !== 'pendiente_visacion' || ((int) $transfer->visor_user_id !== (int) $actor->id && ! $actor->isSuperAdmin())) {
            throw ValidationException::withMessages(['approval_status' => 'No puedes visar esta solicitud en su estado actual.']);
        }

        $transfer = DB::transaction(function () use ($transfer, $actor, $comment, $internalComment): OperationalTransferRequest {
            $oldStatus = $transfer->approval_status;
            $transfer->approvals()->create([
                'actor_user_id' => $actor->id,
                'step' => 'visacion',
                'decision' => 'visado',
                'comments' => $comment,
                'internal_comments' => $internalComment,
                'acted_at' => now(),
            ]);
            $transfer->forceFill([
                'approval_status' => 'pendiente_administracion',
                'service_status' => 'cotizando',
                'visor_reviewed_at' => now(),
                'updated_by' => $actor->id,
            ])->save();
            $this->log($transfer, $actor, 'visada', $oldStatus, $transfer->approval_status, ['comment' => $comment]);

            return $this->load($transfer);
        });

        $this->notify($this->administrators(), $transfer, 'Traslado visado pendiente de gestión', 'Una solicitud visada requiere cotización y aprobación administrativa.', $comment);
        $this->notifyRequester($transfer, 'Solicitud de traslado visada', 'Tu solicitud fue visada y pasó a Administración.', $comment);

        return $transfer;
    }

    public function observe(OperationalTransferRequest $transfer, User $actor, string $comment, ?string $internalComment = null): OperationalTransferRequest
    {
        $step = $transfer->approval_status === 'pendiente_visacion' ? 'visacion' : 'administracion';
        $transfer = DB::transaction(function () use ($transfer, $actor, $comment, $internalComment, $step): OperationalTransferRequest {
            $oldStatus = $transfer->approval_status;
            $transfer->approvals()->create([
                'actor_user_id' => $actor->id,
                'step' => $step,
                'decision' => 'observado',
                'comments' => $comment,
                'internal_comments' => $internalComment,
                'acted_at' => now(),
            ]);
            $transfer->forceFill([
                'approval_status' => 'observado',
                'visible_observations' => $comment,
                'internal_observations' => $internalComment ?: $transfer->internal_observations,
                'updated_by' => $actor->id,
            ])->save();
            $this->log($transfer, $actor, 'observada', $oldStatus, $transfer->approval_status, ['comment' => $comment]);

            return $this->load($transfer);
        });
        $this->notifyRequester($transfer, 'Solicitud de traslado observada', 'Tu solicitud requiere correcciones o antecedentes adicionales.', $comment);

        return $transfer;
    }

    public function reject(OperationalTransferRequest $transfer, User $actor, string $comment, ?string $internalComment = null): OperationalTransferRequest
    {
        $step = $transfer->approval_status === 'pendiente_visacion' ? 'visacion' : 'administracion';
        $transfer = DB::transaction(function () use ($transfer, $actor, $comment, $internalComment, $step): OperationalTransferRequest {
            $oldStatus = $transfer->approval_status;
            $transfer->approvals()->create([
                'actor_user_id' => $actor->id,
                'step' => $step,
                'decision' => 'rechazado',
                'comments' => $comment,
                'internal_comments' => $internalComment,
                'acted_at' => now(),
            ]);
            $transfer->forceFill([
                'approval_status' => 'rechazado',
                'rejected_at' => now(),
                'visible_observations' => $comment,
                'internal_observations' => $internalComment ?: $transfer->internal_observations,
                'updated_by' => $actor->id,
            ])->save();
            $this->log($transfer, $actor, 'rechazada', $oldStatus, $transfer->approval_status, ['comment' => $comment]);

            return $this->load($transfer);
        });
        $this->notifyRequester($transfer, 'Solicitud de traslado rechazada', 'Tu solicitud fue rechazada.', $comment);

        return $transfer;
    }

    public function addQuote(OperationalTransferRequest $transfer, array $payload, User $actor): OperationalTransferRequest
    {
        if (! in_array($transfer->approval_status, ['pendiente_administracion', 'aprobado'], true)) {
            throw ValidationException::withMessages(['approval_status' => 'Las cotizaciones solo pueden registrarse durante la gestión administrativa.']);
        }

        return DB::transaction(function () use ($transfer, $payload, $actor): OperationalTransferRequest {
            $quote = $transfer->quotes()->create($payload + ['created_by' => $actor->id, 'updated_by' => $actor->id, 'selected' => false]);
            $this->log($transfer, $actor, 'cotizacion_agregada', $transfer->approval_status, $transfer->approval_status, ['quote_id' => $quote->id, 'amount' => $quote->amount]);

            return $this->load($transfer);
        });
    }

    public function selectQuote(OperationalTransferRequest $transfer, OperationalTransferQuote $quote, User $actor): OperationalTransferRequest
    {
        if ((int) $quote->operational_transfer_request_id !== (int) $transfer->id) {
            throw ValidationException::withMessages(['quote' => 'La cotización no pertenece a esta solicitud.']);
        }

        return DB::transaction(function () use ($transfer, $quote, $actor): OperationalTransferRequest {
            $transfer->quotes()->update(['selected' => false]);
            $quote->forceFill(['selected' => true, 'updated_by' => $actor->id])->save();
            $transfer->operation()->updateOrCreate(
                ['operational_transfer_request_id' => $transfer->id],
                ['selected_quote_id' => $quote->id, 'provider_id' => $quote->provider_id, 'final_cost' => $quote->amount, 'updated_by' => $actor->id],
            );
            $transfer->forceFill(['service_status' => 'cotizado', 'updated_by' => $actor->id])->save();
            $this->log($transfer, $actor, 'cotizacion_seleccionada', $transfer->approval_status, $transfer->approval_status, ['quote_id' => $quote->id]);

            return $this->load($transfer);
        });
    }

    public function administrationApprove(OperationalTransferRequest $transfer, User $actor, ?string $comment = null, ?string $internalComment = null): OperationalTransferRequest
    {
        $this->assertNotSelfApproval($transfer, $actor);
        if ($transfer->approval_status !== 'pendiente_administracion') {
            throw ValidationException::withMessages(['approval_status' => 'La solicitud no está pendiente de aprobación administrativa.']);
        }
        if (! $transfer->quotes()->where('selected', true)->exists()) {
            throw ValidationException::withMessages(['quote' => 'Selecciona una cotización antes de aprobar la solicitud.']);
        }

        $transfer = DB::transaction(function () use ($transfer, $actor, $comment, $internalComment): OperationalTransferRequest {
            $oldStatus = $transfer->approval_status;
            $transfer->approvals()->create([
                'actor_user_id' => $actor->id,
                'step' => 'administracion',
                'decision' => 'aprobado',
                'comments' => $comment,
                'internal_comments' => $internalComment,
                'acted_at' => now(),
            ]);
            $transfer->forceFill([
                'approval_status' => 'aprobado',
                'administration_user_id' => $actor->id,
                'approved_at' => now(),
                'dte_status' => 'pendiente',
                'updated_by' => $actor->id,
            ])->save();
            $this->log($transfer, $actor, 'aprobada_administracion', $oldStatus, $transfer->approval_status, ['comment' => $comment]);

            return $this->load($transfer);
        });
        $this->pdfService->storeSnapshot($transfer, 'aprobacion', $actor);
        $this->notifyRequester($transfer, 'Solicitud de traslado aprobada', 'Tu solicitud fue aprobada por Administración.', $comment);

        return $this->load($transfer);
    }

    public function updateOperation(OperationalTransferRequest $transfer, array $payload, User $actor): OperationalTransferRequest
    {
        if (! in_array($transfer->approval_status, ['pendiente_administracion', 'aprobado', 'importado_historico'], true)) {
            throw ValidationException::withMessages(['approval_status' => 'La gestión administrativa aún no está habilitada para esta solicitud.']);
        }

        return DB::transaction(function () use ($transfer, $payload, $actor): OperationalTransferRequest {
            $operationFields = collect($payload)->only([
                'final_cost', 'confirmation_reference', 'confirmation_notes', 'dte_number', 'dte_received_on',
                'payment_reference', 'payment_requested_on', 'payment_scheduled_on', 'paid_on', 'administrative_notes',
            ])->all();
            $transfer->operation()->updateOrCreate(
                ['operational_transfer_request_id' => $transfer->id],
                $operationFields + ['updated_by' => $actor->id],
            );
            $requestFields = collect($payload)->only(['dte_status', 'payment_status'])->all();
            if ($requestFields !== []) {
                $transfer->forceFill($requestFields + ['updated_by' => $actor->id])->save();
            }
            $this->log($transfer, $actor, 'gestion_administrativa_actualizada', $transfer->approval_status, $transfer->approval_status, array_keys($payload));

            return $this->load($transfer);
        });
    }

    public function confirm(OperationalTransferRequest $transfer, User $actor, array $payload): OperationalTransferRequest
    {
        if ($transfer->approval_status !== 'aprobado' || ! $transfer->quotes()->where('selected', true)->exists()) {
            throw ValidationException::withMessages(['approval_status' => 'Solo una solicitud aprobada y cotizada puede confirmarse.']);
        }

        $transfer = DB::transaction(function () use ($transfer, $actor, $payload): OperationalTransferRequest {
            $this->updateOperation($transfer, $payload, $actor);
            $transfer->forceFill([
                'service_status' => 'confirmado',
                'confirmed_at' => now(),
                'administration_user_id' => $actor->id,
                'updated_by' => $actor->id,
            ])->save();
            $this->log($transfer, $actor, 'servicio_confirmado', $transfer->approval_status, $transfer->approval_status, ['reference' => $payload['confirmation_reference'] ?? null]);

            return $this->load($transfer);
        });
        $this->pdfService->storeSnapshot($transfer, 'confirmacion', $actor);
        $this->notifyRequester($transfer, 'Traslado confirmado', 'El servicio de transporte fue confirmado.', $payload['confirmation_notes'] ?? null);

        return $this->load($transfer);
    }

    public function execute(OperationalTransferRequest $transfer, User $actor, ?string $comment = null): OperationalTransferRequest
    {
        if ($transfer->service_status !== 'confirmado') {
            throw ValidationException::withMessages(['service_status' => 'Solo un traslado confirmado puede marcarse como ejecutado.']);
        }

        return DB::transaction(function () use ($transfer, $actor, $comment): OperationalTransferRequest {
            $transfer->forceFill(['service_status' => 'ejecutado', 'executed_at' => now(), 'updated_by' => $actor->id])->save();
            $this->log($transfer, $actor, 'traslado_ejecutado', $transfer->approval_status, $transfer->approval_status, ['comment' => $comment]);

            return $this->load($transfer);
        });
    }

    public function cancel(OperationalTransferRequest $transfer, User $actor, string $comment): OperationalTransferRequest
    {
        if ($transfer->service_status === 'ejecutado' || $transfer->approval_status === 'cancelado') {
            throw ValidationException::withMessages(['service_status' => 'Un traslado ejecutado o ya cancelado no puede cancelarse.']);
        }

        return DB::transaction(function () use ($transfer, $actor, $comment): OperationalTransferRequest {
            $oldStatus = $transfer->approval_status;
            $transfer->forceFill([
                'approval_status' => 'cancelado',
                'service_status' => 'cancelado',
                'cancelled_at' => now(),
                'visible_observations' => $comment,
                'updated_by' => $actor->id,
            ])->save();
            $this->log($transfer, $actor, 'cancelada', $oldStatus, $transfer->approval_status, ['comment' => $comment]);
            $this->notifyRequester($transfer, 'Solicitud de traslado cancelada', 'La solicitud fue cancelada.', $comment);

            return $this->load($transfer);
        });
    }

    public function load(OperationalTransferRequest $transfer): OperationalTransferRequest
    {
        return $transfer->fresh()->load([
            'requesterStaff:id,full_name,cargo_id', 'requesterStaff.cargo:id,name',
            'requestedBy:id,name,email,staff_id', 'visorUser:id,name,email,staff_id',
            'administrationUser:id,name,email,staff_id', 'approvals.actorUser:id,name,email',
            'quotes.provider', 'quotes.documents', 'operation.provider', 'operation.selectedQuote.provider',
            'documents.uploadedByUser:id,name,email', 'logs.user:id,name,email',
        ]);
    }

    private function resolveVisor(Staff $staff): ?User
    {
        $relation = $staff->organigramRelations
            ->where('active', true)
            ->where('relationship_type', 'subdirector')
            ->sortBy([['is_primary', 'desc'], ['priority', 'asc'], ['id', 'asc']])
            ->first();

        return $relation?->relatedStaff?->user;
    }

    private function nextFolio(): string
    {
        $year = (int) now()->format('Y');
        $sequence = DB::table('operational_transfer_sequences')->where('year', $year)->lockForUpdate()->first();
        if (! $sequence) {
            DB::table('operational_transfer_sequences')->insert(['year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $sequence = DB::table('operational_transfer_sequences')->where('year', $year)->lockForUpdate()->first();
        }
        $next = ((int) $sequence->last_number) + 1;
        DB::table('operational_transfer_sequences')->where('year', $year)->update(['last_number' => $next, 'updated_at' => now()]);

        return sprintf('TR-%d-%04d', $year, $next);
    }

    private function assertNotSelfApproval(OperationalTransferRequest $transfer, User $actor): void
    {
        if ((int) $transfer->requested_by_user_id === (int) $actor->id && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages(['approver' => 'No puedes aprobar tu propia solicitud.']);
        }
    }

    private function administrators(): Collection
    {
        return User::query()->where('active', true)->where(function ($query): void {
            $query->whereHas('roles', fn ($roleQuery) => $roleQuery->where('slug', 'super_admin'))
                ->orWhereHas('roles.permissions', fn ($permissionQuery) => $permissionQuery->where('slug', 'gestionar_traslados_operativos'));
        })->get()->unique('id')->values();
    }

    private function notifyRequester(OperationalTransferRequest $transfer, string $subject, string $headline, ?string $comment = null): void
    {
        $this->notify(collect([$transfer->requestedBy])->filter(), $transfer, $subject, $headline, $comment);
    }

    private function notify(Collection $users, OperationalTransferRequest $transfer, string $subject, string $headline, ?string $comment = null): void
    {
        if ($users->isEmpty()) {
            return;
        }
        try {
            Notification::send($users, new OperationalTransferNotification($transfer, $subject, $headline, $comment));
        } catch (Throwable $exception) {
            Log::warning('No se pudo enviar una notificación de traslado operativo.', ['transfer_id' => $transfer->id, 'error' => $exception->getMessage()]);
        }
    }

    private function log(OperationalTransferRequest $transfer, ?User $actor, string $action, ?string $oldStatus, ?string $newStatus, array $details = []): void
    {
        $transfer->logs()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'details' => $details ?: null,
        ]);
    }
}
