<?php

namespace App\Services\Schedule;

use App\Models\CourseSection;
use App\Models\Schedule\ScheduleEvent;
use App\Models\Schedule\SchoolDayBlock;
use App\Models\Schedule\TeacherContract;
use App\Models\Schedule\TeacherScheduleLayer;
use App\Models\Staff;

class ScheduleValidationService
{
    public function __construct(
        private readonly ScheduleTimeCalculator $calculator,
        private readonly JornadaService $jornadaService,
        private readonly StudyPlanService $studyPlanService,
        private readonly ScheduleSummaryService $summaryService,
    ) {
    }

    /**
     * @param array<string, mixed>|ScheduleEvent $event
     * @return array<int, array<string, mixed>>
     */
    public function validateScheduleEvent(array|ScheduleEvent $event): array
    {
        $data = $event instanceof ScheduleEvent ? $event->toArray() : $event;
        $eventId = $event instanceof ScheduleEvent ? $event->id : ($data['id'] ?? null);

        $issues = [];
        $academicYearId = (int) ($data['academic_year_id'] ?? 0);
        $teacherId = (int) ($data['staff_id'] ?? 0);
        $courseId = $data['course_section_id'] ?? null;
        $dayOfWeek = (int) ($data['day_of_week'] ?? 0);
        $startTime = (string) ($data['start_time'] ?? '');
        $endTime = (string) ($data['end_time'] ?? '');
        $activityType = (string) ($data['activity_type'] ?? 'lective_class');

        if ($startTime === '' || $endTime === '' || $this->calculator->minutesBetween($startTime, $endTime) <= 0) {
            return [$this->issue('error', 'invalid_time_range', 'La hora de termino debe ser posterior a la hora de inicio.', $eventId, 'schedule_event', $eventId)];
        }

        $layer = !empty($data['teacher_schedule_layer_id'])
            ? TeacherScheduleLayer::query()->find($data['teacher_schedule_layer_id'])
            : null;

        if (!$layer) {
            $issues[] = $this->issue('error', 'missing_layer', 'El bloque debe pertenecer a una capa de horario.', $eventId, 'schedule_event', $eventId);
        } elseif (!$layer->active) {
            $issues[] = $this->issue('error', 'inactive_layer', 'La capa seleccionada esta inactiva.', $eventId, 'teacher_schedule_layer', $layer->id);
        }

        $teacherConflicts = ScheduleEvent::query()
            ->with('layer:id,name,type')
            ->where('academic_year_id', $academicYearId)
            ->where('staff_id', $teacherId)
            ->where('day_of_week', $dayOfWeek)
            ->when($eventId, fn ($query) => $query->where('id', '!=', $eventId))
            ->get()
            ->filter(fn (ScheduleEvent $existing) => $this->calculator->overlaps($startTime, $endTime, $existing->start_time, $existing->end_time));

        foreach ($teacherConflicts as $conflict) {
            $severity = $conflict->activity_type === 'availability_block' || $conflict->layer?->type === 'availability_block'
                ? 'error'
                : 'error';
            $issues[] = $this->issue(
                $severity,
                'teacher_overlap',
                'El docente ya tiene otro bloque en ese horario, aunque este en otra capa.',
                $eventId,
                'staff',
                $teacherId,
                ['conflicting_event_id' => $conflict->id],
            );
        }

        if ($courseId) {
            $courseConflicts = ScheduleEvent::query()
                ->where('academic_year_id', $academicYearId)
                ->where('course_section_id', $courseId)
                ->where('day_of_week', $dayOfWeek)
                ->when($eventId, fn ($query) => $query->where('id', '!=', $eventId))
                ->get()
                ->filter(fn (ScheduleEvent $existing) => $this->calculator->overlaps($startTime, $endTime, $existing->start_time, $existing->end_time));

            foreach ($courseConflicts as $conflict) {
                $issues[] = $this->issue(
                    'error',
                    'course_overlap',
                    'El curso ya tiene otro bloque asignado en ese horario.',
                    $eventId,
                    'course_section',
                    (int) $courseId,
                    ['conflicting_event_id' => $conflict->id],
                );
            }

            $course = CourseSection::query()->with('educationLevel')->find($courseId);
            if ($course) {
                $issues = array_merge($issues, $this->validateCourseJornada($course, $dayOfWeek, $startTime, $endTime, $eventId));
                $issues = array_merge($issues, $this->validateStudyPlan($course, $data, $eventId));
            }
        }

        if ($teacherId) {
            $teacher = Staff::query()->find($teacherId);
            if ($teacher) {
                $issues = array_merge($issues, $this->validateTeacherContract($teacher, $academicYearId, $data, $eventId, $activityType, $layer?->type));
            }
        }

        return $this->deduplicateIssues($issues);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function validateTeacherSchedule(Staff $teacher, int $academicYearId): array
    {
        return ScheduleEvent::query()
            ->where('staff_id', $teacher->id)
            ->where('academic_year_id', $academicYearId)
            ->get()
            ->flatMap(fn (ScheduleEvent $event) => $this->validateScheduleEvent($event))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function validateCourseSchedule(CourseSection $courseSection): array
    {
        return ScheduleEvent::query()
            ->where('course_section_id', $courseSection->id)
            ->where('academic_year_id', $courseSection->academic_year_id)
            ->get()
            ->flatMap(fn (ScheduleEvent $event) => $this->validateScheduleEvent($event))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function validateCourseJornada(CourseSection $course, int $dayOfWeek, string $startTime, string $endTime, ?int $eventId): array
    {
        $issues = [];
        $jornada = $this->jornadaService->jornadaForCourse($course);

        if (!$jornada) {
            return [$this->issue('error', 'course_without_jornada', 'El curso no tiene jornada configurada.', $eventId, 'course_section', $course->id)];
        }

        $days = array_map('intval', $jornada->days_of_week ?: []);
        if (!in_array($dayOfWeek, $days, true)) {
            $issues[] = $this->issue('error', 'day_outside_jornada', 'La jornada del curso no aplica para ese dia.', $eventId, 'course_section', $course->id);
        }

        if (!$this->calculator->contains($jornada->start_time, $jornada->end_time, $startTime, $endTime)) {
            $issues[] = $this->issue('error', 'outside_jornada', 'El bloque queda fuera de la hora de inicio o termino de la jornada.', $eventId, 'course_section', $course->id);
        }

        $dayBlocks = $jornada->blocks->where('day_of_week', $dayOfWeek);
        $assignableIntersection = false;

        foreach ($dayBlocks as $block) {
            if (!$this->calculator->overlaps($startTime, $endTime, $block->start_time, $block->end_time)) {
                continue;
            }

            if (!$block->assignable || in_array($block->type, [SchoolDayBlock::TYPE_RECESS, SchoolDayBlock::TYPE_LUNCH, SchoolDayBlock::TYPE_NON_ASSIGNABLE], true)) {
                $issues[] = $this->issue('error', 'non_assignable_block', 'El bloque intersecta recreo, almuerzo u otro tramo no asignable.', $eventId, 'school_day_block', $block->id);
            } else {
                $assignableIntersection = true;
            }
        }

        if (!$assignableIntersection && $dayBlocks->isNotEmpty()) {
            $issues[] = $this->issue('warning', 'no_assignable_block_match', 'El bloque no coincide con ningun tramo pedagogico asignable de la jornada.', $eventId, 'course_section', $course->id);
        }

        return $issues;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, array<string, mixed>>
     */
    private function validateStudyPlan(CourseSection $course, array $data, ?int $eventId): array
    {
        $subjectId = $data['schedule_subject_id'] ?? null;
        if (!$subjectId || !in_array($data['activity_type'] ?? null, ['lective_class', 'class', 'jefatura_course'], true)) {
            return [];
        }

        $planSubject = $this->studyPlanService->planSubjectForCourse($course, (int) $subjectId);
        if (!$planSubject) {
            return [$this->issue('warning', 'subject_not_in_study_plan', 'La asignatura no pertenece al plan de estudio vigente del curso o nivel.', $eventId, 'schedule_subject', (int) $subjectId)];
        }

        $currentAssigned = ScheduleEvent::query()
            ->where('academic_year_id', $course->academic_year_id)
            ->where('course_section_id', $course->id)
            ->where('schedule_subject_id', $subjectId)
            ->whereIn('activity_type', ['lective_class', 'class', 'jefatura_course'])
            ->when($eventId, fn ($query) => $query->where('id', '!=', $eventId))
            ->sum('pedagogical_hours');

        $nextAssigned = (float) $currentAssigned + (float) ($data['pedagogical_hours'] ?? 0);

        if ($nextAssigned > (float) $planSubject->weekly_pedagogical_hours) {
            return [$this->issue('warning', 'study_plan_exceeded', 'La asignacion excede las horas semanales definidas en el plan de estudio.', $eventId, 'study_plan_subject', $planSubject->id, [
                'required_hours' => (float) $planSubject->weekly_pedagogical_hours,
                'assigned_hours' => round($nextAssigned, 2),
            ])];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, array<string, mixed>>
     */
    private function validateTeacherContract(Staff $teacher, int $academicYearId, array $data, ?int $eventId, string $activityType, ?string $layerType): array
    {
        $contract = TeacherContract::query()
            ->where('staff_id', $teacher->id)
            ->where('academic_year_id', $academicYearId)
            ->where('active', true)
            ->latest('id')
            ->first();

        if (!$contract) {
            return [$this->issue('warning', 'teacher_without_contract', 'El docente no tiene contrato activo para el ano academico seleccionado.', $eventId, 'staff', $teacher->id)];
        }

        $summary = $this->summaryService->teacherWeeklySummary($teacher, $academicYearId);
        $eventHours = (float) ($data['pedagogical_hours'] ?? 0);
        $existing = $eventId ? ScheduleEvent::query()->with('layer')->find($eventId) : null;

        $lectiveAssigned = (float) $summary['assigned_lective_hours'];
        $nonLectiveAssigned = (float) $summary['assigned_non_lective_hours'];

        if ($existing) {
            if ($this->calculator->isLectiveActivity($existing->activity_type, $existing->layer?->type)) {
                $lectiveAssigned -= (float) $existing->pedagogical_hours;
            } elseif ($this->calculator->isNonLectiveActivity($existing->activity_type, $existing->layer?->type)) {
                $nonLectiveAssigned -= (float) $existing->pedagogical_hours;
            }
        }

        if ($this->calculator->isLectiveActivity($activityType, $layerType)) {
            $lectiveAssigned += $eventHours;
        } elseif ($this->calculator->isNonLectiveActivity($activityType, $layerType)) {
            $nonLectiveAssigned += $eventHours;
        }

        $issues = [];

        if ($lectiveAssigned > (float) $contract->calculated_lective_hours) {
            $issues[] = $this->issue('warning', 'teacher_lective_hours_exceeded', 'El docente supera sus horas lectivas esperadas.', $eventId, 'staff', $teacher->id, [
                'expected' => (float) $contract->calculated_lective_hours,
                'assigned' => round($lectiveAssigned, 2),
            ]);
        }

        if ($nonLectiveAssigned > (float) $contract->calculated_non_lective_hours) {
            $issues[] = $this->issue('warning', 'teacher_non_lective_hours_exceeded', 'El docente supera sus horas no lectivas esperadas.', $eventId, 'staff', $teacher->id, [
                'expected' => (float) $contract->calculated_non_lective_hours,
                'assigned' => round($nonLectiveAssigned, 2),
            ]);
        }

        if (($lectiveAssigned + $nonLectiveAssigned) > (float) $contract->weekly_contract_hours) {
            $issues[] = $this->issue('warning', 'teacher_contract_hours_exceeded', 'El docente supera el total de horas contractuales semanales.', $eventId, 'staff', $teacher->id, [
                'expected' => (float) $contract->weekly_contract_hours,
                'assigned' => round($lectiveAssigned + $nonLectiveAssigned, 2),
            ]);
        }

        return $issues;
    }

    /**
     * @return array<string, mixed>
     */
    public function issue(
        string $severity,
        string $code,
        string $message,
        ?int $scheduleEventId,
        string $entityType,
        ?int $entityId,
        array $metadata = [],
    ): array {
        return [
            'schedule_event_id' => $scheduleEventId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'severity' => $severity,
            'code' => $code,
            'message' => $message,
            'metadata' => $metadata,
            'resolved' => false,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $issues
     * @return array<int, array<string, mixed>>
     */
    private function deduplicateIssues(array $issues): array
    {
        $seen = [];

        return array_values(array_filter($issues, function (array $issue) use (&$seen) {
            $key = implode('|', [
                $issue['code'] ?? '',
                $issue['entity_type'] ?? '',
                $issue['entity_id'] ?? '',
                $issue['metadata']['conflicting_event_id'] ?? '',
            ]);

            if (isset($seen[$key])) {
                return false;
            }

            $seen[$key] = true;

            return true;
        }));
    }
}
