<?php

namespace App\Services\Psychology;

use App\Events\Psychology\PsychologyRecordChanged;
use App\Models\Psychology\PsychologyCase;
use App\Models\Psychology\PsychologyCaseAssignment;
use App\Models\Psychology\PsychologyCaseClosure;
use App\Models\Psychology\PsychologyCaseReopening;
use App\Models\Psychology\PsychologyReferral;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PsychologyWorkflowService
{
    private const TRANSITIONS = [
        'draft' => ['submitted', 'cancelled'],
        'submitted' => ['under_review', 'information_requested', 'accepted', 'redirected', 'rejected', 'duplicated'],
        'under_review' => ['information_requested', 'accepted', 'linked_to_existing_case', 'redirected', 'rejected', 'duplicated'],
        'information_requested' => ['submitted', 'under_review', 'cancelled'],
        'accepted' => ['linked_to_existing_case', 'completed'],
        'linked_to_existing_case' => ['completed'],
        'redirected' => ['completed'], 'rejected' => [], 'duplicated' => [], 'cancelled' => [], 'completed' => [],
    ];

    public function __construct(
        private readonly PsychologyAuditService $audit,
        private readonly PsychologyNotificationService $notifications,
    ) {}

    public function createReferral(array $payload, User $user): PsychologyReferral
    {
        return DB::transaction(function () use ($payload, $user) {
            $submit = (bool) ($payload['submit'] ?? false);
            unset($payload['submit']);
            $referral = PsychologyReferral::query()->create($payload + [
                'referred_by_user_id' => $user->id, 'status' => 'draft', 'created_by' => $user->id, 'updated_by' => $user->id,
            ]);
            $referral->histories()->create(['to_status' => 'draft', 'reason' => 'Creación de borrador', 'changed_by' => $user->id, 'changed_at' => now()]);
            $this->audit->record('referral.created', $referral, $user, [], ['status' => 'draft']);
            if ($submit) {
                $referral = $this->transitionReferral($referral, 'submitted', $user, ['reason' => 'Derivación enviada']);
            }

            return $referral;
        });
    }

    public function updateDraft(PsychologyReferral $referral, array $payload, User $user): PsychologyReferral
    {
        if ($referral->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Una derivación enviada solo admite complementos auditables.']);
        }

        return DB::transaction(function () use ($referral, $payload, $user) {
            $old = $referral->only(['primary_reason', 'suggested_urgency', 'status']);
            $referral->fill($payload)->forceFill(['updated_by' => $user->id])->save();
            $this->audit->record('referral.updated', $referral, $user, $old, $referral->only(array_keys($old)));

            return $referral->refresh();
        });
    }

    public function transitionReferral(PsychologyReferral $referral, string $to, User $user, array $context = []): PsychologyReferral
    {
        $from = $referral->status;
        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "Transición no permitida: {$from} → {$to}."]);
        }
        if ($to === 'submitted' && ! $referral->purpose_declaration_accepted) {
            throw ValidationException::withMessages(['purpose_declaration_accepted' => 'Debes aceptar la declaración de finalidad institucional.']);
        }

        return DB::transaction(function () use ($referral, $from, $to, $user, $context) {
            $updates = ['status' => $to, 'updated_by' => $user->id];
            if ($to === 'submitted') {
                $updates += ['code' => $referral->code ?: sprintf('PSI-D-%s-%06d', now()->format('Y'), $referral->id), 'referred_at' => now()];
            }
            if ($from === 'submitted' && ! $referral->first_reviewed_at) {
                $updates['first_reviewed_at'] = now();
            }
            if (in_array($to, ['completed', 'redirected', 'rejected', 'duplicated'], true)) {
                $updates['completed_at'] = now();
            }
            foreach (['professional_priority', 'information_request', 'information_response', 'shared_decision_note', 'internal_decision_note', 'case_id'] as $field) {
                if (array_key_exists($field, $context)) {
                    $updates[$field] = $context[$field];
                }
            }
            $referral->forceFill($updates)->save();
            $referral->histories()->create(['from_status' => $from, 'to_status' => $to, 'reason' => $context['reason'] ?? null, 'shared_note' => $context['shared_note'] ?? null, 'internal_note' => $context['internal_note'] ?? null, 'changed_by' => $user->id, 'changed_at' => now()]);
            $this->audit->record('referral.status_changed', $referral, $user, ['status' => $from], ['status' => $to], $context['reason'] ?? null);
            if ($to === 'submitted') {
                $this->notifications->referralSubmitted($referral);
            } else {
                $this->notifications->referralStatusChanged($referral, $user);
            }
            event(new PsychologyRecordChanged('referral', $referral->id, $to));

            return $referral->refresh();
        });
    }

    public function assignReferral(PsychologyReferral $referral, User $professional, User $actor, array $context): PsychologyReferral
    {
        return DB::transaction(function () use ($referral, $professional, $actor, $context) {
            PsychologyCaseAssignment::query()->where('referral_id', $referral->id)->whereNull('ended_at')->update(['ended_at' => now()]);
            PsychologyCaseAssignment::query()->create(['referral_id' => $referral->id, 'user_id' => $professional->id, 'role' => 'primary', 'reason' => $context['reason'] ?? null, 'expected_first_review_at' => $context['expected_first_review_at'] ?? now()->addHours(config('psychology.first_review_hours', 48)), 'assigned_at' => now(), 'assigned_by' => $actor->id]);
            $referral->forceFill(['assigned_user_id' => $professional->id, 'professional_priority' => $context['professional_priority'] ?? $referral->professional_priority, 'updated_by' => $actor->id])->save();
            $this->audit->record('referral.assigned', $referral, $actor, [], ['assigned_user_id' => $professional->id], $context['reason'] ?? null);
            $this->notifications->assigned($referral, $professional);

            return $referral->refresh();
        });
    }

    public function openCase(PsychologyReferral $referral, array $payload, User $user): PsychologyCase
    {
        if (! in_array($referral->status, ['accepted', 'under_review'], true)) {
            throw ValidationException::withMessages(['status' => 'Solo una derivación revisada o aceptada puede abrir un caso.']);
        }

        return DB::transaction(function () use ($referral, $payload, $user) {
            $case = $this->createCase(
                (int) $referral->student_profile_id,
                $referral->id,
                $payload,
                $user,
                (int) ($referral->assigned_user_id ?: $user->id),
                'Apertura desde derivación',
            );
            $referral->forceFill(['case_id' => $case->id, 'assigned_user_id' => $case->responsible_user_id, 'updated_by' => $user->id])->save();
            $this->transitionReferral($referral, 'linked_to_existing_case', $user, ['case_id' => $case->id, 'reason' => 'Caso abierto desde derivación']);

            return $case;
        });
    }

    public function openDirectCase(array $payload, User $user): PsychologyCase
    {
        return DB::transaction(function () use ($payload, $user) {
            $studentProfileId = (int) $payload['student_profile_id'];
            unset($payload['student_profile_id']);

            return $this->createCase(
                $studentProfileId,
                null,
                $payload,
                $user,
                $user->id,
                'Apertura directa sin derivación',
            );
        });
    }

    private function createCase(
        int $studentProfileId,
        ?int $originReferralId,
        array $payload,
        User $user,
        int $defaultResponsibleId,
        string $assignmentReason,
    ): PsychologyCase {
        $activeCase = PsychologyCase::query()
            ->where('student_profile_id', $studentProfileId)
            ->where('status', '!=', 'closed')
            ->lockForUpdate()
            ->first(['id', 'code']);
        if ($activeCase) {
            throw ValidationException::withMessages([
                'student_profile_id' => "La estudiante ya tiene el caso activo {$activeCase->code}. Abre esa ficha o vincula la derivación existente.",
            ]);
        }

        $responsibleId = (int) ($payload['responsible_user_id'] ?? $defaultResponsibleId);
        $responsible = User::query()->whereKey($responsibleId)->where('active', true)->firstOrFail();
        if (! app(PsychologyAccessService::class)->canBeAssignedToPsychology($responsible)) {
            throw ValidationException::withMessages(['responsible_user_id' => 'La persona seleccionada no tiene un rol profesional habilitado para Psicología.']);
        }

        $case = PsychologyCase::query()->create(array_merge($payload, [
            'code' => sprintf('PSI-%s-%06d', now()->format('Y'), PsychologyCase::withTrashed()->max('id') + 1),
            'student_profile_id' => $studentProfileId,
            'origin_referral_id' => $originReferralId,
            'responsible_user_id' => $responsibleId,
            'opened_at' => now(),
            'last_activity_at' => now(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]));
        PsychologyCaseAssignment::query()->create([
            'case_id' => $case->id,
            'user_id' => $responsibleId,
            'role' => 'primary',
            'reason' => $assignmentReason,
            'assigned_at' => now(),
            'assigned_by' => $user->id,
        ]);
        $this->audit->record(
            'case.opened',
            $case,
            $user,
            [],
            ['status' => $case->status, 'priority' => $case->priority, 'origin' => $originReferralId ? 'referral' : 'direct'],
            $assignmentReason,
        );

        return $case;
    }

    public function reassignCase(PsychologyCase $case, User $professional, User $actor, string $reason): PsychologyCase
    {
        return DB::transaction(function () use ($case, $professional, $actor, $reason) {
            PsychologyCaseAssignment::query()->where('case_id', $case->id)->where('role', 'primary')->whereNull('ended_at')->update(['ended_at' => now()]);
            PsychologyCaseAssignment::query()->create(['case_id' => $case->id, 'user_id' => $professional->id, 'role' => 'primary', 'reason' => $reason, 'assigned_at' => now(), 'assigned_by' => $actor->id]);
            $old = $case->responsible_user_id;
            $case->forceFill(['responsible_user_id' => $professional->id, 'updated_by' => $actor->id])->save();
            $this->audit->record('case.reassigned', $case, $actor, ['responsible_user_id' => $old], ['responsible_user_id' => $professional->id], $reason);
            $this->notifications->assigned($case, $professional);

            return $case->refresh();
        });
    }

    public function closeCase(PsychologyCase $case, array $payload, User $user): PsychologyCase
    {
        if ($case->status === 'closed') {
            throw ValidationException::withMessages(['status' => 'El caso ya está cerrado.']);
        }

        return DB::transaction(function () use ($case, $payload, $user) {
            PsychologyCaseClosure::query()->create($payload + ['case_id' => $case->id, 'closed_at' => now(), 'closed_by' => $user->id]);
            $case->forceFill(['status' => 'closed', 'closed_at' => now(), 'closure_reason' => $payload['reason'], 'updated_by' => $user->id])->save();
            $this->audit->record('case.closed', $case, $user, [], ['status' => 'closed'], $payload['reason']);

            return $case->refresh();
        });
    }

    public function reopenCase(PsychologyCase $case, string $reason, User $user): PsychologyCase
    {
        if ($case->status !== 'closed') {
            throw ValidationException::withMessages(['status' => 'Solo un caso cerrado puede reabrirse.']);
        }

        return DB::transaction(function () use ($case, $reason, $user) {
            PsychologyCaseReopening::query()->create(['case_id' => $case->id, 'reason' => $reason, 'reopened_at' => now(), 'reopened_by' => $user->id]);
            $case->forceFill(['status' => 'reopened', 'closed_at' => null, 'closure_reason' => null, 'last_activity_at' => now(), 'updated_by' => $user->id])->save();
            $this->audit->record('case.reopened', $case, $user, ['status' => 'closed'], ['status' => 'reopened'], $reason);

            return $case->refresh();
        });
    }
}
