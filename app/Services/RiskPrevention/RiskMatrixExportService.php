<?php

namespace App\Services\RiskPrevention;

use App\Models\RiskPrevention\RiskMatrixVersion;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RiskMatrixExportService
{
    public function create(RiskMatrixVersion $version, bool $historical = false): string
    {
        $version->loadMissing(
            'matrix.workCenter', 'methodology', 'processes.tasks.positions', 'processes.tasks.exposures.category',
            'processes.tasks.risks.family', 'processes.tasks.risks.hazardFactors',
            'processes.tasks.risks.currentAssessment.calculatedLevel', 'processes.tasks.risks.residualAssessment.calculatedLevel',
            'processes.tasks.risks.controls.responsible', 'program.actions.responsible', 'preparedBy', 'reviewedBy', 'approvedBy',
        );
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($historical ? 'IPER-CNSC' : 'Matriz IPER');
        $headers = $historical ? $this->historicalHeaders() : $this->modernHeaders();
        $sheet->fromArray($headers, null, 'A1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:'.$sheet->getHighestColumn().'1');
        $rowNumber = 2;

        foreach ($version->processes as $process) {
            foreach ($process->tasks as $task) {
                foreach ($task->risks as $risk) {
                    $assessment = $risk->currentAssessment;
                    $residual = $risk->residualAssessment;
                    $hazards = $risk->hazardFactors->pluck('hazard_description')->implode("\n");
                    $factors = $risk->hazardFactors->pluck('risk_factor_description')->filter()->implode("\n");
                    $exposures = $task->exposures->mapWithKeys(fn ($item) => [$item->category?->code => $item->count]);
                    $controls = $risk->controls->where('control_stage', 'existing')->pluck('description')->implode("\n");
                    $proposed = $risk->controls->where('control_stage', '!=', 'existing');
                    $action = $proposed->first();
                    $row = $historical
                        ? [
                            $task->activity_name, $task->task_name, $task->positions->pluck('name')->push($task->job_position_text)->filter()->implode(', '),
                            $task->specific_location, $exposures['women'] ?? 0, $exposures['men'] ?? 0, $exposures['other_or_unspecified'] ?? 0,
                            $task->routine_type === 'routine' ? 'Rutinaria' : 'No rutinaria', $factors, $hazards, $risk->specific_risk_name, $risk->possible_harm,
                            $assessment?->probability, $assessment?->consequence, $assessment?->calculated_score, $assessment?->calculatedLevel?->name ?? $assessment?->result_level,
                            $action?->hierarchy_type, $action?->description ?? $controls, $risk->verified_controlled_status, $action?->responsible?->name ?? $action?->responsible_text,
                            $action?->periodicity_type,
                        ]
                        : [
                            $version->matrix->code, $version->matrix->folio, $version->version_number, $version->status->value,
                            $version->company_name_snapshot, $version->work_center_name_snapshot, $process->name, $task->activity_name, $task->task_name,
                            $task->positions->pluck('name')->push($task->job_position_text)->filter()->implode(', '), $task->specific_location,
                            $task->exposures->map(fn ($item) => ($item->category?->name ?? 'Categoría').': '.$item->count)->implode(' · '),
                            $risk->family?->name, $hazards, $factors, $risk->specific_risk_name, $risk->possible_harm, $risk->evaluation_method,
                            $assessment?->probability, $assessment?->consequence, $assessment?->calculated_score, $assessment?->calculatedLevel?->name ?? $assessment?->result_level,
                            $controls, $proposed->pluck('description')->implode("\n"), $proposed->pluck('hierarchy_type')->unique()->implode(', '),
                            $action?->responsible?->name ?? $action?->responsible_text, optional($action?->due_date)->toDateString(), $action?->periodicity_type,
                            $action?->status, $action?->progress_percentage, $residual?->calculated_score, $residual?->calculatedLevel?->name ?? $residual?->result_level,
                            $version->preparedBy?->name, $version->reviewedBy?->name, $version->approvedBy?->name, $version->snapshot_hash ? substr($version->snapshot_hash, 0, 12) : null,
                        ];
                    $sheet->fromArray(array_map([$this, 'safeCell'], $row), null, 'A'.$rowNumber++);
                }
            }
        }

        $lastColumn = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '17324D']],
            'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A1:'.$lastColumn.max(2, $rowNumber - 1))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $lastColumnIndex = Coordinate::columnIndexFromString($lastColumn);
        for ($columnIndex = 1; $columnIndex <= $lastColumnIndex; $columnIndex++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setWidth(22);
        }
        $sheet->getRowDimension(1)->setRowHeight(38);

        $criteria = $spreadsheet->createSheet();
        $criteria->setTitle('Criterios evaluación IPER');
        $criteria->fromArray([
            ['Metodología', $version->methodology->code.' v'.$version->methodology->version_number],
            ['Probabilidad', '1 Baja · 2 Media · 4 Alta'],
            ['Consecuencia', '1 Baja · 2 Media · 4 Alta'],
            ['VEP', 'Probabilidad × Consecuencia'],
            ['Niveles', '1-2 Tolerable · 4 Moderado · 8 Importante · 16 Intolerable'],
            ['Estado', $version->status->value],
            ['Hash', $version->snapshot_hash ?: 'BORRADOR SIN HASH DE APROBACIÓN'],
        ]);
        $criteria->getColumnDimension('A')->setWidth(24);
        $criteria->getColumnDimension('B')->setWidth(85);

        if ($version->program) {
            $program = $spreadsheet->createSheet();
            $program->setTitle('Programa de trabajo');
            $program->fromArray(['N°', 'Acción', 'Jerarquía', 'Responsable', 'Inicio', 'Vencimiento', 'Estado', 'Avance'], null, 'A1');
            foreach ($version->program->actions as $index => $action) {
                $program->fromArray([$index + 1, $this->safeCell($action->action_description), $action->hierarchy_type, $action->responsible?->name, optional($action->planned_start_date)->toDateString(), optional($action->due_date)->toDateString(), $action->status, $action->progress_percentage / 100], null, 'A'.($index + 2));
            }
            $program->getStyle('H2:H'.max(2, $version->program->actions->count() + 1))->getNumberFormat()->setFormatCode('0%');
        }

        $directory = 'risk-prevention/exports/'.now()->format('Y/m/d');
        $filename = sprintf('%s/%s-v%d-%s.xlsx', $directory, $version->matrix->code, $version->version_number, $historical ? 'historico' : 'moderno');
        Storage::disk('local')->makeDirectory($directory);
        (new Xlsx($spreadsheet))->save(Storage::disk('local')->path($filename));
        $spreadsheet->disconnectWorksheets();

        return $filename;
    }

    private function safeCell(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[=+\-@]/', $value)) {
            return "'".$value;
        }

        return $value;
    }

    private function historicalHeaders(): array
    {
        return ['Actividad', 'Tarea', 'Puesto de trabajo', 'Lugar específico', 'Exposición F', 'Exposición M', 'Exposición Otro', 'Rutinaria/No rutinaria', 'Factor de riesgo', 'Peligro', 'Riesgo específico', 'Daño posible', 'Probabilidad', 'Consecuencia', 'Magnitud', 'Clasificación', 'Tipo de control', 'Medida de control', 'Riesgo controlado', 'Responsable', 'Periodicidad'];
    }

    private function modernHeaders(): array
    {
        return ['Código', 'Folio', 'Versión', 'Estado', 'Empresa', 'Centro de trabajo', 'Proceso', 'Actividad', 'Tarea', 'Cargo', 'Lugar', 'Personas expuestas', 'Familia', 'Peligros', 'Factores', 'Riesgo', 'Daño', 'Método', 'Probabilidad', 'Consecuencia', 'VEP', 'Nivel actual', 'Controles existentes', 'Medidas propuestas', 'Jerarquía', 'Responsable', 'Fecha límite', 'Periodicidad', 'Estado medida', 'Avance %', 'VEP residual', 'Nivel residual', 'Elaboró', 'Revisó', 'Aprobó', 'Hash corto'];
    }
}
