<?php

namespace App\Services\RiskPrevention;

use App\Models\RiskPrevention\RiskMatrixVersion;

class RiskMatrixComparisonService
{
    /** @return array<string, mixed> */
    public function compare(RiskMatrixVersion $from, RiskMatrixVersion $to): array
    {
        $fromRows = $this->rows($from);
        $toRows = $this->rows($to);
        $added = array_diff_key($toRows, $fromRows);
        $removed = array_diff_key($fromRows, $toRows);
        $modified = [];

        foreach (array_intersect_key($toRows, $fromRows) as $key => $row) {
            $changes = [];
            foreach ($row as $field => $value) {
                if (($fromRows[$key][$field] ?? null) !== $value) {
                    $changes[$field] = ['from' => $fromRows[$key][$field] ?? null, 'to' => $value];
                }
            }
            if ($changes !== []) {
                $modified[] = ['key' => $key, 'risk' => $row['risk'], 'changes' => $changes];
            }
        }

        return [
            'from' => ['id' => $from->id, 'version' => $from->version_number, 'methodology' => $from->methodology?->code],
            'to' => ['id' => $to->id, 'version' => $to->version_number, 'methodology' => $to->methodology?->code],
            'methodology_changed' => $from->methodology_id !== $to->methodology_id,
            'added' => array_values($added),
            'removed' => array_values($removed),
            'modified' => $modified,
            'summary' => ['added' => count($added), 'removed' => count($removed), 'modified' => count($modified)],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function rows(RiskMatrixVersion $version): array
    {
        $version->loadMissing('methodology', 'processes.tasks.exposures.category', 'processes.tasks.risks.currentAssessment.calculatedLevel', 'processes.tasks.risks.controls');
        $rows = [];
        foreach ($version->processes as $process) {
            foreach ($process->tasks as $task) {
                foreach ($task->risks as $risk) {
                    $key = mb_strtolower(implode('|', [$process->name, $task->activity_name, $task->task_name, $risk->specific_risk_code ?: $risk->specific_risk_name]));
                    $assessment = $risk->currentAssessment;
                    $rows[$key] = [
                        'process' => $process->name,
                        'activity' => $task->activity_name,
                        'task' => $task->task_name,
                        'risk' => $risk->specific_risk_name,
                        'score' => $assessment?->calculated_score,
                        'level' => $assessment?->calculatedLevel?->code ?? $assessment?->result_level,
                        'exposure' => $task->exposures->mapWithKeys(fn ($item) => [$item->category?->code => $item->count])->all(),
                        'controls' => $risk->controls->map(fn ($control) => [$control->hierarchy_type, $control->description, $control->responsible_user_id, optional($control->due_date)->toDateString()])->values()->all(),
                    ];
                }
            }
        }

        return $rows;
    }
}
