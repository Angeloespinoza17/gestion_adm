<?php

namespace Tests\Unit\Schedule;

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
use App\Services\Schedule\ScheduleSummaryService;
use App\Services\Schedule\ScheduleValidationService;
use App\Services\Schedule\StudyPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_detects_recess_and_outside_jornada(): void
    {
        $context = $this->context();
        $service = app(ScheduleValidationService::class);

        $recessIssues = $service->validateScheduleEvent([
            'academic_year_id' => $context['year']->id,
            'staff_id' => $context['teacher']->id,
            'teacher_schedule_layer_id' => $context['lectiveLayer']->id,
            'course_section_id' => $context['course']->id,
            'schedule_subject_id' => $context['subject']->id,
            'day_of_week' => 1,
            'start_time' => '09:30',
            'end_time' => '09:45',
            'activity_type' => 'lective_class',
            'pedagogical_hours' => 0.33,
        ]);

        $outsideIssues = $service->validateScheduleEvent([
            'academic_year_id' => $context['year']->id,
            'staff_id' => $context['teacher']->id,
            'teacher_schedule_layer_id' => $context['lectiveLayer']->id,
            'course_section_id' => $context['course']->id,
            'schedule_subject_id' => $context['subject']->id,
            'day_of_week' => 1,
            'start_time' => '17:00',
            'end_time' => '17:45',
            'activity_type' => 'lective_class',
            'pedagogical_hours' => 1,
        ]);

        $this->assertContains('non_assignable_block', collect($recessIssues)->pluck('code')->all());
        $this->assertContains('outside_jornada', collect($outsideIssues)->pluck('code')->all());
    }

    public function test_it_counts_hidden_layers_for_teacher_conflicts(): void
    {
        $context = $this->context();
        $service = app(ScheduleValidationService::class);

        ScheduleEvent::query()->create([
            'academic_year_id' => $context['year']->id,
            'staff_id' => $context['teacher']->id,
            'teacher_schedule_layer_id' => $context['lectiveLayer']->id,
            'course_section_id' => $context['course']->id,
            'education_level_id' => $context['level']->id,
            'schedule_subject_id' => $context['subject']->id,
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '08:45',
            'activity_type' => 'lective_class',
            'pedagogical_hours' => 1,
            'minutes' => 45,
            'status' => 'draft',
            'source' => 'manual',
        ]);

        $issues = $service->validateScheduleEvent([
            'academic_year_id' => $context['year']->id,
            'staff_id' => $context['teacher']->id,
            'teacher_schedule_layer_id' => $context['hiddenLayer']->id,
            'day_of_week' => 1,
            'start_time' => '08:15',
            'end_time' => '09:00',
            'activity_type' => 'non_lective',
            'pedagogical_hours' => 1,
        ]);

        $this->assertContains('teacher_overlap', collect($issues)->pluck('code')->all());
    }

    public function test_adjacent_events_in_different_layers_are_not_conflicts(): void
    {
        $context = $this->context();
        $service = app(ScheduleValidationService::class);

        ScheduleEvent::query()->create([
            'academic_year_id' => $context['year']->id,
            'staff_id' => $context['teacher']->id,
            'teacher_schedule_layer_id' => $context['lectiveLayer']->id,
            'course_section_id' => $context['course']->id,
            'education_level_id' => $context['level']->id,
            'schedule_subject_id' => $context['subject']->id,
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '08:45',
            'activity_type' => 'lective_class',
            'pedagogical_hours' => 1,
            'minutes' => 45,
            'status' => 'draft',
            'source' => 'manual',
        ]);

        $issues = $service->validateScheduleEvent([
            'academic_year_id' => $context['year']->id,
            'staff_id' => $context['teacher']->id,
            'teacher_schedule_layer_id' => $context['hiddenLayer']->id,
            'day_of_week' => 1,
            'start_time' => '08:45',
            'end_time' => '09:30',
            'activity_type' => 'non_lective',
            'pedagogical_hours' => 1,
        ]);

        $this->assertNotContains('teacher_overlap', collect($issues)->pluck('code')->all());
    }

    public function test_it_calculates_study_plan_progress_and_teacher_load(): void
    {
        $context = $this->context();

        ScheduleEvent::query()->create([
            'academic_year_id' => $context['year']->id,
            'staff_id' => $context['teacher']->id,
            'teacher_schedule_layer_id' => $context['lectiveLayer']->id,
            'course_section_id' => $context['course']->id,
            'education_level_id' => $context['level']->id,
            'schedule_subject_id' => $context['subject']->id,
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '09:30',
            'activity_type' => 'lective_class',
            'pedagogical_hours' => 2,
            'minutes' => 90,
            'status' => 'draft',
            'source' => 'manual',
        ]);

        ScheduleEvent::query()->create([
            'academic_year_id' => $context['year']->id,
            'staff_id' => $context['teacher']->id,
            'teacher_schedule_layer_id' => $context['hiddenLayer']->id,
            'day_of_week' => 2,
            'start_time' => '11:30',
            'end_time' => '12:15',
            'activity_type' => 'non_lective',
            'pedagogical_hours' => 1,
            'minutes' => 45,
            'status' => 'draft',
            'source' => 'manual',
        ]);

        $progress = app(StudyPlanService::class)->progressForCourse($context['course']);
        $summary = app(ScheduleSummaryService::class)->teacherWeeklySummary($context['teacher'], $context['year']->id);

        $this->assertSame(4.0, $progress[0]['pending_hours']);
        $this->assertSame(2.0, $summary['assigned_lective_hours']);
        $this->assertSame(1.0, $summary['assigned_non_lective_hours']);
    }

    /**
     * @return array<string, mixed>
     */
    private function context(): array
    {
        $year = AcademicYear::query()->create([
            'name' => '2026',
            'year' => 2026,
            'is_active' => true,
        ]);

        SchoolScheduleConfig::query()->create([
            'academic_year_id' => $year->id,
            'pedagogical_hour_minutes' => 45,
            'default_lective_percentage' => 65,
            'default_non_lective_percentage' => 35,
            'calculation_base' => 'pedagogical',
            'rounding_mode' => 'nearest',
            'strict_validation_enabled' => true,
        ]);

        $jornada = SchoolDayTemplate::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'Jornada Test',
            'start_time' => '08:00',
            'end_time' => '12:15',
            'days_of_week' => [1, 2, 3, 4, 5],
            'active' => true,
        ]);

        foreach ([1, 2, 3, 4, 5] as $day) {
            foreach ([
                ['08:00', '08:45', 'pedagogical_block', true],
                ['08:45', '09:30', 'pedagogical_block', true],
                ['09:30', '09:45', 'recess', false],
                ['09:45', '10:30', 'pedagogical_block', true],
            ] as $index => [$start, $end, $type, $assignable]) {
                $jornada->blocks()->create([
                    'day_of_week' => $day,
                    'start_time' => $start,
                    'end_time' => $end,
                    'type' => $type,
                    'label' => $type,
                    'order' => $index + 1,
                    'assignable' => $assignable,
                    'pedagogical_hours_equivalent' => $assignable ? 1 : null,
                ]);
            }
        }

        $level = EducationLevel::query()->create([
            'name' => '7° básico',
            'order' => 9,
            'type' => 'basica',
            'default_school_day_template_id' => $jornada->id,
        ]);

        $course = CourseSection::query()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'section_name' => 'A',
            'display_name' => '7° básico A',
            'capacity' => 35,
            'active' => true,
        ]);

        $teacher = Staff::query()->create([
            'full_name' => 'Docente Test',
            'rut' => '12345678-5',
            'institutional_email' => 'docente.test@example.com',
            'status' => 'activo',
            'active' => true,
            'contract_hours' => 44,
        ]);

        TeacherContract::query()->create([
            'staff_id' => $teacher->id,
            'academic_year_id' => $year->id,
            'weekly_contract_hours' => 44,
            'hour_type' => 'pedagogical',
            'lective_percentage' => 65,
            'non_lective_percentage' => 35,
            'calculated_lective_hours' => 28.6,
            'calculated_non_lective_hours' => 15.4,
            'active' => true,
        ]);

        $lectiveLayer = TeacherScheduleLayer::query()->create([
            'staff_id' => $teacher->id,
            'academic_year_id' => $year->id,
            'name' => 'Lectiva',
            'type' => 'lective',
            'color' => '#0d6efd',
            'visible_by_default' => true,
            'priority' => 1,
            'active' => true,
        ]);

        $hiddenLayer = TeacherScheduleLayer::query()->create([
            'staff_id' => $teacher->id,
            'academic_year_id' => $year->id,
            'name' => 'No lectiva oculta',
            'type' => 'non_lective',
            'color' => '#198754',
            'visible_by_default' => false,
            'priority' => 2,
            'active' => true,
        ]);

        $subject = ScheduleSubject::query()->create([
            'name' => 'Matematica',
            'code' => 'MAT',
            'color' => '#0d6efd',
            'active' => true,
        ]);

        $plan = StudyPlan::query()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'name' => 'Plan test',
            'active' => true,
        ]);

        $plan->subjects()->create([
            'schedule_subject_id' => $subject->id,
            'weekly_pedagogical_hours' => 6,
            'required' => true,
        ]);

        return compact('year', 'jornada', 'level', 'course', 'teacher', 'lectiveLayer', 'hiddenLayer', 'subject', 'plan');
    }
}
