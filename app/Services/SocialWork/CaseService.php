<?php

namespace App\Services\SocialWork;

use App\Events\SocialWork\SocialCaseStatusChanged;
use App\Events\SocialWork\SocialCaseCreated;
use App\Models\SocialWork\SocialCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CaseService
{
    public const TRANSITIONS = [
        'borrador' => ['recibido', 'anulado'],
        'recibido' => ['evaluacion_inicial', 'abierto', 'anulado'],
        'evaluacion_inicial' => ['abierto', 'espera_antecedentes', 'derivado_externamente', 'pendiente_cierre'],
        'abierto' => ['intervencion', 'espera_antecedentes', 'seguimiento', 'derivado_externamente', 'pendiente_cierre'],
        'intervencion' => ['espera_antecedentes', 'seguimiento', 'derivado_externamente', 'pendiente_cierre'],
        'espera_antecedentes' => ['evaluacion_inicial', 'intervencion', 'seguimiento', 'pendiente_cierre'],
        'seguimiento' => ['intervencion', 'espera_antecedentes', 'pendiente_cierre'],
        'derivado_externamente' => ['seguimiento', 'intervencion', 'pendiente_cierre'],
        'pendiente_cierre' => ['intervencion', 'seguimiento', 'cerrado'],
        'cerrado' => ['reabierto'],
        'reabierto' => ['evaluacion_inicial', 'intervencion', 'seguimiento', 'pendiente_cierre'],
        'anulado' => [],
    ];

    public function __construct(private readonly AuditService $audit) {}

    public function create(array $data, User $user): SocialCase
    {
        return DB::transaction(function () use ($data, $user) {
            $year = (int) now()->format('Y');
            $sequence = DB::table('social_work_sequences')->lockForUpdate()->where('year', $year)->first();
            if (! $sequence) {
                DB::table('social_work_sequences')->insert(['year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
                $sequence = DB::table('social_work_sequences')->lockForUpdate()->where('year', $year)->first();
            }
            $number = ((int) $sequence->last_number) + 1;
            DB::table('social_work_sequences')->where('year', $year)->update(['last_number' => $number, 'updated_at' => now()]);

            $studentIds = array_values(array_unique(array_merge([$data['primary_student_id']], $data['student_ids'] ?? [])));
            unset($data['student_ids']);
            $case = SocialCase::create(array_merge($data, [
                'code' => sprintf('TS-%d-%05d', $year, $number),
                'opened_on' => $data['opened_on'] ?? today(),
                'last_activity_at' => now(),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]));

            foreach ($studentIds as $studentId) {
                $case->students()->attach($studentId, ['is_primary' => (int) $studentId === (int) $case->primary_student_id, 'relationship' => (int) $studentId === (int) $case->primary_student_id ? 'principal' : 'relacionado']);
            }
            $case->statusHistory()->create(['from_status' => null, 'to_status' => $case->status, 'changed_at' => now(), 'changed_by' => $user->id, 'reason' => 'Creación del caso']);
            if ($case->responsible_user_id) {
                DB::table('social_work_case_assignments')->insert(['case_id' => $case->id, 'user_id' => $case->responsible_user_id, 'role' => 'responsable', 'assigned_at' => now(), 'assigned_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->audit->record('case.created', $case, $user, [], $case->toArray());
            event(new SocialCaseCreated($case, $user));
            return $case->fresh();
        });
    }

    public function changeStatus(SocialCase $case, string $to, User $user, string $reason, ?string $notes = null): SocialCase
    {
        if (! in_array($to, self::TRANSITIONS[$case->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "Transición inválida de {$case->status} a {$to}."]);
        }

        return DB::transaction(function () use ($case, $to, $user, $reason, $notes) {
            $from = $case->status;
            $case->update(['status' => $to, 'last_activity_at' => now(), 'updated_by' => $user->id]);
            $case->statusHistory()->create(['from_status' => $from, 'to_status' => $to, 'changed_at' => now(), 'changed_by' => $user->id, 'reason' => $reason, 'notes' => $notes]);
            $this->audit->record('case.status_changed', $case, $user, ['status' => $from], ['status' => $to], $reason);
            event(new SocialCaseStatusChanged($case, $from, $to, $user));
            return $case->fresh();
        });
    }

    public function close(SocialCase $case, array $data, User $user): SocialCase
    {
        if ($case->status === 'cerrado') throw ValidationException::withMessages(['status' => 'El caso ya está cerrado.']);
        if ($case->status !== 'pendiente_cierre') throw ValidationException::withMessages(['status' => 'El caso debe pasar a pendiente de cierre antes de cerrarse.']);
        if (empty(trim((string) ($data['closure_conclusion'] ?? '')))) throw ValidationException::withMessages(['closure_conclusion' => 'La conclusión final es obligatoria.']);

        return DB::transaction(function () use ($case, $data, $user) {
            $from = $case->status;
            $case->update(array_merge($data, ['status' => 'cerrado', 'closed_at' => $data['closed_at'] ?? now(), 'closed_by' => $user->id, 'last_activity_at' => now(), 'updated_by' => $user->id]));
            $case->statusHistory()->create(['from_status' => $from, 'to_status' => 'cerrado', 'changed_at' => now(), 'changed_by' => $user->id, 'reason' => $data['closure_reason']]);
            $this->audit->record('case.closed', $case, $user, ['status' => $from], ['status' => 'cerrado', 'closure_conclusion' => $data['closure_conclusion']], $data['closure_reason']);
            return $case->fresh();
        });
    }

    public function reopen(SocialCase $case, array $data, User $user): SocialCase
    {
        if ($case->status !== 'cerrado') throw ValidationException::withMessages(['status' => 'Solo se puede reabrir un caso cerrado.']);

        return DB::transaction(function () use ($case, $data, $user) {
            $case->reopenings()->create(['reopened_at' => $data['reopened_at'] ?? now(), 'reopened_by' => $user->id, 'reason' => $data['reason'], 'previous_conclusion' => $case->closure_conclusion, 'risk_level' => $data['risk_level'], 'priority' => $data['priority'], 'assigned_user_id' => $data['assigned_user_id'], 'next_action' => $data['next_action']]);
            $case->update(['status' => 'reabierto', 'risk_level' => $data['risk_level'], 'priority' => $data['priority'], 'responsible_user_id' => $data['assigned_user_id'], 'next_milestone' => $data['next_action'], 'closed_at' => null, 'closed_by' => null, 'reopen_count' => $case->reopen_count + 1, 'last_activity_at' => now(), 'updated_by' => $user->id]);
            $case->statusHistory()->create(['from_status' => 'cerrado', 'to_status' => 'reabierto', 'changed_at' => now(), 'changed_by' => $user->id, 'reason' => $data['reason']]);
            $this->audit->record('case.reopened', $case, $user, ['status' => 'cerrado'], ['status' => 'reabierto', 'risk_level' => $data['risk_level']], $data['reason']);
            return $case->fresh();
        });
    }
}
