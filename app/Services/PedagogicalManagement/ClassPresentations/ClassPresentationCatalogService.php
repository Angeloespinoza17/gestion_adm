<?php

namespace App\Services\PedagogicalManagement\ClassPresentations;

use App\Models\CourseSection;
use App\Models\LibroDigital\CurriculumUnit;
use App\Models\LibroDigital\LearningObjective;
use App\Models\LibroDigital\School;
use App\Models\Schedule\ScheduleSubject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ClassPresentationCatalogService
{
    public function __construct(private readonly ClassPresentationAccessService $access) {}

    public function school(User $user, int $schoolId): School
    {
        return $this->access->schoolsFor($user)->whereKey($schoolId)->firstOrFail();
    }

    public function schools(User $user): Collection
    {
        return $this->access->schoolsFor($user)->orderBy('name')->limit(50)->get(['id', 'public_id', 'name', 'rbd']);
    }

    public function academicYears(School $school): Collection
    {
        return $school->academicYears()->wherePivot('active', true)->orderByDesc('academic_years.year')
            ->get(['academic_years.id', 'academic_years.name', 'academic_years.year', 'academic_years.is_active', 'academic_years.is_closed'])
            ->unique('id')->values();
    }

    public function courses(School $school, int $academicYearId): Collection
    {
        abort_unless($school->academicYears()->where('academic_years.id', $academicYearId)->wherePivot('active', true)->exists(), 422, 'El año académico no está habilitado para el establecimiento.');

        return CourseSection::query()->where('academic_year_id', $academicYearId)->where('active', true)
            ->with('educationLevel:id,name,type')->orderBy('display_name')->limit(250)
            ->get(['id', 'academic_year_id', 'education_level_id', 'display_name'])
            ->map(fn (CourseSection $course): array => [
                'id' => $course->id, 'name' => $course->display_name,
                'education_level_id' => $course->education_level_id,
                'level' => $course->educationLevel?->name, 'level_type' => $course->educationLevel?->type,
                'allows_children_style' => in_array($course->educationLevel?->type, ['parvularia'], true)
                    || preg_match('/^(1|2|3|4)\D/', (string) $course->display_name) === 1,
            ]);
    }

    public function subjects(CourseSection $course): Collection
    {
        return ScheduleSubject::query()->where('active', true)
            ->whereHas('curriculumPrograms', fn (Builder $programs) => $programs
                ->where('education_level_id', $course->education_level_id)->where('status', 'published'))
            ->with('catalogProfile:id,schedule_subject_id,display_name')->orderBy('name')->limit(250)
            ->get(['id', 'name', 'code', 'color'])
            ->map(fn (ScheduleSubject $subject): array => ['id' => $subject->id, 'name' => $subject->resolvedDisplayName(), 'code' => $subject->code, 'color' => $subject->color]);
    }

    public function units(CourseSection $course, ScheduleSubject $subject): Collection
    {
        return CurriculumUnit::query()
            ->whereHas('program', fn (Builder $programs) => $programs
                ->where('education_level_id', $course->education_level_id)
                ->where('schedule_subject_id', $subject->id)
                ->where('status', 'published'))
            ->orderBy('official_order')->limit(100)
            ->get(['id', 'public_id', 'curriculum_program_id', 'unit_code', 'official_title', 'friendly_focus', 'purpose', 'official_order'])
            ->map(fn (CurriculumUnit $unit): array => [
                'id' => $unit->id, 'public_id' => $unit->public_id, 'code' => $unit->unit_code,
                'title' => $unit->official_title ?: $unit->friendly_focus ?: $unit->unit_code,
                'focus' => $unit->friendly_focus, 'purpose' => $unit->purpose,
            ]);
    }

    public function objectives(CurriculumUnit $unit): Collection
    {
        return $unit->learningObjectives()->where('lcd_learning_objectives.active', true)
            ->orderBy('lcd_curriculum_unit_objectives.official_order')->limit(250)
            ->get(['lcd_learning_objectives.id', 'lcd_learning_objectives.public_id', 'lcd_learning_objectives.code', 'lcd_learning_objectives.description'])
            ->map(fn (LearningObjective $objective): array => ['id' => $objective->id, 'code' => $objective->code, 'description' => $objective->description]);
    }

    public function course(int $courseId, int $academicYearId): CourseSection
    {
        return CourseSection::query()->whereKey($courseId)->where('academic_year_id', $academicYearId)->where('active', true)->with('educationLevel:id,name,type')->firstOrFail();
    }

    public function subjectForCourse(CourseSection $course, int $subjectId): ScheduleSubject
    {
        return ScheduleSubject::query()->whereKey($subjectId)->where('active', true)
            ->whereHas('curriculumPrograms', fn (Builder $programs) => $programs->where('education_level_id', $course->education_level_id)->where('status', 'published'))
            ->firstOrFail();
    }

    public function unitForCourseSubject(CourseSection $course, ScheduleSubject $subject, int $unitId): CurriculumUnit
    {
        return CurriculumUnit::query()->whereKey($unitId)
            ->whereHas('program', fn (Builder $programs) => $programs
                ->where('education_level_id', $course->education_level_id)->where('schedule_subject_id', $subject->id)->where('status', 'published'))
            ->firstOrFail();
    }

    /** @param list<int> $objectiveIds */
    public function selectedObjectives(CurriculumUnit $unit, array $objectiveIds): Collection
    {
        $ids = collect($objectiveIds)->map(fn ($id): int => (int) $id)->unique()->values();
        $objectives = $unit->learningObjectives()->where('lcd_learning_objectives.active', true)
            ->whereIn('lcd_learning_objectives.id', $ids)->get(['lcd_learning_objectives.id', 'lcd_learning_objectives.code', 'lcd_learning_objectives.description']);
        abort_unless($objectives->count() === $ids->count(), 422, 'Todos los objetivos deben pertenecer a la unidad seleccionada.');

        return $objectives;
    }
}
