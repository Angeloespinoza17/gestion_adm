<?php

namespace Database\Seeders\Modules;

use Database\Seeders\Support\PreventsProductionSeeding;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\Schedule\ScheduleEvent;
use App\Models\Schedule\ScheduleSubject;
use App\Models\Schedule\SchoolDayTemplate;
use App\Models\Schedule\SchoolScheduleConfig;
use App\Models\Schedule\StudyPlan;
use App\Models\Schedule\TeacherContract;
use App\Models\Schedule\TeacherScheduleLayer;
use App\Models\Staff;
use App\Services\Schedule\ScheduleTimeCalculator;
use Database\Seeders\Support\ModuleSeeder;

class ScheduleModuleSeeder extends ModuleSeeder
{
    use PreventsProductionSeeding;

    public function run(): void
    {
        $this->preventProductionSeeding();
        $year = AcademicYear::query()->where('is_active', true)->first()
            ?: AcademicYear::query()->orderByDesc('year')->first();

        if (!$year) {
            return;
        }

        $calculator = app(ScheduleTimeCalculator::class);

        SchoolScheduleConfig::query()->updateOrCreate(
            ['academic_year_id' => $year->id],
            [
                'pedagogical_hour_minutes' => 45,
                'default_lective_percentage' => 65,
                'default_non_lective_percentage' => 35,
                'calculation_base' => 'pedagogical',
                'rounding_mode' => 'nearest',
                'strict_validation_enabled' => true,
            ],
        );

        $basic = $this->seedJornada($year, 'Jornada Basica', '08:00', '15:30', [
            ['08:00', '08:45', 'pedagogical_block', 'Bloque 1', true],
            ['08:45', '09:30', 'pedagogical_block', 'Bloque 2', true],
            ['09:30', '09:45', 'recess', 'Recreo manana', false],
            ['09:45', '10:30', 'pedagogical_block', 'Bloque 3', true],
            ['10:30', '11:15', 'pedagogical_block', 'Bloque 4', true],
            ['11:15', '11:30', 'recess', 'Recreo media manana', false],
            ['11:30', '12:15', 'pedagogical_block', 'Bloque 5', true],
            ['12:15', '13:00', 'pedagogical_block', 'Bloque 6', true],
            ['13:00', '13:45', 'lunch', 'Almuerzo', false],
            ['13:45', '14:30', 'pedagogical_block', 'Bloque 7', true],
            ['14:30', '15:15', 'pedagogical_block', 'Bloque 8', true],
        ]);

        $media = $this->seedJornada($year, 'Jornada Media', '08:00', '16:15', [
            ['08:00', '08:45', 'pedagogical_block', 'Bloque 1', true],
            ['08:45', '09:30', 'pedagogical_block', 'Bloque 2', true],
            ['09:30', '09:45', 'recess', 'Recreo manana', false],
            ['09:45', '10:30', 'pedagogical_block', 'Bloque 3', true],
            ['10:30', '11:15', 'pedagogical_block', 'Bloque 4', true],
            ['11:15', '11:30', 'recess', 'Recreo media manana', false],
            ['11:30', '12:15', 'pedagogical_block', 'Bloque 5', true],
            ['12:15', '13:00', 'pedagogical_block', 'Bloque 6', true],
            ['13:00', '13:45', 'lunch', 'Almuerzo', false],
            ['13:45', '14:30', 'pedagogical_block', 'Bloque 7', true],
            ['14:30', '15:15', 'pedagogical_block', 'Bloque 8', true],
            ['15:15', '15:30', 'recess', 'Recreo tarde', false],
            ['15:30', '16:15', 'pedagogical_block', 'Bloque 9', true],
        ]);

        EducationLevel::query()->where('type', 'basica')->update(['default_school_day_template_id' => $basic->id]);
        EducationLevel::query()->where('type', 'media')->update(['default_school_day_template_id' => $media->id]);

        $subjects = $this->seedSubjects();
        $this->seedStudyPlans($year, $subjects);
        $this->seedTeacherContractsAndLayers($year, $calculator);
        $this->seedEvents($year, $subjects, $calculator);
    }

