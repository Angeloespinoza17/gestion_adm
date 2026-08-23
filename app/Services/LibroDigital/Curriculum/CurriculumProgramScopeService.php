<?php

namespace App\Services\LibroDigital\Curriculum;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\CurriculumAxis;
use App\Models\LibroDigital\CurriculumProgram;
use App\Models\LibroDigital\CurriculumUnit;

class CurriculumProgramScopeService
{
    /** @param list<int> $objectiveIds @return array{program:?CurriculumProgram,unit:?CurriculumUnit,axis:?CurriculumAxis} */
    public function resolve(Book $book, int $subjectId, string|int|null $programIdentifier, string|int|null $unitIdentifier = null, string|int|null $axisIdentifier = null, array $objectiveIds = []): array
    {
        if (! filled($programIdentifier)) {
            if (filled($unitIdentifier) || filled($axisIdentifier)) {
                throw new LibroDigitalException('Selecciona un programa antes de elegir unidad o eje.', 'LCD_CURRICULUM_PROGRAM_CONTEXT_REQUIRED', 422);
            }

            return ['program' => null, 'unit' => null, 'axis' => null];
        }
        $book->loadMissing('courseSection.educationLevel');
        $program = $this->find(CurriculumProgram::query(), $programIdentifier);
        if (! $program || $program->status !== 'published'
            || (int) $program->schedule_subject_id !== $subjectId
            || (int) $program->education_level_id !== (int) $book->courseSection?->education_level_id) {
            throw new LibroDigitalException('El programa no corresponde a la asignatura y curso del libro.', 'LCD_CURRICULUM_PROGRAM_SCOPE_INVALID', 422);
        }
        $unit = null;
        if (filled($unitIdentifier)) {
            $unit = $this->find(CurriculumUnit::query()->where('curriculum_program_id', $program->id), $unitIdentifier);
            if (! $unit) {
                throw new LibroDigitalException('La unidad no pertenece al programa seleccionado.', 'LCD_CURRICULUM_UNIT_SCOPE_INVALID', 422);
            }
            if ($objectiveIds !== []) {
                $outside = collect($objectiveIds)->diff($unit->learningObjectives()->pluck('lcd_learning_objectives.id'));
                if ($outside->isNotEmpty()) {
                    throw new LibroDigitalException('Uno o más OA no pertenecen a la unidad seleccionada.', 'LCD_CURRICULUM_UNIT_OBJECTIVES_INVALID', 422, [['objective_ids' => $outside->values()->all()]]);
                }
            }
        }
        $axis = null;
        if (filled($axisIdentifier)) {
            $axis = $this->find(CurriculumAxis::query()->whereHas('programs', fn ($query) => $query->where('lcd_curriculum_programs.id', $program->id)), $axisIdentifier);
            if (! $axis) {
                throw new LibroDigitalException('El eje no pertenece al programa seleccionado.', 'LCD_CURRICULUM_AXIS_SCOPE_INVALID', 422);
            }
        }

        return compact('program', 'unit', 'axis');
    }

    private function find($query, string|int $identifier): mixed
    {
        return $query->where(function ($nested) use ($identifier): void {
            if (ctype_digit((string) $identifier)) {
                $nested->whereKey((int) $identifier)->orWhere('public_id', (string) $identifier);
            } else {
                $nested->where('public_id', (string) $identifier);
            }
        })->first();
    }
}
