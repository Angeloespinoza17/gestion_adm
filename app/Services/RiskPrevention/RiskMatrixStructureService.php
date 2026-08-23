<?php

namespace App\Services\RiskPrevention;

use App\Models\RiskPrevention\RiskAssessment;
use App\Models\RiskPrevention\RiskControl;
use App\Models\RiskPrevention\RiskEntry;
use App\Models\RiskPrevention\RiskHazardFactor;
use App\Models\RiskPrevention\RiskMatrixProcess;
use App\Models\RiskPrevention\RiskMatrixTask;
use App\Models\RiskPrevention\RiskMatrixVersion;
use App\Models\RiskPrevention\RiskTaskExposure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RiskMatrixStructureService
{
    public function __construct(
        private readonly RiskAssessmentService $assessmentService,
        private readonly RiskMatrixAuditService $audit,
    ) {}

    public function replace(RiskMatrixVersion $version, array $processes, int $expectedLockVersion, ?int $actorId): RiskMatrixVersion
    {
        if (! $version->isEditable()) {
            throw ValidationException::withMessages(['version' => 'Una versión aprobada o archivada es inmutable. Cree una nueva versión.']);
        }
        if ($version->lock_version !== $expectedLockVersion) {
            abort(409, 'La matriz fue modificada por otra persona. Recargue y revise los cambios antes de guardar.');
        }

        return DB::transaction(function () use ($version, $processes, $actorId) {
            $this->deleteStructure($version);

            foreach ($processes as $processIndex => $processData) {
                $process = RiskMatrixProcess::query()->create([
                    'risk_matrix_version_id' => $version->id,
                    'name' => $processData['name'],
                    'description' => $processData['description'] ?? null,
                    'process_type' => $processData['process_type'] ?? 'operational',
                    'display_order' => $processData['display_order'] ?? $processIndex,
                    'observations' => $processData['observations'] ?? null,
                ]);

                foreach ($processData['tasks'] ?? [] as $taskIndex => $taskData) {
                    $task = RiskMatrixTask::query()->create([
                        'risk_matrix_process_id' => $process->id,
                        'activity_name' => $taskData['activity_name'],
                        'task_name' => $taskData['task_name'],
                        'routine_type' => $taskData['routine_type'] ?? 'routine',
                        'job_position_id' => $taskData['job_position_id'] ?? null,
                        'job_position_text' => $taskData['job_position_text'] ?? null,
                        'location_id' => $taskData['location_id'] ?? null,
                        'specific_location' => $taskData['specific_location'] ?? null,
                        'zero_exposure_justification' => $taskData['zero_exposure_justification'] ?? null,
                        'observations' => $taskData['observations'] ?? null,
                        'display_order' => $taskData['display_order'] ?? $taskIndex,
                    ]);
                    $task->positions()->sync($taskData['position_ids'] ?? []);

                    foreach ($taskData['exposures'] ?? [] as $exposure) {
                        RiskTaskExposure::query()->create([
                            'risk_matrix_task_id' => $task->id,
                            'exposure_category_id' => $exposure['exposure_category_id'],
                            'count' => $exposure['count'],
                            'observations' => $exposure['observations'] ?? null,
                        ]);
                    }

                    foreach ($taskData['risks'] ?? [] as $riskIndex => $riskData) {
                        $risk = RiskEntry::query()->create([
                            'risk_matrix_task_id' => $task->id,
                            'risk_family_id' => $riskData['risk_family_id'],
                            'risk_catalog_item_id' => $riskData['risk_catalog_item_id'] ?? null,
                            'specific_risk_code' => $riskData['specific_risk_code'] ?? null,
                            'specific_risk_name' => $riskData['specific_risk_name'],
                            'possible_harm' => $riskData['possible_harm'],
                            'evaluation_method' => $riskData['evaluation_method'] ?? 'vep',
                            'declared_controlled_status' => $riskData['declared_controlled_status'] ?? 'not_assessed',
                            'verified_controlled_status' => $riskData['verified_controlled_status'] ?? 'not_assessed',
                            'legal_or_protocol_reference' => $riskData['legal_or_protocol_reference'] ?? null,
                            'notes' => $riskData['notes'] ?? null,
                            'source_payload' => $riskData['source_payload'] ?? null,
                            'source_row_number' => $riskData['source_row_number'] ?? null,
                            'display_order' => $riskData['display_order'] ?? $riskIndex,
                        ]);

                        foreach ($riskData['hazard_factors'] ?? [] as $hazard) {
                            RiskHazardFactor::query()->create([
                                'risk_entry_id' => $risk->id,
                                'category' => $hazard['category'] ?? null,
                                'hazard_description' => $hazard['hazard_description'],
                                'risk_factor_description' => $hazard['risk_factor_description'] ?? null,
                                'source_catalog_id' => $hazard['source_catalog_id'] ?? null,
                            ]);
                        }

                        foreach ($riskData['assessments'] ?? [] as $assessment) {
                            $this->assessmentService->createCurrent($risk, $assessment, $version->methodology, $actorId);
                        }

                        foreach ($riskData['controls'] ?? [] as $control) {
                            RiskControl::query()->create([
                                'risk_entry_id' => $risk->id,
                                'control_stage' => $control['control_stage'],
                                'hierarchy_type' => $control['hierarchy_type'],
                                'description' => $control['description'],
                                'responsible_user_id' => $control['responsible_user_id'] ?? null,
                                'responsible_employee_id' => $control['responsible_employee_id'] ?? null,
                                'responsible_text' => $control['responsible_text'] ?? null,
                                'status' => $control['status'] ?? 'pending',
                                'priority' => $control['priority'] ?? 'medium',
                                'planned_start_date' => $control['planned_start_date'] ?? null,
                                'due_date' => $control['due_date'] ?? null,
                                'periodicity_type' => $control['periodicity_type'] ?? 'once',
                                'periodicity_value' => $control['periodicity_value'] ?? null,
                                'next_due_date' => $control['next_due_date'] ?? null,
                                'progress_percentage' => $control['progress_percentage'] ?? 0,
                                'creates_program_action' => (bool) ($control['creates_program_action'] ?? false),
                            ]);
                        }
                    }
                }
            }

            $version->increment('lock_version');
            $version->update(['updated_on' => now()->toDateString()]);
            $this->audit->record($version, 'structure_replaced', [], ['processes' => count($processes)]);

            return $version->fresh($this->detailRelations());
        });
    }

    public function deleteStructure(RiskMatrixVersion $version): void
    {
        $taskIds = RiskMatrixTask::query()->whereIn('risk_matrix_process_id', $version->processes()->pluck('id'))->pluck('id');
        $riskIds = RiskEntry::query()->whereIn('risk_matrix_task_id', $taskIds)->pluck('id');
        RiskControl::query()->whereIn('risk_entry_id', $riskIds)->delete();
        RiskAssessment::query()->whereIn('risk_entry_id', $riskIds)->delete();
        RiskHazardFactor::query()->whereIn('risk_entry_id', $riskIds)->delete();
        RiskEntry::query()->whereIn('id', $riskIds)->delete();
        RiskTaskExposure::query()->whereIn('risk_matrix_task_id', $taskIds)->delete();
        DB::table('prevent_risk_task_positions')->whereIn('risk_matrix_task_id', $taskIds)->delete();
        RiskMatrixTask::query()->whereIn('id', $taskIds)->delete();
        RiskMatrixProcess::query()->where('risk_matrix_version_id', $version->id)->delete();
    }

    /** @return array<int, string> */
    public function detailRelations(): array
    {
        return [
            'matrix.workCenter:id,name,code', 'methodology:id,code,version_number,name,configuration',
            'processes.tasks.positions:id,name', 'processes.tasks.location:id,name,code',
            'processes.tasks.exposures.category:id,code,name',
            'processes.tasks.risks.family:id,code,name,configuration',
            'processes.tasks.risks.hazardFactors',
            'processes.tasks.risks.assessments.calculatedLevel:id,code,name,color,configuration',
            'processes.tasks.risks.currentAssessment.calculatedLevel:id,code,name,color,configuration',
            'processes.tasks.risks.controls.responsible:id,name,email',
            'participations', 'reviews', 'evidences', 'program.actions.responsible:id,name,email',
            'preparedBy:id,name', 'reviewedBy:id,name', 'approvedBy:id,name',
        ];
    }
}
