<?php

namespace App\Services\RiskPrevention;

use App\Enums\RiskPrevention\RiskMatrixStatus;
use App\Models\RiskPrevention\RiskMatrixVersion;
use App\Models\User;
use App\Notifications\RiskPrevention\RiskMatrixNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class RiskMatrixWorkflow
{
    public function __construct(
        private readonly PreventiveProgramSynchronizer $programs,
        private readonly RiskMatrixAuditService $audit,
        private readonly RiskMatrixStructureService $structures,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function validationIssues(RiskMatrixVersion $version): array
    {
        $version->loadMissing($this->structures->detailRelations());
        $issues = [];
        $add = function (string $severity, string $code, string $message, string $path) use (&$issues): void {
            $issues[] = compact('severity', 'code', 'message', 'path');
        };

        if (blank($version->company_name_snapshot)) {
            $add('error', 'company_missing', 'Debe informar la empresa.', 'general.company');
        }
        if (blank($version->matrix->work_center_id) && blank($version->work_center_name_snapshot)) {
            $add('error', 'work_center_missing', 'Debe informar el centro de trabajo.', 'general.work_center');
        }
        if (blank($version->prepared_on)) {
            $add('error', 'date_missing', 'Debe informar la fecha de elaboración.', 'general.prepared_on');
        }
        if (! $version->program_responsible_id && blank($version->program_responsible_name_snapshot)) {
            $add('error', 'responsible_missing', 'Debe informar el responsable del programa.', 'general.responsible');
        }
        if ($version->processes->isEmpty()) {
            $add('error', 'process_missing', 'Debe registrar al menos un proceso.', 'processes');
        }

        $taskCount = 0;
        $riskCount = 0;
        foreach ($version->processes as $processIndex => $process) {
            foreach ($process->tasks as $taskIndex => $task) {
                $taskCount++;
                $taskPath = "processes.$processIndex.tasks.$taskIndex";
                if ($task->exposures->sum('count') === 0 && blank($task->zero_exposure_justification)) {
                    $add('error', 'zero_exposure_unjustified', 'Una tarea sin personas expuestas requiere justificación.', "$taskPath.exposures");
                }
                foreach ($task->risks as $riskIndex => $risk) {
                    $riskCount++;
                    $riskPath = "$taskPath.risks.$riskIndex";
                    $assessment = $risk->assessments->first(fn ($item) => $item->phase === 'current' && $item->active);
                    if (! $assessment) {
                        $add('error', 'assessment_missing', 'El riesgo requiere una evaluación actual.', "$riskPath.assessment");

                        continue;
                    }
                    if ($risk->evaluation_method === 'vep' && (! in_array($assessment->probability, [1, 2, 4], true) || ! in_array($assessment->consequence, [1, 2, 4], true))) {
                        $add('error', 'vep_invalid', 'La evaluación VEP debe usar probabilidad y consecuencia 1, 2 o 4.', "$riskPath.assessment");
                    }
                    if ($risk->evaluation_method !== 'vep' && blank($assessment->result_level)) {
                        $add('error', 'protocol_result_missing', 'La evaluación específica requiere resultado o clasificación.', "$riskPath.assessment");
                    }
                    $score = (int) $assessment->calculated_score;
                    $actionable = $risk->controls->first(fn ($control) => $control->creates_program_action
                        && ($control->responsible_user_id || $control->responsible_employee_id || filled($control->responsible_text))
                        && $control->due_date);
                    if ($score === 8 && ! $actionable) {
                        $add('error', 'important_without_action', 'Un riesgo importante requiere medida, responsable y plazo.', "$riskPath.controls");
                    }
                    if ($score === 16) {
                        $immediate = $risk->controls->first(fn ($control) => $control->creates_program_action
                            && in_array($control->hierarchy_type, ['elimination', 'substitution', 'engineering'], true)
                            && ($control->responsible_user_id || $control->responsible_employee_id || filled($control->responsible_text))
                            && $control->due_date);
                        $add('block', 'intolerable_risk', $immediate
                            ? 'El riesgo intolerable posee respuesta inmediata, pero requiere autorización excepcional auditada.'
                            : 'El riesgo intolerable bloquea la aprobación y requiere suspensión, eliminación o reducción inmediata.', "$riskPath.controls");
                    }
                    if ((int) $assessment->consequence === 4) {
                        $add('warning', 'severe_consequence', 'Consecuencia severa: verifique controles aunque el nivel calculado sea moderado.', "$riskPath.assessment");
                    }
                }
            }
        }
        if ($taskCount === 0) {
            $add('error', 'task_missing', 'Debe registrar al menos una actividad o tarea.', 'processes');
        }
        if ($riskCount === 0) {
            $add('error', 'risk_missing', 'Debe registrar al menos un riesgo.', 'risks');
        }

        return $issues;
    }

    public function submit(RiskMatrixVersion $version, User $actor, ?string $reason = null): RiskMatrixVersion
    {
        $this->assertTransition($version, RiskMatrixStatus::InReview);
        // A formal intolerable-risk block must reach technical review so an
        // authorized, justified exception can be decided and audited. Data
        // consistency errors still prevent submission.
        $blocking = collect($this->validationIssues($version))->where('severity', 'error')->values();
        if ($blocking->isNotEmpty()) {
            throw ValidationException::withMessages(['matrix' => $blocking->pluck('message')->all()]);
        }

        return DB::transaction(function () use ($version, $reason) {
            $version->update(['status' => RiskMatrixStatus::InReview, 'submitted_at' => now(), 'reviewed_at' => null, 'reviewed_by' => null]);
            $this->audit->record($version, 'submitted', [], ['status' => 'in_review'], $reason);
            $this->notifyPermission('risk-matrix.review', 'Matriz enviada a revisión', "{$version->matrix->code} · versión {$version->version_number}", $version);

            return $version->fresh();
        });
    }

    public function technicalReview(RiskMatrixVersion $version, User $actor, string $notes): RiskMatrixVersion
    {
        if ($version->status !== RiskMatrixStatus::InReview) {
            abort(409, 'Solo una matriz en revisión puede validarse técnicamente.');
        }
        $version->update(['reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_notes' => $notes]);
        $this->audit->record($version, 'technically_reviewed', [], ['reviewed_by' => $actor->id], $notes);

        return $version->fresh();
    }

    public function observe(RiskMatrixVersion $version, User $actor, string $reason): RiskMatrixVersion
    {
        $this->assertTransition($version, RiskMatrixStatus::Observed);
        $version->update(['status' => RiskMatrixStatus::Observed, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_notes' => $reason]);
        $this->audit->record($version, 'observed', [], ['status' => 'observed'], $reason);
        if ($version->preparedBy) {
            $version->preparedBy->notify(new RiskMatrixNotification('Matriz observada', $reason, $version));
        }

        return $version->fresh();
    }

    public function returnToDraft(RiskMatrixVersion $version, User $actor, string $reason): RiskMatrixVersion
    {
        $this->assertTransition($version, RiskMatrixStatus::Draft);
        $version->update(['status' => RiskMatrixStatus::Draft, 'review_notes' => $reason]);
        $this->audit->record($version, 'returned_to_draft', [], ['status' => 'draft'], $reason);

        return $version->fresh();
    }

    public function approve(RiskMatrixVersion $version, User $actor, array $exception = []): RiskMatrixVersion
    {
        $this->assertTransition($version, RiskMatrixStatus::Approved);
        if (! $version->reviewed_at || ! $version->reviewed_by) {
            throw ValidationException::withMessages(['review' => 'La revisión técnica debe quedar registrada antes de aprobar.']);
        }

        $issues = collect($this->validationIssues($version));
        $errors = $issues->where('severity', 'error');
        if ($errors->isNotEmpty()) {
            throw ValidationException::withMessages(['matrix' => $errors->pluck('message')->all()]);
        }
        $blocks = $issues->where('severity', 'block');
        if ($blocks->isNotEmpty()) {
            if (! $actor->hasPermission('risk-matrix.override-block') || blank($exception['justification'] ?? null) || ! ($exception['additional_approval'] ?? false)) {
                throw ValidationException::withMessages(['exception' => 'El riesgo intolerable requiere permiso especial, justificación y aprobación adicional expresa.']);
            }
        }

        return DB::transaction(function () use ($version, $actor, $exception, $blocks) {
            $version->loadMissing($this->structures->detailRelations());
            $snapshot = $version->toArray();
            $hash = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $approvedAt = now();
            $version->update([
                'status' => RiskMatrixStatus::Approved,
                'approved_by' => $actor->id,
                'approved_at' => $approvedAt,
                'effective_from' => $version->effective_from ?? $approvedAt->toDateString(),
                'next_review_at' => $approvedAt->copy()->addMonths(config('risk_matrix.annual_review_months', 12))->toDateString(),
                'snapshot_hash' => $hash,
                'snapshot_payload' => $snapshot,
            ]);

            $version->matrix->versions()
                ->where('id', '!=', $version->id)
                ->where('status', RiskMatrixStatus::Approved->value)
                ->update(['status' => RiskMatrixStatus::Superseded->value]);
            $version->matrix->update(['active_version_id' => $version->id]);
            $this->programs->sync($version->fresh());
            $this->audit->record($version, 'approved', [], ['status' => 'approved', 'snapshot_hash' => $hash]);
            if ($blocks->isNotEmpty()) {
                $this->audit->record($version, 'approval_block_overridden', [], ['additional_approval' => true], $exception['justification']);
            }
            if ($version->preparedBy) {
                $version->preparedBy->notify(new RiskMatrixNotification('Matriz aprobada', "La versión {$version->version_number} fue aprobada.", $version));
            }

            return $version->fresh(['matrix', 'program.actions']);
        });
    }

    public function archive(RiskMatrixVersion $version, User $actor, string $reason): RiskMatrixVersion
    {
        $this->assertTransition($version, RiskMatrixStatus::Archived);
        $version->update(['status' => RiskMatrixStatus::Archived, 'archived_at' => now()]);
        $this->audit->record($version, 'archived', [], ['status' => 'archived'], $reason);

        return $version->fresh();
    }

    private function assertTransition(RiskMatrixVersion $version, RiskMatrixStatus $target): void
    {
        if (! $version->status->canTransitionTo($target)) {
            abort(409, "Transición no permitida: {$version->status->value} → {$target->value}.");
        }
    }

    private function notifyPermission(string $permission, string $title, string $message, RiskMatrixVersion $version): void
    {
        $users = User::query()->where('active', true)->with('roles.permissions')->get()->filter(fn (User $user) => $user->hasPermission($permission));
        Notification::send($users, new RiskMatrixNotification($title, $message, $version));
    }
}
