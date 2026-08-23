<?php

namespace App\Services\RiskPrevention;

use App\Enums\RiskPrevention\RiskMatrixStatus;
use App\Models\RiskPrevention\RiskMatrix;
use App\Models\RiskPrevention\RiskMatrixVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RiskMatrixVersionService
{
    public function __construct(private readonly RiskMatrixAuditService $audit) {}

    public function createFrom(RiskMatrix $matrix, RiskMatrixVersion $source, User $actor, string $reason): RiskMatrixVersion
    {
        abort_unless($source->risk_matrix_id === $matrix->id, 404);
        abort_if($source->status === RiskMatrixStatus::Archived, 409, 'No se puede versionar una matriz archivada.');

        return DB::transaction(function () use ($matrix, $source, $actor, $reason) {
            $version = $source->replicate([
                'status', 'submitted_at', 'reviewed_at', 'approved_at', 'archived_at', 'reviewed_by', 'approved_by',
                'snapshot_hash', 'snapshot_payload', 'source_version_id', 'lock_version',
            ]);
            $version->version_number = ((int) $matrix->versions()->max('version_number')) + 1;
            $version->status = RiskMatrixStatus::Draft;
            $version->source_version_id = $source->id;
            $version->prepared_by = $actor->id;
            $version->prepared_on = now()->toDateString();
            $version->updated_on = now()->toDateString();
            $version->review_reason = $reason;
            $version->lock_version = 1;
            $version->save();

            foreach ($source->processes()->with('tasks.exposures', 'tasks.positions', 'tasks.risks.hazardFactors', 'tasks.risks.assessments', 'tasks.risks.controls')->get() as $sourceProcess) {
                $process = $sourceProcess->replicate();
                $process->risk_matrix_version_id = $version->id;
                $process->save();
                foreach ($sourceProcess->tasks as $sourceTask) {
                    $task = $sourceTask->replicate();
                    $task->risk_matrix_process_id = $process->id;
                    $task->save();
                    $task->positions()->sync($sourceTask->positions->pluck('id'));
                    foreach ($sourceTask->exposures as $sourceExposure) {
                        $exposure = $sourceExposure->replicate();
                        $exposure->risk_matrix_task_id = $task->id;
                        $exposure->save();
                    }
                    foreach ($sourceTask->risks as $sourceRisk) {
                        $risk = $sourceRisk->replicate();
                        $risk->risk_matrix_task_id = $task->id;
                        $risk->save();
                        foreach ($sourceRisk->hazardFactors as $sourceHazard) {
                            $hazard = $sourceHazard->replicate();
                            $hazard->risk_entry_id = $risk->id;
                            $hazard->save();
                        }
                        foreach ($sourceRisk->assessments as $sourceAssessment) {
                            $assessment = $sourceAssessment->replicate();
                            $assessment->risk_entry_id = $risk->id;
                            $assessment->save();
                        }
                        foreach ($sourceRisk->controls as $sourceControl) {
                            $control = $sourceControl->replicate(['completed_at', 'verified_at', 'verified_by', 'effectiveness_result', 'effectiveness_notes']);
                            $control->risk_entry_id = $risk->id;
                            $control->status = in_array($sourceControl->status, ['verified', 'implemented'], true) ? 'planned' : $sourceControl->status;
                            $control->progress_percentage = 0;
                            $control->save();
                        }
                    }
                }
            }

            foreach ($source->evidences as $sourceEvidence) {
                $evidence = $sourceEvidence->replicate();
                $evidence->risk_matrix_version_id = $version->id;
                $evidence->description = trim(($evidence->description ? $evidence->description.' · ' : '').'Heredada de versión '.$source->version_number);
                $evidence->save();
            }

            $this->audit->record($version, 'version_created', [], ['source_version_id' => $source->id], $reason);

            return $version->fresh();
        });
    }
}