    private function seedJornada(AcademicYear $year, string $name, string $start, string $end, array $blocks): SchoolDayTemplate
    {
        $jornada = SchoolDayTemplate::query()->updateOrCreate(
            ['academic_year_id' => $year->id, 'name' => $name],
            [
                'start_time' => $start,
                'end_time' => $end,
                'days_of_week' => [1, 2, 3, 4, 5],
                'active' => true,
                'notes' => 'Jornada demo configurable para planificacion docente.',
            ],
        );

        $jornada->blocks()->delete();

        foreach ([1, 2, 3, 4, 5] as $day) {
            foreach ($blocks as $index => [$blockStart, $blockEnd, $type, $label, $assignable]) {
                $jornada->blocks()->create([
                    'day_of_week' => $day,
                    'start_time' => $blockStart,
                    'end_time' => $blockEnd,
                    'type' => $type,
                    'label' => $label,
                    'order' => $index + 1,
                    'assignable' => $assignable,
                    'pedagogical_hours_equivalent' => $assignable ? 1 : null,
                ]);
            }
        }

        return $jornada->fresh('blocks');
    }

    /**
     * @return array<string, ScheduleSubject>
     */
    private function seedSubjects(): array
    {
        $definitions = [
            'MAT' => ['Matematica', '#0d6efd', 'Ciencias'],
            'LEN' => ['Lenguaje', '#d63384', 'Humanidades'],
            'HIS' => ['Historia', '#fd7e14', 'Humanidades'],
            'CIE' => ['Ciencias Naturales', '#198754', 'Ciencias'],
            'ING' => ['Ingles', '#6f42c1', 'Idiomas'],
            'ART' => ['Artes Visuales', '#20c997', 'Artes'],
        ];

        $subjects = [];
        foreach ($definitions as $code => [$name, $color, $area]) {
            $subjects[$code] = ScheduleSubject::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'color' => $color, 'area' => $area, 'active' => true],
            );
        }

        return $subjects;
    }

    /**
     * @param array<string, ScheduleSubject> $subjects
     */
    private function seedStudyPlans(AcademicYear $year, array $subjects): void
    {
        $definitions = [
            '7° básico' => ['MAT' => 6, 'LEN' => 6, 'HIS' => 4, 'CIE' => 4, 'ING' => 3, 'ART' => 2],
            '1° medio' => ['MAT' => 7, 'LEN' => 6, 'HIS' => 4, 'CIE' => 4, 'ING' => 4, 'ART' => 2],
        ];

        foreach ($definitions as $levelName => $hoursBySubject) {
            $level = EducationLevel::query()->where('name', $levelName)->first();
            if (!$level) {
                continue;
            }

            $plan = StudyPlan::query()->updateOrCreate(
                ['academic_year_id' => $year->id, 'education_level_id' => $level->id, 'course_section_id' => null],
                ['name' => "Plan {$levelName} {$year->name}", 'active' => true],
            );

            foreach ($hoursBySubject as $code => $hours) {
                $plan->subjects()->updateOrCreate(
                    ['schedule_subject_id' => $subjects[$code]->id],
                    ['weekly_pedagogical_hours' => $hours, 'required' => true],
                );
            }
        }
    }

    private function seedTeacherContractsAndLayers(AcademicYear $year, ScheduleTimeCalculator $calculator): void
    {
        $teachers = Staff::query()
            ->whereIn('institutional_email', ['andrea.medina@cnscgestion.local', 'daniela.castillo@cnscgestion.local', 'paula.vargas@cnscgestion.local'])
            ->get();

        foreach ($teachers as $teacher) {
            $hours = (float) ($teacher->contract_hours ?: 44);
            $distribution = $calculator->calculateDistribution($hours, 65, 35, 'nearest');

            TeacherContract::query()->updateOrCreate(
                ['staff_id' => $teacher->id, 'academic_year_id' => $year->id],
                [
                    'weekly_contract_hours' => $hours,
                    'hour_type' => 'chronological',
                    'lective_percentage' => 65,
                    'non_lective_percentage' => 35,
                    'calculated_lective_hours' => $distribution['lective'],
                    'calculated_non_lective_hours' => $distribution['non_lective'],
                    'valid_from' => $year->starts_at,
                    'valid_to' => $year->ends_at,
                    'active' => true,
                ],
            );

            foreach ([
                ['Clases lectivas', 'lective', '#0d6efd', 1],
                ['Horas no lectivas', 'non_lective', '#198754', 2],
                ['Coordinacion', 'coordination', '#fd7e14', 3],
                ['Bloqueos', 'availability_block', '#6c757d', 9],
            ] as [$name, $type, $color, $priority]) {
                TeacherScheduleLayer::query()->updateOrCreate(
                    ['staff_id' => $teacher->id, 'academic_year_id' => $year->id, 'type' => $type, 'name' => $name],
                    ['color' => $color, 'visible_by_default' => true, 'priority' => $priority, 'active' => true],
                );
            }
        }
    }

    /**
     * @param array<string, ScheduleSubject> $subjects
     */
    private function seedEvents(AcademicYear $year, array $subjects, ScheduleTimeCalculator $calculator): void
    {
        $mathTeacher = Staff::query()->where('institutional_email', 'daniela.castillo@cnscgestion.local')->first();
        $scienceTeacher = Staff::query()->where('institutional_email', 'andrea.medina@cnscgestion.local')->first();
        $course7 = CourseSection::query()
            ->where('academic_year_id', $year->id)
            ->whereHas('educationLevel', fn ($query) => $query->where('name', '7° básico'))
            ->where('section_name', 'A')
            ->first();

        if (!$mathTeacher || !$scienceTeacher || !$course7) {
            return;
        }

        $levelId = $course7->education_level_id;
        $jornadaId = $course7->school_day_template_id ?: $course7->educationLevel?->default_school_day_template_id;
        $events = [
            [$mathTeacher, 'lective', $subjects['MAT'], 1, '08:00', '09:30', 'lective_class', 'Matematica 7A'],
            [$mathTeacher, 'non_lective', null, 1, '11:30', '12:15', 'non_lective', 'Planificacion matematica'],
            [$scienceTeacher, 'lective', $subjects['CIE'], 2, '09:45', '11:15', 'lective_class', 'Ciencias 7A'],
            [$scienceTeacher, 'availability_block', null, 4, '14:30', '15:15', 'availability_block', 'No disponible'],
        ];

        foreach ($events as [$teacher, $layerType, $subject, $day, $start, $end, $activityType, $notes]) {
            $layer = TeacherScheduleLayer::query()
                ->where('staff_id', $teacher->id)
                ->where('academic_year_id', $year->id)
                ->where('type', $layerType)
                ->first();

            if (!$layer) {
                continue;
            }

            $minutes = $calculator->minutesBetween($start, $end);

            ScheduleEvent::query()->updateOrCreate(
                [
                    'academic_year_id' => $year->id,
                    'staff_id' => $teacher->id,
                    'day_of_week' => $day,
                    'start_time' => $start,
                    'end_time' => $end,
                    'activity_type' => $activityType,
                    'notes' => $notes,
                ],
                [
                    'teacher_schedule_layer_id' => $layer->id,
                    'course_section_id' => $activityType === 'lective_class' ? $course7->id : null,
                    'education_level_id' => $activityType === 'lective_class' ? $levelId : null,
                    'schedule_subject_id' => $subject?->id,
                    'school_day_template_id' => $activityType === 'lective_class' ? $jornadaId : null,
                    'school_day_block_id' => null,
                    'pedagogical_hours' => $calculator->minutesToPedagogicalHours($minutes, 45, 'nearest'),
                    'minutes' => $minutes,
                    'status' => 'draft',
                    'source' => 'manual',
                ],
            );
        }
    }
}
