<?php

namespace App\Services\Psychology;

use App\Models\Psychology\PsychologyActivity;
use App\Models\Psychology\PsychologyCase;
use App\Models\Psychology\PsychologyCoordinationRequest;
use App\Models\User;
use App\Notifications\Psychology\PsychologyCoordinationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PsychologyCoordinationService
{
    public function __construct(private readonly PsychologyAuditService $audit) {}

    public function create(PsychologyCase $case, array $payload, User $requester, ?PsychologyActivity $activity = null): PsychologyCoordinationRequest
    {
        if ((int) $payload['recipient_user_id'] === (int) $requester->id) {
            throw ValidationException::withMessages(['recipient_user_id' => 'Selecciona a otro funcionario para la coordinación.']);
        }

        $recipient = User::query()
            ->whereKey($payload['recipient_user_id'])
            ->where('active', true)
            ->where(fn ($query) => $query->whereNotNull('staff_id')->orWhere('user_type', 'staff'))
            ->first();
        if (! $recipient) {
            throw ValidationException::withMessages(['recipient_user_id' => 'El funcionario seleccionado no está disponible.']);
        }

        return DB::transaction(function () use ($case, $payload, $requester, $activity, $recipient) {
            $coordination = PsychologyCoordinationRequest::query()->create([
                ...$payload,
                'case_id' => $case->id,
                'activity_id' => $activity?->id,
                'requester_user_id' => $requester->id,
                'status' => 'pending',
                'created_by' => $requester->id,
                'updated_by' => $requester->id,
            ]);
            $this->audit->record('coordination.requested', $coordination, $requester, [], [
                'recipient_user_id' => $recipient->id,
                'status' => 'pending',
                'coordination_type' => $coordination->coordination_type,
            ]);
            $recipient->notify(new PsychologyCoordinationNotification(
                'Nueva solicitud de coordinación',
                'Tienes una solicitud institucional pendiente de respuesta.',
                $coordination->id,
            ));

            return $coordination->load(['requester:id,name', 'recipient:id,name', 'responder:id,name']);
        });
    }

    public function respond(PsychologyCoordinationRequest $coordination, User $recipient, string $status, string $message): PsychologyCoordinationRequest
    {
        return DB::transaction(function () use ($coordination, $recipient, $status, $message) {
            $locked = PsychologyCoordinationRequest::query()
                ->whereKey($coordination->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ((int) $locked->recipient_user_id !== (int) $recipient->id) {
                abort(403);
            }
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Esta solicitud ya fue respondida.']);
            }

            $locked->forceFill([
                'status' => $status,
                'response_message' => $message,
                'responded_at' => now(),
                'responded_by' => $recipient->id,
                'updated_by' => $recipient->id,
            ])->save();
            $this->audit->record('coordination.responded', $locked, $recipient, ['status' => 'pending'], ['status' => $status]);
            $locked->loadMissing('requester');
            $locked->requester?->notify(new PsychologyCoordinationNotification(
                'Solicitud de coordinación respondida',
                $status === 'accepted' ? 'Tu solicitud de coordinación fue aceptada.' : 'Tu solicitud de coordinación fue rechazada.',
                $locked->id,
            ));

            return $locked->refresh()->load(['requester:id,name', 'recipient:id,name', 'responder:id,name']);
        });
    }
}
