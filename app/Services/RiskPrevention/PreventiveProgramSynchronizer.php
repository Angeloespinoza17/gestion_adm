<?php

namespace App\Services\RiskPrevention;

use App\Models\RiskPrevention\PreventiveProgram;
use App\Models\RiskPrevention\PreventiveProgramAction;
use App\Models\RiskPrevention\RiskMatrixVersion;
use Illuminate\Support\Facades\DB;

class PreventiveProgramSynchronizer
{
    public function sync(RiskMatrixVersion $version): PreventiveProgram
    {
        return DB::transaction(function () use ($version) {
            $version->loadMissing('matrix', 'processes.tasks.risks.controls');
            $program = PreventiveProgram::query()->updateOrCreate(
                ['risk_matrix_version_id' => $version->id],
                [
                    'company_key' => $version->matrix->company_key,
                    'work_center_id' => $version->matrix->work_center_id,
                    'code' => sprintf('PTP-%s-V%d', $version->matrix->code, $version->version_number),
                    'name' => 'Programa preventivo · '.$version->matrix->name,
                    'status' => 'draft',
                    'generated_at' => now(),
                    'due_to_be_prepared_at' => ($version->approved_at ?? now())->copy()->addDays(config('risk_matrix.program_due_days', 30))->toDateString(),
                    'responsible_id' => $version->program_responsible_id,
                ],
            );

            $activeControlIds = [];
            foreach ($version->processes as $process) {
                foreach ($process->tasks as $task) {
                    foreach ($task->risks as $risk) {
                        foreach ($risk->controls->where('creates_program_action', true) as $control) {
                            $activeControlIds[] = $control->id;
                            PreventiveProgramAction::query()->updateOrCreate(
                                ['risk_control_id' => $control->id],
                                [
                                    'preventive_program_id' => $program->id,
                                    'process_id' => $process->id,
                                    'task_id' => $task->id,
                                    'risk_entry_id' => $risk->id,
                                    'action_description' => $control->description,
                                    'hierarchy_type' => $control->hierarchy_type,
                                    'responsible_id' => $control->responsible_user_id,
                                    'work_center_id' => $version->matrix->work_center_id,
                                    'planned_start_date' => $control->planned_start_date,
                                    'due_date' => $control->due_date,
                                    'actual_completion_date' => $control->completed_at?->toDateString(),
                                    'periodicity' => $control->periodicity_type,
                                    'progress_percentage' => $control->progress_percentage,
                                    'status' => $control->status,
                                    'verification_result' => $control->effectiveness_result,
                                ],
                            );
                        }
                    }
                }
            }

            if ($activeControlIds !== []) {
                $program->actions()->whereNotIn('risk_control_id', $activeControlIds)->delete();
            } else {
                $program->actions()->delete();
            }

            $progress = (int) round((float) $program->actions()->avg('progress_percentage'));
            $program->update(['progress_percentage' => $progress]);

            return $program->fresh('actions.responsible:id,name,email');
        });
    }
}
